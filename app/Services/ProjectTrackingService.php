<?php

namespace App\Services;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seguimiento de avances por materia. La carrera elige qué materias piden avances y a qué modalidades
 * aplican (materias_seguimiento). Cada curso de esas materias en el periodo abierto queda ligado solo a los
 * proyectos de su grupo con esa modalidad; el docente o la jefatura agregan o quitan proyectos concretos
 * (curso_proyectos). El vínculo se refleja en curso_estudiantes con origen "seguimiento", que es lo que ya
 * usan entregas, permisos y recordatorios; las inscripciones por contraseña no se tocan.
 */
class ProjectTrackingService
{
    public const MODALITIES = ['proyecto_integrador' => 'aplica_integrador', 'caso_integrador' => 'aplica_caso', 'dual' => 'aplica_dual'];

    private const EXCLUDED_STATES = ['rechazado', 'no_viable', 'archivado'];

    /** Tipos de archivo que el docente puede exigir en un avance (clave => extensiones). */
    public const FILE_TYPES = [
        'pdf' => ['pdf'], 'word' => ['doc', 'docx'], 'excel' => ['xls', 'xlsx'], 'powerpoint' => ['ppt', 'pptx'],
        'imagen' => ['jpg', 'jpeg', 'png', 'webp'], 'comprimido' => ['zip', 'rar', '7z'], 'texto' => ['txt'],
    ];

    /** "pdf,word" → ['pdf', 'word'] (sólo claves conocidas). */
    public static function fileTypes(?string $stored): array
    {
        return array_values(array_intersect(array_filter(array_map('trim', explode(',', (string) $stored))), array_keys(self::FILE_TYPES)));
    }

    public static function storeFileTypes(?array $types): ?string
    {
        $types = array_values(array_intersect($types ?? [], array_keys(self::FILE_TYPES)));

        return $types ? implode(',', $types) : null;
    }

    /** Extensiones aceptadas por un avance, dentro de las que permite el sistema. Null = las del sistema. */
    public static function extensionsFor(?string $stored, array $systemAllowed): ?array
    {
        $types = self::fileTypes($stored);
        if (!$types) return null;
        $extensions = array_merge(...array_map(fn ($type) => self::FILE_TYPES[$type], $types));
        $allowed = array_values(array_intersect($extensions, array_map('strtolower', $systemAllowed)));

        return $allowed ?: $extensions;
    }

    /** Competencias activas de una materia, en orden. */
    public function competencies(int $subjectId)
    {
        return SchemaCache::hasTable('competencias_materia')
            ? DB::table('competencias_materia')->where('asignatura_id', $subjectId)->where('activo', true)->orderBy('orden')->orderBy('id')->get(['id', 'orden', 'nombre', 'descripcion'])
            : collect();
    }

    public function ready(): bool
    {
        return SchemaCache::hasTable('materias_seguimiento') && SchemaCache::hasColumn('curso_estudiantes', 'origen');
    }

    public function openPeriodId(int $careerId): ?int
    {
        $id = DB::table('carrera_periodos')->where('carrera_id', $careerId)->whereIn('estado', ['abierto', 'en_cierre'])->value('periodo_id');

        return $id ? (int) $id : null;
    }

    /** @return Collection<int, object> materia de seguimiento por asignatura_id */
    public function subjects(int $careerId): Collection
    {
        return DB::table('materias_seguimiento')->where('carrera_id', $careerId)->where('activo', true)->get()->keyBy('asignatura_id');
    }

    /** Modalidades a las que aplica una materia de seguimiento. */
    public function modalitiesOf(object $subject): array
    {
        return array_keys(array_filter(self::MODALITIES, fn ($column) => (bool) $subject->{$column}));
    }

    /** Cursos de seguimiento del periodo abierto (opcionalmente sólo los que imparte un docente). */
    public function courses(int $careerId, ?string $teacherId = null): Collection
    {
        $period = $this->openPeriodId($careerId);
        if (!$period || !$this->ready()) return collect();

        return DB::table('cursos as c')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
            ->join('asignaturas as a', 'a.id', '=', 'c.asignatura_id')
            ->join('materias_seguimiento as m', fn ($join) => $join->on('m.asignatura_id', '=', 'c.asignatura_id')->where('m.carrera_id', $careerId)->where('m.activo', true))
            ->where('g.carrera_id', $careerId)->where('g.periodo_id', $period)->where('g.activo', true)->where('c.activo', true)
            ->when($teacherId, fn ($query) => $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('curso_docentes as cd')
                ->whereColumn('cd.curso_id', 'c.id')->where('cd.docente_id', $teacherId)->where('cd.activo', true)))
            ->orderBy('g.semestre')->orderBy('g.clave_grupo')->orderBy('a.nombre')
            ->get(['c.id', 'c.grupo_id', 'c.asignatura_id', 'g.periodo_id', 'g.semestre', 'g.clave_grupo', 'g.nombre as grupo_nombre',
                'a.nombre as asignatura', 'a.clave as asignatura_clave', 'm.id as materia_id', 'm.aplica_integrador', 'm.aplica_caso', 'm.aplica_dual']);
    }

    public function course(int $careerId, int $courseId): ?object
    {
        return DB::table('cursos as c')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')->join('asignaturas as a', 'a.id', '=', 'c.asignatura_id')
            ->leftJoin('materias_seguimiento as m', fn ($join) => $join->on('m.asignatura_id', '=', 'c.asignatura_id')->where('m.carrera_id', $careerId))
            ->where('c.id', $courseId)->where('g.carrera_id', $careerId)
            ->first(['c.id', 'c.grupo_id', 'c.asignatura_id', 'c.activo', 'g.periodo_id', 'g.semestre', 'g.clave_grupo', 'g.nombre as grupo_nombre',
                'a.nombre as asignatura', 'a.clave as asignatura_clave', 'm.id as materia_id', 'm.activo as materia_activa',
                'm.aplica_integrador', 'm.aplica_caso', 'm.aplica_dual']);
    }

    /**
     * Proyectos del curso: [proyecto_id => 'automatico'|'manual']. Los quitados a mano no aparecen.
     * Un proyecto pertenece al grupo de su periodo (proyecto_periodos) o, sin registro del periodo, a su grupo actual.
     */
    public function linkedProjects(object $course): array
    {
        $manual = DB::table('curso_proyectos')->where('curso_id', $course->id)->pluck('tipo', 'proyecto_id')->all();
        $linked = [];
        if ($course->materia_id && ($course->materia_activa ?? true)) {
            $modalities = $this->modalitiesOf($course);
            if ($modalities) {
                $ids = DB::table('proyectos as p')->where('p.activo', true)->whereNotIn('p.estado', self::EXCLUDED_STATES)
                    ->when(SchemaCache::hasColumn('proyectos', 'es_residencia'), fn ($query) => $query->where('p.es_residencia', false))
                    ->whereIn('p.modalidad', $modalities)
                    ->where(function ($query) use ($course) {
                        $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('proyecto_periodos as pp')->whereColumn('pp.proyecto_id', 'p.id')->where('pp.grupo_id', $course->grupo_id))
                            ->orWhere(fn ($own) => $own->where('p.grupo_id', $course->grupo_id)
                                ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('proyecto_periodos as pp')->join('grupos_academicos as pg', 'pg.id', '=', 'pp.grupo_id')
                                    ->whereColumn('pp.proyecto_id', 'p.id')->where('pg.periodo_id', $course->periodo_id)->where('pp.grupo_id', '!=', $course->grupo_id)));
                    })->pluck('p.id');
                foreach ($ids as $id) $linked[(int) $id] = 'automatico';
            }
        }
        foreach ($manual as $project => $type) {
            if ($type === 'baja') unset($linked[(int) $project]);
            elseif (DB::table('proyectos')->where('id', $project)->where('activo', true)->exists()) $linked[(int) $project] = 'manual';
        }

        return $linked;
    }

    /** Refleja el vínculo en curso_estudiantes. Devuelve [altas, bajas]. */
    public function syncCourse(object $course): array
    {
        if (!$this->ready()) return [0, 0];
        $projects = array_keys($this->linkedProjects($course));
        $targets = $projects ? DB::table('proyecto_integrantes')->whereIn('proyecto_id', $projects)->whereIn('rol', ['lider', 'integrante'])
            ->pluck('usuario_id')->map(fn ($id) => (string) $id)->unique()->values()->all() : [];
        $rows = DB::table('curso_estudiantes')->where('curso_id', $course->id)->get(['estudiante_id', 'activo', 'origen']);
        $added = 0;
        $removed = 0;
        DB::transaction(function () use ($course, $targets, $rows, &$added, &$removed) {
            foreach ($targets as $student) {
                $row = $rows->first(fn ($candidate) => (string) $candidate->estudiante_id === $student);
                if (!$row) {
                    DB::table('curso_estudiantes')->insert(['curso_id' => $course->id, 'estudiante_id' => $student, 'inscrito_en' => now(), 'activo' => true, 'origen' => 'seguimiento']);
                    $added++;
                } elseif (!$row->activo) {
                    DB::table('curso_estudiantes')->where('curso_id', $course->id)->where('estudiante_id', $student)->update(['activo' => true, 'origen' => 'seguimiento']);
                    $added++;
                }
            }
            // Las claves numéricas de PHP se vuelven enteros: se comparan y envían como texto (la columna es varchar).
            $stale = $rows->filter(fn ($row) => $row->activo && $row->origen === 'seguimiento' && !in_array((string) $row->estudiante_id, $targets, true))
                ->map(fn ($row) => (string) $row->estudiante_id)->values()->all();
            if ($stale) {
                $removed = DB::table('curso_estudiantes')->where('curso_id', $course->id)->whereIn('estudiante_id', $stale)->update(['activo' => false]);
            }
            DB::table('cursos')->where('id', $course->id)->where('es_seguimiento_proyecto', false)->update(['es_seguimiento_proyecto' => true]);
        });

        return [$added, $removed];
    }

    public function syncCareer(int $careerId): array
    {
        $totals = [0, 0];
        foreach ($this->courses($careerId) as $course) {
            [$added, $removed] = $this->syncCourse($course);
            $totals[0] += $added;
            $totals[1] += $removed;
        }

        return $totals;
    }

    /** Antes de mostrarle sus avances a un alumno, sincroniza sólo los cursos de los grupos de sus proyectos. */
    public function syncForStudent(int $careerId, string $studentId): void
    {
        if (!$this->ready()) return;
        $projects = DB::table('proyecto_integrantes')->where('usuario_id', $studentId)->whereIn('rol', ['lider', 'integrante'])->pluck('proyecto_id');
        if ($projects->isEmpty()) return;
        $groups = DB::table('proyecto_periodos')->whereIn('proyecto_id', $projects)->pluck('grupo_id')
            ->merge(DB::table('proyectos')->whereIn('id', $projects)->pluck('grupo_id'))
            ->merge(DB::table('curso_proyectos as cp')->join('cursos as c', 'c.id', '=', 'cp.curso_id')->whereIn('cp.proyecto_id', $projects)->pluck('c.grupo_id'))
            ->unique()->all();
        foreach ($this->courses($careerId)->whereIn('grupo_id', $groups) as $course) $this->syncCourse($course);
    }

    public function teaches(string $userId, int $courseId): bool
    {
        return DB::table('curso_docentes')->where('curso_id', $courseId)->where('docente_id', $userId)->where('activo', true)->exists();
    }

    /** Fecha límite del equipo: su prórroga si la tiene, si no la del avance. */
    public function deadline(object $deliverable, int $projectId): ?Carbon
    {
        $extension = SchemaCache::hasTable('entrega_prorrogas')
            ? DB::table('entrega_prorrogas')->where('entregable_id', $deliverable->id)->where('proyecto_id', $projectId)->value('vence_en')
            : null;
        $date = $extension ?: $deliverable->fecha_limite;

        return $date ? Carbon::parse($date) : null;
    }

    /** Promedio ponderado: con ponderaciones usa sólo las de avances calificados; sin ellas, promedio simple. */
    public static function average(array $items): ?float
    {
        $graded = array_values(array_filter($items, fn ($item) => $item['calificacion'] !== null));
        if (!$graded) return null;
        $weights = array_sum(array_map(fn ($item) => (float) ($item['ponderacion'] ?? 0), $graded));
        if ($weights > 0) {
            return round(array_sum(array_map(fn ($item) => (float) $item['calificacion'] * (float) ($item['ponderacion'] ?? 0), $graded)) / $weights, 2);
        }

        return round(array_sum(array_column($graded, 'calificacion')) / count($graded), 2);
    }
}
