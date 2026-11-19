<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AcademicWorkflowService;
use App\Services\ActivityNotificationService;
use App\Services\ProjectTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Seguimiento de avances: la carrera configura materias y plantillas; el docente de cada materia abre los
 * avances, ve el tablero de sus equipos, califica, ajusta a un integrante, da prórrogas y agrega o quita
 * proyectos; la jefatura ve el panorama de toda la carrera.
 */
class ProjectTrackingController extends Controller
{
    public function __construct(private ProjectTrackingService $tracking, private AcademicWorkflowService $workflow) {}

    private function career(): int
    {
        abort_unless($this->tracking->ready(), 503, 'Falta instalar la actualización de seguimiento de avances.');

        return $this->workflow->careerId();
    }

    private function isStaff(): bool
    {
        $user = auth('api')->user();

        return $user->isGeneralAdmin() || in_array((int) app(\App\Support\CareerContext::class)->profileId(), [1, 5, 6, 7], true);
    }

    private function requireStaff(): void
    {
        abort_unless($this->isStaff(), 403, 'Se requiere jefatura, asistencia o coordinación de proyectos.');
    }

    private function courseFor(int $courseId): object
    {
        $course = $this->tracking->course($this->career(), $courseId);
        abort_unless($course, 404, 'Materia no encontrada.');
        abort_unless($this->isStaff() || $this->tracking->teaches((string) auth('api')->id(), $course->id), 403, 'No impartes esta materia.');

        return $course;
    }

    // ---------------------------------------------------------------- Docente

    /** Materias de seguimiento del periodo abierto: las que imparto, o todas para la jefatura (?all=1). */
    public function courses(Request $request)
    {
        $career = $this->career();
        $all = $request->boolean('all') && $this->isStaff();
        $courses = $this->tracking->courses($career, $all ? null : (string) auth('api')->id());
        foreach ($courses as $course) $this->tracking->syncCourse($course);

        return response()->json([
            'data' => $courses->map(fn ($course) => $this->shapeCourse($course) + ['resumen' => $this->stats($course)])->values(),
            'staff' => $this->isStaff(),
            'configured' => $this->tracking->subjects($career)->isNotEmpty(),
            'period_open' => (bool) $this->tracking->openPeriodId($career),
        ]);
    }

    public function show(int $course)
    {
        $course = $this->courseFor($course);
        $this->tracking->syncCourse($course);
        $linked = $this->tracking->linkedProjects($course);
        $deliverables = DB::table('entregables')->where('curso_id', $course->id)->where('activo', true)->orderByRaw('fecha_limite IS NULL')->orderBy('fecha_limite')->orderBy('id')
            ->get(['id', 'nombre', 'descripcion', 'tipo_documento', 'estado', 'fecha_limite', 'ponderacion', 'plantilla_id']);
        $ids = $deliverables->pluck('id');
        $deliveries = DB::table('entregas as e')->leftJoin('documento_versiones as v', function ($join) {
            $join->on('v.documento_id', '=', 'e.documento_id')->whereRaw('v.numero_version = (SELECT MAX(x.numero_version) FROM documento_versiones x WHERE x.documento_id = e.documento_id)');
        })->whereIn('e.entregable_id', $ids)->whereIn('e.proyecto_id', array_keys($linked))
            ->get(['e.id', 'e.entregable_id', 'e.proyecto_id', 'e.entregado_en', 'e.calificacion', 'e.comentarios_docente', 'v.numero_version', 'v.nombre_archivo'])
            ->keyBy(fn ($row) => "{$row->entregable_id}:{$row->proyecto_id}");
        $extensions = DB::table('entrega_prorrogas')->whereIn('entregable_id', $ids)->get()->keyBy(fn ($row) => "{$row->entregable_id}:{$row->proyecto_id}");
        $adjustments = DB::table('entrega_ajustes')->whereIn('entrega_id', $deliveries->pluck('id'))->get()->groupBy('entrega_id');
        $projects = DB::table('proyectos')->whereIn('id', array_keys($linked))->orderBy('titulo')->get(['id', 'titulo', 'modalidad']);
        $members = DB::table('proyecto_integrantes as i')->join('usuarios as u', 'u.id', '=', 'i.usuario_id')->whereIn('i.proyecto_id', array_keys($linked))
            ->whereIn('i.rol', ['lider', 'integrante'])->orderBy('u.apellido_paterno')
            ->get(['i.proyecto_id', 'u.id', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno'])->groupBy('proyecto_id');

        $rows = $projects->map(function ($project) use ($deliverables, $deliveries, $extensions, $adjustments, $members, $linked) {
            $cells = $deliverables->map(function ($deliverable) use ($project, $deliveries, $extensions, $adjustments) {
                $key = "{$deliverable->id}:{$project->id}";
                $delivery = $deliveries->get($key);
                $extension = $extensions->get($key);
                $due = $extension?->vence_en ?? $deliverable->fecha_limite;

                return [
                    'deliverable_id' => $deliverable->id,
                    'delivery_id' => $delivery?->id,
                    'estado' => $this->cellState($deliverable, $delivery, $due),
                    'calificacion' => $delivery?->calificacion !== null ? (float) $delivery->calificacion : null,
                    'comentario' => $delivery?->comentarios_docente,
                    'entregado_en' => $delivery?->entregado_en ? Carbon::parse($delivery->entregado_en)->toIso8601String() : null,
                    'version' => $delivery?->numero_version ? (int) $delivery->numero_version : null,
                    'archivo' => $delivery?->nombre_archivo,
                    'vence_en' => $due ? Carbon::parse($due)->toIso8601String() : null,
                    'prorroga' => $extension ? ['vence_en' => Carbon::parse($extension->vence_en)->toIso8601String(), 'motivo' => $extension->motivo] : null,
                    'ajustes' => $delivery ? ($adjustments->get($delivery->id) ?? collect())->map(fn ($row) => [
                        'estudiante_id' => $row->estudiante_id, 'calificacion' => (float) $row->calificacion, 'motivo' => $row->motivo,
                    ])->values() : [],
                    'ponderacion' => $deliverable->ponderacion !== null ? (float) $deliverable->ponderacion : null,
                ];
            })->values();

            return [
                'id' => $project->id,
                'titulo' => $project->titulo,
                'modalidad' => $project->modalidad,
                'origen' => $linked[$project->id],
                'integrantes' => ($members->get($project->id) ?? collect())->map(fn ($m) => [
                    'id' => $m->id, 'nombre' => trim("{$m->nombres} {$m->apellido_paterno} {$m->apellido_materno}"),
                ])->values(),
                'promedio' => ProjectTrackingService::average($cells->where('estado', '!=', 'borrador')->values()->all()),
                'celdas' => $cells,
            ];
        })->values();

        $removed = DB::table('curso_proyectos as cp')->join('proyectos as p', 'p.id', '=', 'cp.proyecto_id')
            ->where('cp.curso_id', $course->id)->where('cp.tipo', 'baja')->get(['p.id', 'p.titulo', 'cp.motivo']);

        return response()->json(['data' => [
            'course' => $this->shapeCourse($course),
            'deliverables' => $deliverables->map(fn ($d) => [
                'id' => $d->id, 'nombre' => $d->nombre, 'descripcion' => $d->descripcion, 'tipo_documento' => $d->tipo_documento,
                'estado' => $d->estado, 'fecha_limite' => $d->fecha_limite ? Carbon::parse($d->fecha_limite)->toIso8601String() : null,
                'ponderacion' => $d->ponderacion !== null ? (float) $d->ponderacion : null, 'plantilla_id' => $d->plantilla_id,
            ])->values(),
            'template_pending' => $this->pendingTemplate($course, $deliverables)->values(),
            'projects' => $rows,
            'removed' => $removed,
            'resumen' => $this->stats($course),
        ]]);
    }

    /** Crea en borrador los avances de la plantilla que aún no tiene el curso; la fecha sale de la semana sugerida. */
    public function applyTemplate(int $course)
    {
        $course = $this->courseFor($course);
        $this->workflow->requireOpen((int) $course->periodo_id);
        $existing = DB::table('entregables')->where('curso_id', $course->id)->where('activo', true)->get(['plantilla_id']);
        $pending = $this->pendingTemplate($course, $existing);
        abort_if($pending->isEmpty(), 422, 'La materia no tiene avances de plantilla pendientes.');
        $start = DB::table('periodos_academicos')->where('id', $course->periodo_id)->value('fecha_inicio');
        DB::transaction(function () use ($pending, $course, $start) {
            foreach ($pending as $item) {
                $due = $start && $item->semana ? Carbon::parse($start)->startOfDay()->addWeeks((int) $item->semana)->setTime(23, 59) : null;
                DB::table('entregables')->insert([
                    'curso_id' => $course->id, 'nombre' => $item->nombre, 'descripcion' => $item->descripcion, 'tipo_documento' => $item->tipo_documento,
                    'fecha_limite' => $due && $due->isFuture() ? $due : null, 'ponderacion' => $item->ponderacion, 'plantilla_id' => $item->id,
                    'estado' => 'borrador', 'activo' => true, 'creado_en' => now(),
                ]);
            }
            $this->workflow->audit('seguimiento.plantilla_aplicada', 'curso', $course->id, null, ['avances' => $pending->pluck('id')]);
        });

        return response()->json(['message' => "Se agregaron {$pending->count()} avance(s) en borrador. Revisa las fechas y publícalos."]);
    }

    public function candidates(int $course)
    {
        $course = $this->courseFor($course);
        $linked = array_keys($this->tracking->linkedProjects($course));
        $groups = DB::table('grupos_academicos')->where('carrera_id', $this->career())->where('periodo_id', $course->periodo_id)->pluck('id');
        $projects = DB::table('proyectos as p')->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')
            ->where('p.activo', true)->whereNotIn('p.estado', ['rechazado', 'no_viable', 'archivado'])->whereNotIn('p.id', $linked)
            ->where(fn ($query) => $query->whereIn('p.grupo_id', $groups)->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('proyecto_periodos as pp')
                ->whereColumn('pp.proyecto_id', 'p.id')->whereIn('pp.grupo_id', $groups)))
            ->orderBy('p.titulo')->limit(300)->get(['p.id', 'p.titulo', 'p.modalidad', 'g.semestre', 'g.clave_grupo']);

        return response()->json(['data' => $projects]);
    }

    public function link(Request $request, int $course)
    {
        $course = $this->courseFor($course);
        $data = $request->validate(['project_id' => 'required|integer|exists:proyectos,id', 'motivo' => 'required|string|min:5|max:500']);
        $this->workflow->requireOpen((int) $course->periodo_id);
        abort_unless(DB::table('proyectos as p')->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')->where('p.id', $data['project_id'])
            ->where('g.carrera_id', $this->career())->where('p.activo', true)->exists(), 422, 'El proyecto no pertenece a la carrera.');
        $key = ['curso_id' => $course->id, 'proyecto_id' => $data['project_id']];
        $before = DB::table('curso_proyectos')->where($key)->first();
        // Si se había quitado a mano, volver a agregarlo sólo deshace esa baja.
        if ($before?->tipo === 'baja') DB::table('curso_proyectos')->where($key)->delete();
        else DB::table('curso_proyectos')->updateOrInsert($key, ['tipo' => 'alta', 'motivo' => $data['motivo'], 'registrado_por' => auth('api')->id(), 'creado_en' => now()]);
        $this->workflow->audit('seguimiento.proyecto_agregado', 'curso', $course->id, $before, $data);
        $this->tracking->syncCourse($course);

        return response()->json(['message' => 'Proyecto agregado a la materia.']);
    }

    public function unlink(Request $request, int $course, int $project)
    {
        $course = $this->courseFor($course);
        $data = $request->validate(['motivo' => 'required|string|min:5|max:500']);
        $this->workflow->requireOpen((int) $course->periodo_id);
        $key = ['curso_id' => $course->id, 'proyecto_id' => $project];
        $before = DB::table('curso_proyectos')->where($key)->first();
        abort_unless($before || array_key_exists($project, $this->tracking->linkedProjects($course)), 404, 'El proyecto no está en esta materia.');
        if ($before?->tipo === 'alta') DB::table('curso_proyectos')->where($key)->delete();
        else DB::table('curso_proyectos')->updateOrInsert($key, ['tipo' => 'baja', 'motivo' => $data['motivo'], 'registrado_por' => auth('api')->id(), 'creado_en' => now()]);
        $this->workflow->audit('seguimiento.proyecto_quitado', 'curso', $course->id, $before, $data + ['project_id' => $project]);
        $this->tracking->syncCourse($course);

        return response()->json(['message' => 'Proyecto quitado de la materia; sus entregas se conservan.']);
    }

    public function extend(Request $request, int $deliverable, int $project)
    {
        [$item, $course] = $this->deliverableFor($deliverable);
        $data = $request->validate(['vence_en' => 'required|date|after:now', 'motivo' => 'required|string|min:5|max:500']);
        abort_unless(array_key_exists($project, $this->tracking->linkedProjects($course)), 422, 'El proyecto no está en esta materia.');
        $key = ['entregable_id' => $item->id, 'proyecto_id' => $project];
        $before = DB::table('entrega_prorrogas')->where($key)->first();
        DB::table('entrega_prorrogas')->updateOrInsert($key, ['vence_en' => Carbon::parse($data['vence_en']), 'motivo' => $data['motivo'], 'otorgada_por' => auth('api')->id(), 'creado_en' => now()]);
        $this->workflow->audit('seguimiento.prorroga', 'entregable', $item->id, $before, $data + ['project_id' => $project]);
        $when = Carbon::parse($data['vence_en'])->format('d/m/Y H:i');
        ActivityNotificationService::send($this->projectStudents($project), (string) auth('api')->id(), 'actividad_habilitada', 'Prórroga concedida',
            "Tu equipo tiene hasta el {$when} para entregar «{$item->nombre}».", '/deliverables');

        return response()->json(['message' => 'Prórroga registrada para el equipo.']);
    }

    public function removeExtension(int $deliverable, int $project)
    {
        [$item] = $this->deliverableFor($deliverable);
        $key = ['entregable_id' => $item->id, 'proyecto_id' => $project];
        $before = DB::table('entrega_prorrogas')->where($key)->first();
        abort_unless($before, 404, 'El equipo no tiene prórroga en este avance.');
        DB::table('entrega_prorrogas')->where($key)->delete();
        $this->workflow->audit('seguimiento.prorroga_retirada', 'entregable', $item->id, $before, null);

        return response()->json(['message' => 'Prórroga retirada; vuelve a aplicar la fecha general.']);
    }

    public function adjust(Request $request, int $delivery, string $student)
    {
        [$row] = $this->deliveryFor($delivery);
        $data = $request->validate(['calificacion' => 'required|numeric|min:0|max:100', 'motivo' => 'required|string|min:5|max:500']);
        abort_if($row->calificacion === null, 422, 'Primero califica al equipo.');
        abort_unless(DB::table('proyecto_integrantes')->where('proyecto_id', $row->proyecto_id)->where('usuario_id', $student)->whereIn('rol', ['lider', 'integrante'])->exists(),
            422, 'El alumno no es integrante del equipo.');
        $key = ['entrega_id' => $row->id, 'estudiante_id' => $student];
        $before = DB::table('entrega_ajustes')->where($key)->first();
        DB::table('entrega_ajustes')->updateOrInsert($key, ['calificacion' => $data['calificacion'], 'motivo' => $data['motivo'], 'ajustado_por' => auth('api')->id(), 'creado_en' => now()]);
        $this->workflow->audit('seguimiento.ajuste', 'entrega', $row->id, $before, $data + ['student_id' => $student]);
        ActivityNotificationService::send([$student], (string) auth('api')->id(), 'entrega_calificada', 'Calificación actualizada',
            "Tu calificación de «{$row->nombre}» cambió. Revísala en SGPI.", '/deliverables');

        return response()->json(['message' => 'Calificación individual registrada.']);
    }

    public function removeAdjustment(int $delivery, string $student)
    {
        [$row] = $this->deliveryFor($delivery);
        $key = ['entrega_id' => $row->id, 'estudiante_id' => $student];
        $before = DB::table('entrega_ajustes')->where($key)->first();
        abort_unless($before, 404, 'El alumno no tiene ajuste.');
        DB::table('entrega_ajustes')->where($key)->delete();
        $this->workflow->audit('seguimiento.ajuste_retirado', 'entrega', $row->id, $before, null);

        return response()->json(['message' => 'El alumno vuelve a tener la calificación del equipo.']);
    }

    // ---------------------------------------------------------------- Jefatura

    public function overview()
    {
        $this->requireStaff();
        $career = $this->career();
        $courses = $this->tracking->courses($career);
        $rows = $courses->map(function ($course) {
            $this->tracking->syncCourse($course);

            return $this->shapeCourse($course) + ['resumen' => $this->stats($course)];
        })->values();
        $byModality = [];
        foreach ($courses as $course) {
            foreach (DB::table('proyectos')->whereIn('id', array_keys($this->tracking->linkedProjects($course)))->pluck('modalidad') as $modality) {
                $byModality[$modality] = ($byModality[$modality] ?? 0) + 1;
            }
        }

        return response()->json(['data' => [
            'courses' => $rows,
            'totals' => [
                'materias' => $rows->count(),
                'sin_docente' => $rows->filter(fn ($row) => empty($row['docentes']))->count(),
                'esperadas' => $rows->sum('resumen.esperadas'),
                'entregadas' => $rows->sum('resumen.entregadas'),
                'vencidas' => $rows->sum('resumen.vencidas'),
                'sin_calificar' => $rows->sum('resumen.sin_calificar'),
                'atrasadas' => $rows->sum('resumen.atrasadas'),
                'por_modalidad' => $byModality,
            ],
            'period_open' => (bool) $this->tracking->openPeriodId($career),
        ]]);
    }

    public function config()
    {
        $this->requireStaff();
        $career = $this->career();
        $subjects = DB::table('materias_seguimiento as m')->join('asignaturas as a', 'a.id', '=', 'm.asignatura_id')->where('m.carrera_id', $career)
            ->orderByDesc('m.activo')->orderBy('a.nombre')->get(['m.*', 'a.nombre as asignatura', 'a.clave as asignatura_clave']);
        $templates = DB::table('plantillas_avance')->whereIn('materia_seguimiento_id', $subjects->pluck('id'))->orderBy('orden')->get()->groupBy('materia_seguimiento_id');
        $semesters = DB::table('cursos as c')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')->where('g.carrera_id', $career)->where('c.activo', true)
            ->whereNotNull('g.semestre')->select('c.asignatura_id', 'g.semestre')->distinct()->get()->groupBy('asignatura_id')
            ->map(fn ($rows) => $rows->pluck('semestre')->map(fn ($s) => (int) $s)->unique()->sort()->values());

        return response()->json(['data' => [
            'subjects' => $subjects->map(fn ($m) => [
                'id' => $m->id, 'asignatura_id' => $m->asignatura_id, 'asignatura' => $m->asignatura, 'clave' => $m->asignatura_clave,
                'modalidades' => $this->tracking->modalitiesOf($m), 'activo' => (bool) $m->activo,
                'semestres' => $semesters->get($m->asignatura_id, collect())->values(),
                'plantilla' => ($templates->get($m->id) ?? collect())->map(fn ($t) => [
                    'id' => $t->id, 'nombre' => $t->nombre, 'descripcion' => $t->descripcion, 'tipo_documento' => $t->tipo_documento,
                    'semana' => $t->semana, 'ponderacion' => $t->ponderacion !== null ? (float) $t->ponderacion : null,
                ])->values(),
            ])->values(),
            'catalog' => DB::table('asignaturas')->where('carrera_id', $career)->where('activo', true)->whereNotIn('id', $subjects->pluck('asignatura_id'))
                ->orderBy('nombre')->get(['id', 'nombre', 'clave'])->map(fn ($a) => [
                    'id' => $a->id, 'nombre' => $a->nombre, 'clave' => $a->clave, 'semestres' => $semesters->get($a->id, collect())->values(),
                ]),
        ]]);
    }

    public function storeSubject(Request $request)
    {
        $this->requireStaff();
        $career = $this->career();
        $data = $request->validate([
            'asignatura_id' => ['required', 'integer', Rule::exists('asignaturas', 'id')->where('carrera_id', $career)],
            'modalidades' => 'required|array|min:1', 'modalidades.*' => Rule::in(array_keys(ProjectTrackingService::MODALITIES)),
        ]);
        $values = $this->modalityColumns($data['modalidades']) + ['activo' => true, 'actualizado_por' => auth('api')->id(), 'actualizado_en' => now()];
        DB::table('materias_seguimiento')->updateOrInsert(['carrera_id' => $career, 'asignatura_id' => $data['asignatura_id']], $values);
        $this->workflow->audit('seguimiento.materia', 'asignatura', $data['asignatura_id'], null, $data);
        $this->tracking->syncCareer($career);

        return response()->json(['message' => 'Materia agregada al seguimiento de avances.'], 201);
    }

    public function updateSubject(Request $request, int $id)
    {
        $this->requireStaff();
        $career = $this->career();
        $subject = DB::table('materias_seguimiento')->where('id', $id)->where('carrera_id', $career)->first();
        abort_unless($subject, 404);
        $data = $request->validate([
            'modalidades' => 'sometimes|array|min:1', 'modalidades.*' => Rule::in(array_keys(ProjectTrackingService::MODALITIES)),
            'activo' => 'sometimes|boolean',
        ]);
        $values = (isset($data['modalidades']) ? $this->modalityColumns($data['modalidades']) : []) + (isset($data['activo']) ? ['activo' => $data['activo']] : []);
        DB::table('materias_seguimiento')->where('id', $id)->update($values + ['actualizado_por' => auth('api')->id(), 'actualizado_en' => now()]);
        $this->workflow->audit('seguimiento.materia', 'asignatura', $subject->asignatura_id, $subject, $data);
        $this->tracking->syncCareer($career);

        return response()->json(['message' => ($data['activo'] ?? true) ? 'Materia actualizada.' : 'La materia ya no pide avances; lo entregado se conserva.']);
    }

    public function updateTemplate(Request $request, int $id)
    {
        $this->requireStaff();
        $subject = DB::table('materias_seguimiento')->where('id', $id)->where('carrera_id', $this->career())->first();
        abort_unless($subject, 404);
        $data = $request->validate([
            'items' => 'present|array|max:20',
            'items.*.nombre' => 'required|string|max:150',
            'items.*.descripcion' => 'nullable|string|max:2000',
            'items.*.tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
            'items.*.semana' => 'nullable|integer|min:1|max:24',
            'items.*.ponderacion' => 'nullable|numeric|min:0|max:100',
        ]);
        $weights = array_sum(array_map(fn ($item) => (float) ($item['ponderacion'] ?? 0), $data['items']));
        abort_if($weights > 100.001, 422, 'Las ponderaciones suman más de 100 %.');
        DB::transaction(function () use ($id, $data, $subject) {
            $before = DB::table('plantillas_avance')->where('materia_seguimiento_id', $id)->orderBy('orden')->get();
            // Las entregas ya creadas guardan su propia copia; la plantilla sólo se usa al aplicarla.
            DB::table('plantillas_avance')->where('materia_seguimiento_id', $id)->delete();
            foreach (array_values($data['items']) as $index => $item) {
                DB::table('plantillas_avance')->insert([
                    'materia_seguimiento_id' => $id, 'orden' => $index + 1, 'nombre' => $item['nombre'], 'descripcion' => $item['descripcion'] ?? null,
                    'tipo_documento' => $item['tipo_documento'] ?? 'documento', 'semana' => $item['semana'] ?? null, 'ponderacion' => $item['ponderacion'] ?? null,
                ]);
            }
            $this->workflow->audit('seguimiento.plantilla', 'asignatura', $subject->asignatura_id, $before, $data['items']);
        });

        return response()->json(['message' => 'Plantilla guardada. Cada docente la aplica desde su materia.']);
    }

    // ---------------------------------------------------------------- Apoyo

    private function modalityColumns(array $modalities): array
    {
        return collect(ProjectTrackingService::MODALITIES)->mapWithKeys(fn ($column, $modality) => [$column => in_array($modality, $modalities, true)])->all();
    }

    private function deliverableFor(int $id): array
    {
        $item = DB::table('entregables')->where('id', $id)->where('activo', true)->first(['id', 'curso_id', 'nombre', 'fecha_limite']);
        abort_unless($item, 404, 'Avance no encontrado.');

        return [$item, $this->courseFor((int) $item->curso_id)];
    }

    private function deliveryFor(int $id): array
    {
        $row = DB::table('entregas as e')->join('entregables as d', 'd.id', '=', 'e.entregable_id')->where('e.id', $id)
            ->first(['e.id', 'e.proyecto_id', 'e.calificacion', 'd.curso_id', 'd.nombre']);
        abort_unless($row, 404, 'Entrega no encontrada.');

        return [$row, $this->courseFor((int) $row->curso_id)];
    }

    private function projectStudents(int $project)
    {
        return DB::table('proyecto_integrantes')->where('proyecto_id', $project)->whereIn('rol', ['lider', 'integrante'])->pluck('usuario_id');
    }

    private function pendingTemplate(object $course, $deliverables)
    {
        if (!$course->materia_id) return collect();
        $used = collect($deliverables)->pluck('plantilla_id')->filter()->map(fn ($id) => (int) $id)->all();

        return DB::table('plantillas_avance')->where('materia_seguimiento_id', $course->materia_id)->whereNotIn('id', $used)->orderBy('orden')->get();
    }

    private function cellState(object $deliverable, ?object $delivery, $due): string
    {
        if ($deliverable->estado === 'borrador') return 'borrador';
        if ($delivery) return $delivery->calificacion !== null ? 'calificado' : 'entregado';
        if (($due && Carbon::parse($due)->isPast()) || $deliverable->estado === 'cerrado') return 'vencido';

        return 'pendiente';
    }

    private function shapeCourse(object $course): array
    {
        $teachers = DB::table('curso_docentes as cd')->join('usuarios as u', 'u.id', '=', 'cd.docente_id')->where('cd.curso_id', $course->id)->where('cd.activo', true)
            ->get(['u.nombres', 'u.apellido_paterno'])->map(fn ($u) => trim("{$u->nombres} {$u->apellido_paterno}"))->values();

        return [
            'id' => $course->id, 'asignatura' => $course->asignatura, 'clave' => $course->asignatura_clave,
            'semestre' => $course->semestre !== null ? (int) $course->semestre : null, 'grupo' => $course->clave_grupo, 'grupo_nombre' => $course->grupo_nombre,
            'modalidades' => $course->materia_id ? $this->tracking->modalitiesOf($course) : [], 'docentes' => $teachers,
            'puede_editar' => $this->isStaff() || $this->tracking->teaches((string) auth('api')->id(), $course->id),
        ];
    }

    private function stats(object $course): array
    {
        $projects = array_keys($this->tracking->linkedProjects($course));
        $published = DB::table('entregables')->where('curso_id', $course->id)->where('activo', true)->where('estado', '!=', 'borrador')->get(['id', 'fecha_limite', 'estado']);
        $deliveries = $published->isEmpty() || !$projects ? collect() : DB::table('entregas')->whereIn('entregable_id', $published->pluck('id'))->whereIn('proyecto_id', $projects)
            ->get(['entregable_id', 'proyecto_id', 'calificacion', 'entregado_en']);
        $done = $deliveries->map(fn ($row) => "{$row->entregable_id}:{$row->proyecto_id}")->flip();
        $extensions = $published->isEmpty() ? collect() : DB::table('entrega_prorrogas')->whereIn('entregable_id', $published->pluck('id'))->get()
            ->keyBy(fn ($row) => "{$row->entregable_id}:{$row->proyecto_id}");
        $overdue = 0;
        foreach ($published as $deliverable) {
            foreach ($projects as $project) {
                $key = "{$deliverable->id}:{$project}";
                $due = $extensions->get($key)?->vence_en ?? $deliverable->fecha_limite;
                if (!$done->has($key) && (($due && Carbon::parse($due)->isPast()) || $deliverable->estado === 'cerrado')) $overdue++;
            }
        }
        $ungraded = $deliveries->whereNull('calificacion');

        return [
            'proyectos' => count($projects),
            'avances' => $published->count(),
            'borradores' => DB::table('entregables')->where('curso_id', $course->id)->where('activo', true)->where('estado', 'borrador')->count(),
            'esperadas' => $published->count() * count($projects),
            'entregadas' => $deliveries->count(),
            'vencidas' => $overdue,
            'sin_calificar' => $ungraded->count(),
            'atrasadas' => $ungraded->filter(fn ($row) => Carbon::parse($row->entregado_en)->lte(now()->subDays(5)))->count(),
            'promedio' => ($graded = $deliveries->whereNotNull('calificacion'))->isNotEmpty() ? round((float) $graded->avg('calificacion'), 2) : null,
        ];
    }
}
