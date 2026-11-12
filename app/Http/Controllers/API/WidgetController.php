<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerSetting;
use App\Models\Project;
use App\Models\User;
use App\Support\CareerContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resumen para el widget de la pantalla de inicio del teléfono: hasta cuatro filas según el perfil activo.
 * Se ve sin desbloquear el teléfono, así que sólo lleva números y títulos: ni nombres de alumnos ni calificaciones.
 */
class WidgetController extends Controller
{
    private const PROFILES = [1 => 'Administración', 2 => 'Docente', 3 => 'Estudiante', 4 => 'Administrador general', 5 => 'Jefatura de carrera', 6 => 'Asistencia de jefatura', 7 => 'Coordinación de proyectos'];

    public function __invoke()
    {
        $user = auth('api')->user();
        $profile = (int) $user->perfil_id;
        $items = match (true) {
            $profile === 3 => $this->student($user),
            $profile === 2 => $this->teacher($user),
            default => $this->management($user, $profile),
        };

        return response()->json([
            'name' => $user->nombres,
            'profile' => $user->isGeneralAdmin() ? 'Administrador general' : (self::PROFILES[$profile] ?? 'SGPI'),
            'career' => app(CareerContext::class)->career()?->nombre_corto,
            'updated_at' => now()->toIso8601String(),
            'items' => array_values(array_filter($items)),
        ]);
    }

    private function student(User $user): array
    {
        $projectIds = Project::where('activo', true)
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id))
            ->pluck('id');
        // Las mismas inscripciones que usa Entregables para el alumno.
        $courseIds = DB::table('curso_estudiantes')->where('estudiante_id', $user->id)->where('activo', true)->pluck('curso_id');

        $next = DB::table('entregables')
            ->whereIn('entregables.curso_id', $courseIds)
            ->where('entregables.activo', true)->where('entregables.estado', 'publicado')
            ->where('entregables.fecha_limite', '>=', now())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('entregas')
                ->whereColumn('entregas.entregable_id', 'entregables.id')->whereIn('entregas.proyecto_id', $projectIds))
            ->orderBy('entregables.fecha_limite')
            ->first(['entregables.nombre', 'entregables.fecha_limite']);

        $project = Project::whereIn('id', $projectIds)->orderByDesc('creado_en')->first(['id', 'titulo', 'tipo', 'estado']);
        $projectState = match (true) {
            !$project => null,
            $project->tipo !== 'propuesta' => 'Activo',
            default => ['pendiente' => 'En revisión', 'requiere_cambios' => 'Con cambios', 'aprobado' => 'Aprobada', 'rechazado' => 'Rechazada'][$project->proposal_status] ?? 'Propuesta',
        };

        $presentation = DB::table('v_evaluacion_horario as horario')
            ->join('salas_evaluacion as sala', 'sala.id', '=', 'horario.sala_id')
            ->whereIn('horario.proyecto_id', $projectIds)
            ->where('sala.estado', 'programada')
            ->where('horario.inicio_estimado', '>=', now()->subMinutes(30))
            ->orderBy('horario.inicio_estimado')
            ->first(['sala.nombre', 'sala.salon', 'horario.inicio_estimado']);

        $graded = DB::table('entregas')->join('entregables', 'entregables.id', '=', 'entregas.entregable_id')
            ->whereIn('entregas.proyecto_id', $projectIds)->whereNotNull('entregas.calificacion');
        $lastGraded = (clone $graded)->orderByDesc('entregas.entregado_en')->value('entregables.nombre');

        return [
            $this->item('deliverable', 'Próxima entrega', $next ? $this->countdown($next->fecha_limite) : '—',
                $next ? "{$next->nombre} · {$this->date($next->fecha_limite)}" : 'Sin entregas pendientes', '/deliverables'),
            $this->item('project', 'Mi proyecto', $projectState ?? '—', $project?->title ?? 'Aún no tienes proyecto', $project ? '/projects' : '/proposals'),
            $this->item('presentation', 'Mi presentación', $presentation ? $this->shortDate($presentation->inicio_estimado) : '—',
                $presentation ? trim(($presentation->salon ?: $presentation->nombre) . ' · ' . Carbon::parse($presentation->inicio_estimado)->format('H:i')) : 'Sin presentación asignada', '/evaluations'),
            $this->item('grades', 'Calificaciones', (string) (clone $graded)->count(), $lastGraded ? "Última: {$lastGraded}" : 'Aún sin calificaciones', '/deliverables'),
        ];
    }

    private function teacher(User $user): array
    {
        $toReview = DB::table('entregas')
            ->join('entregables', 'entregables.id', '=', 'entregas.entregable_id')
            ->join('curso_docentes', 'curso_docentes.curso_id', '=', 'entregables.curso_id')
            ->where('curso_docentes.docente_id', $user->id)->where('curso_docentes.activo', true)
            ->where('entregables.activo', true)
            ->whereNull('entregas.calificacion')
            ->distinct()->count('entregas.id');

        $validator = in_array((string) $user->id, array_map('strval', CareerSetting::valueFor('proposal_validator_teacher_ids', [])), true);

        $rooms = DB::table('salas_evaluacion as sala')
            ->join('sala_docentes', 'sala_docentes.sala_id', '=', 'sala.id')
            ->where('sala_docentes.docente_id', $user->id)
            ->where('sala.estado', 'programada')
            ->where('sala.termina_en', '>=', now())
            ->whereIn('sala.id', \App\Support\CareerScope::ids('salas_evaluacion', app(CareerContext::class)->careerId()))
            ->orderBy('sala.inicia_en');
        $nextRoom = (clone $rooms)->first(['sala.nombre', 'sala.salon', 'sala.inicia_en']);

        $advised = Project::where('activo', true)->whereHas('advisors', fn ($query) => $query->where('usuarios.id', $user->id))->count();

        return [
            $this->item('review', 'Entregas por revisar', (string) $toReview, $toReview ? 'Esperan tu calificación' : 'Todo al día', '/deliverables'),
            $validator ? $this->item('proposals', 'Propuestas por validar', (string) $this->pendingProposals(), 'De tu carrera', '/proposals') : null,
            $this->item('rooms', 'Próximas evaluaciones', (string) (clone $rooms)->count(),
                $nextRoom ? trim(($nextRoom->salon ?: $nextRoom->nombre) . ' · ' . $this->date($nextRoom->inicia_en)) : 'Sin salas asignadas', '/evaluations'),
            $this->item('advised', 'Proyectos asesorados', (string) $advised, $advised ? 'Como asesor o revisor' : 'Sin asesorías', '/projects'),
        ];
    }

    private function management(User $user, int $profile): array
    {
        $careerId = app(CareerContext::class)->careerId();
        $requests = in_array($profile, [1, 4, 5, 6], true) || $user->isGeneralAdmin()
            ? DB::table('autoregistros_pendientes')->where('carrera_id', $careerId)->where('estado', 'por_aprobar')->count()
            : null;
        $students = User::inCareer($careerId, [3])->where('activo', true)->count();
        $projects = Project::where('activo', true)->where(fn ($scope) => $scope->where('tipo', '!=', 'propuesta')->orWhere('estado', 'aprobado'))->count();

        $alerts = null;
        if ($user->isGeneralAdmin() && \App\Support\SchemaCache::hasTable('alertas_operativas')) {
            $active = DB::table('alertas_operativas')->whereIn('estado', ['abierta', 'atendida']);
            $alerts = ['total' => (clone $active)->count(), 'critical' => (clone $active)->where('severidad', 'critica')->count()];
        }

        return [
            $requests !== null ? $this->item('requests', 'Solicitudes de cuenta', (string) $requests, $requests ? 'Esperan aprobación' : 'Sin solicitudes', '/registration-requests') : null,
            $this->item('proposals', 'Propuestas por validar', (string) $this->pendingProposals(), 'De la carrera', '/proposals'),
            $this->item('career', 'Proyectos activos', (string) $projects, "{$students} alumnos activos", '/dashboard'),
            $alerts ? $this->item('alerts', 'Alertas operativas', (string) $alerts['total'], $alerts['critical'] ? "{$alerts['critical']} críticas" : 'Sin alertas críticas', '/operations') : null,
        ];
    }

    private function pendingProposals(): int
    {
        return Project::where('tipo', 'propuesta')->where('activo', true)->where('estado', 'pendiente')->count();
    }

    private function item(string $key, string $label, string $value, string $detail, string $path): array
    {
        return ['key' => $key, 'label' => $label, 'value' => mb_substr($value, 0, 12), 'detail' => mb_substr($detail, 0, 70), 'path' => $path];
    }

    private function countdown($date): string
    {
        $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay());
        return match (true) {
            $days <= 0 => 'Hoy',
            $days === 1 => 'Mañana',
            default => "{$days} días",
        };
    }

    private function date($date): string
    {
        return Carbon::parse($date)->locale('es')->isoFormat('D MMM, HH:mm');
    }

    private function shortDate($date): string
    {
        return Carbon::parse($date)->locale('es')->isoFormat('D MMM');
    }
}
