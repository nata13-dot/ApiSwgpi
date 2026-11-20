<?php

namespace App\Services;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seguimiento de tesis por capítulos. Cada docente activo del curso califica de 0 a 100 cada capítulo de la
 * última versión entregada; el capítulo vale el promedio y se aprueba con 70 cuando ya lo revisaron todos.
 * El alumno sólo ve el puntaje general y los comentarios.
 */
class ThesisTrackingService
{
    public const PASSING = 70;

    public function ready(): bool
    {
        return SchemaCache::hasTable('tesis_cursos');
    }

    public function reviewers(int $courseId): Collection
    {
        return DB::table('tesis_revisores as r')->join('usuarios as u', 'u.id', '=', 'r.docente_id')
            ->where('r.curso_id', $courseId)->where('r.activo', true)
            ->orderByRaw("FIELD(r.rol, 'director', 'codirector', 'revisor', 'invitado')")->orderBy('u.apellido_paterno')
            ->get(['u.id', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno', 'r.rol'])
            ->map(fn ($u) => (object) ['id' => (string) $u->id, 'rol' => $u->rol, 'nombre' => trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}")]);
    }

    public function isReviewer(int $courseId, string $userId): bool
    {
        return DB::table('tesis_revisores')->where('curso_id', $courseId)->where('docente_id', $userId)->where('activo', true)->exists();
    }

    /**
     * Estado de cada capítulo según su última versión: sin_entregar, en_revision, aprobado o por_corregir.
     * @return Collection<int, array>
     */
    public function chapters(int $courseId, ?Collection $reviewers = null): Collection
    {
        $reviewers ??= $this->reviewers($courseId);
        $active = $reviewers->pluck('id')->all();
        $chapters = DB::table('tesis_capitulos')->where('curso_id', $courseId)->where('activo', true)->orderBy('numero')->get();
        $latest = DB::table('tesis_envio_capitulos as ec')->join('tesis_envios as e', 'e.id', '=', 'ec.envio_id')
            ->where('e.curso_id', $courseId)->groupBy('ec.capitulo_id')->selectRaw('ec.capitulo_id, MAX(e.id) as envio_id')->pluck('envio_id', 'capitulo_id');
        $reviews = $latest->isEmpty() ? collect() : DB::table('tesis_revisiones')->whereIn('envio_id', $latest->values())->get()
            ->filter(fn ($r) => (int) ($latest[$r->capitulo_id] ?? 0) === (int) $r->envio_id)->groupBy('capitulo_id');

        return $chapters->map(function ($chapter) use ($latest, $reviews, $active) {
            $envio = $latest[$chapter->id] ?? null;
            $items = ($reviews->get($chapter->id) ?? collect())->filter(fn ($r) => in_array((string) $r->docente_id, $active, true))->values();
            $average = $items->isNotEmpty() ? round((float) $items->avg('puntaje'), 2) : null;
            $complete = $envio && $active && $items->count() >= count($active);
            $state = !$envio ? 'sin_entregar' : (!$complete ? 'en_revision' : ($average >= self::PASSING ? 'aprobado' : 'por_corregir'));

            return [
                'id' => (int) $chapter->id, 'numero' => (int) $chapter->numero, 'titulo' => $chapter->titulo,
                'estado' => $state, 'promedio' => $average, 'envio_id' => $envio ? (int) $envio : null,
                'revisados' => $items->count(), 'revisores' => count($active),
                'revisiones' => $items->map(fn ($r) => ['docente_id' => (string) $r->docente_id, 'puntaje' => (float) $r->puntaje, 'comentario' => $r->comentario])->values()->all(),
            ];
        })->values();
    }

    /** Puntaje general: promedio de los capítulos que ya tienen alguna revisión. */
    public static function general(Collection $chapters): ?float
    {
        $scored = $chapters->pluck('promedio')->filter(fn ($value) => $value !== null);

        return $scored->isNotEmpty() ? round((float) $scored->avg(), 2) : null;
    }

    public static function allApproved(Collection $chapters): bool
    {
        return $chapters->isNotEmpty() && $chapters->every(fn ($chapter) => $chapter['estado'] === 'aprobado');
    }
}
