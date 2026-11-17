<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dictamen de viabilidad de la evaluación de 5°: la propuesta ya se validó como bien formulada; aquí se decide si el
 * proyecto es viable para desarrollarse. Cada evaluador vota en su rúbrica y coordinación o jefatura confirma el
 * dictamen final (puede ajustarlo con motivo). Al confirmarlo:
 * - viable: el proyecto sigue igual.
 * - correcciones: la propuesta vuelve a "requiere cambios" con las observaciones y un plazo; se valida otra vez.
 * - no_viable: el proyecto queda en el historial como no viable y el equipo queda libre para otra propuesta
 *   (por ejemplo, cuando se abra el registro de propuestas para 6°).
 */
class ViabilityService
{
    public const OPTIONS = ['viable' => 'Viable', 'correcciones' => 'Viable con correcciones', 'no_viable' => 'No viable'];
    /** En empate gana el resultado más estricto. */
    private const STRICTNESS = ['no_viable' => 3, 'correcciones' => 2, 'viable' => 1];

    public static function ready(): bool
    {
        return SchemaCache::hasColumn('evaluaciones', 'viabilidad') && SchemaCache::hasColumn('dictamenes_docentes', 'viabilidad');
    }

    public function summary(Evaluation $evaluation): array
    {
        if ((int) $evaluation->semestre !== 5 || !self::ready()) {
            return ['applies' => false];
        }
        $attempts = $evaluation->relationLoaded('attempts') ? $evaluation->attempts : $evaluation->attempts()->get();
        $votes = array_fill_keys(array_keys(self::OPTIONS), 0);
        foreach ($attempts as $attempt) {
            $vote = $attempt->getAttribute('viabilidad');
            if ($vote && isset($votes[$vote])) $votes[$vote]++;
        }
        $total = array_sum($votes);
        $suggested = null;
        if ($total) {
            $max = max($votes);
            $suggested = collect($votes)->filter(fn ($count) => $count === $max)->keys()->sortByDesc(fn ($key) => self::STRICTNESS[$key])->first();
        }
        $confirmed = $evaluation->getAttribute('viabilidad');

        return [
            'applies' => true,
            'votes' => $votes,
            'total' => $total,
            'suggested' => $suggested,
            'suggested_label' => $suggested ? self::OPTIONS[$suggested] : null,
            'confirmed' => $confirmed,
            'confirmed_label' => $confirmed ? self::OPTIONS[$confirmed] : null,
            'reason' => $evaluation->getAttribute('viabilidad_motivo'),
            'confirmed_by' => $evaluation->getAttribute('viabilidad_confirmada_por'),
            'confirmed_at' => optional($evaluation->getAttribute('viabilidad_confirmada_en') ? Carbon::parse($evaluation->getAttribute('viabilidad_confirmada_en')) : null)->toDateTimeString(),
        ];
    }

    public function confirm(Evaluation $evaluation, string $decision, ?string $reason, ?string $deadline, User $actor): array
    {
        abort_unless(self::ready(), 409, 'Falta ejecutar la migración del dictamen de viabilidad en la API.');
        abort_unless((int) $evaluation->semestre === 5, 422, 'El dictamen de viabilidad sólo aplica a la evaluación de 5°.');
        $summary = $this->summary($evaluation);
        abort_if($summary['confirmed'], 409, 'Este dictamen ya se confirmó y sus efectos se aplicaron.');
        abort_unless($summary['total'] > 0, 422, 'Todavía ningún evaluador ha votado la viabilidad.');
        $reason = trim((string) $reason) ?: null;
        abort_if($decision !== $summary['suggested'] && !$reason, 422, 'Escribe el motivo: el dictamen elegido es distinto al que votó la mayoría de los evaluadores.');
        $until = null;
        if ($decision === 'correcciones') {
            $until = $deadline ? Carbon::parse($deadline) : now()->addDays(7)->endOfDay();
            abort_unless($until->isFuture(), 422, 'El plazo para corregir debe ser posterior a hoy.');
        }

        $project = DB::table('proyectos')->where('id', $evaluation->project_id)->first();
        abort_unless($project, 404, 'El proyecto de esta evaluación ya no existe.');
        // Observaciones para el equipo: el motivo de coordinación y los comentarios generales de los evaluadores.
        $comments = DB::table('dictamenes_docentes')->where('evaluacion_id', $evaluation->id)->whereNotNull('comentario_general')
            ->where('comentario_general', '!=', '')->pluck('comentario_general')->all();
        $observations = trim(implode("\n\n", array_filter([$reason, ...$comments])));

        DB::transaction(function () use ($evaluation, $decision, $reason, $actor, $project, $until, $observations) {
            DB::table('evaluaciones')->where('id', $evaluation->id)->update([
                'viabilidad' => $decision, 'viabilidad_motivo' => $reason,
                'viabilidad_confirmada_por' => $actor->id, 'viabilidad_confirmada_en' => now(),
            ]);
            if ($decision === 'correcciones') {
                DB::table('proyectos')->where('id', $project->id)->update([
                    'estado' => 'requiere_cambios', 'comentario_revision' => $observations ?: 'Atiende las observaciones de la evaluación de viabilidad.',
                    'revision_permitida_hasta' => $until, 'revisado_por' => $actor->id, 'revisado_en' => now(), 'actualizado_en' => now(),
                ]);
            }
            if ($decision === 'no_viable') {
                DB::table('proyectos')->where('id', $project->id)->update([
                    'estado' => 'no_viable', 'activo' => false, 'comentario_revision' => $observations ?: null,
                    'revisado_por' => $actor->id, 'revisado_en' => now(), 'actualizado_en' => now(),
                ]);
                DB::table('solicitudes_union_proyecto')->where('proyecto_id', $project->id)->where('estado', 'pendiente')
                    ->update(['estado' => 'cancelada', 'respondida_en' => now()]);
            }
        });
        app(AcademicWorkflowService::class)->audit('evaluacion.viabilidad', 'proyecto', $project->id, null,
            ['evaluacion_id' => $evaluation->id, 'dictamen' => $decision, 'motivo' => $reason, 'plazo' => $until?->toDateTimeString()]);

        $students = DB::table('proyecto_integrantes')->where('proyecto_id', $project->id)->pluck('usuario_id')->map(fn ($id) => (string) $id);
        [$title, $message] = match ($decision) {
            'viable' => ['Tu proyecto es viable', "«{$project->titulo}» fue dictaminado viable: puedes continuar con su desarrollo."],
            'correcciones' => ['Tu proyecto requiere correcciones', "«{$project->titulo}» es viable con correcciones. Corrige tu propuesta antes del ".$until->format('d/m/Y H:i').'.'],
            default => ['Tu proyecto no es viable', "«{$project->titulo}» no fue dictaminado viable. Podrás registrar una nueva propuesta cuando se abra el registro para tu semestre."],
        };
        ActivityNotificationService::send($students, (string) $actor->id, 'viabilidad_proyecto', $title, $message, '/proposals');

        return $this->summary($evaluation->fresh(['attempts']));
    }
}
