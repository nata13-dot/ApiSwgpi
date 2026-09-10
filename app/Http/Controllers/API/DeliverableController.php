<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Deliverable;
use App\Models\Delivery;
use App\Models\EvaluationRoom;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityNotificationService;
use App\Services\DeliverySubmissionService;
use App\Services\ProtectedDownloadService;
use App\Support\CareerContext;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeliverableController extends Controller
{
    private const CATEGORY_RESEARCH_DOCUMENT = 'documento_investigacion';

    private const CATEGORY_EVALUATION_SLIDES = 'diapositiva_evaluacion';

    public function teacherMatrix(Request $request)
    {
        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 2) {
            return response()->json(['error' => 'Solo docentes pueden consultar esta vista'], 403);
        }

        $subjectFilter = $request->query('asignatura_id');
        $projects = Project::select(['id', 'title', 'semestre', 'year', 'subject_group_id'])
            ->with([
                'students:id,nombres,apa,ama,semestre,grupo',
                'advisors:id,nombres,apa,ama',
                'asignaturas' => fn ($query) => $query->select(['asignaturas.id', 'nombre', 'clave']),
            ])
            ->where('activo', true)
            ->whereHas('advisors', fn ($query) => $query->where('usuarios.id', $user->id))
            ->orderBy('title')
            ->get();

        $studentFilter = trim((string) $request->query('student', ''));

        $data = $projects->map(function (Project $project) use ($studentFilter, $subjectFilter) {
            $deliverables = $this->deliverablesForProject($project, $subjectFilter ? (int) $subjectFilter : null);
            $students = $project->students
                ->when($studentFilter !== '', function ($items) use ($studentFilter) {
                    $term = mb_strtolower($studentFilter);

                    return $items->filter(function ($student) use ($term) {
                        $haystack = mb_strtolower(trim("{$student->id} {$student->nombres} {$student->apa} {$student->ama}"));

                        return str_contains($haystack, $term);
                    });
                })
                ->values();

            $rows = $students->map(function ($student) use ($project, $deliverables) {
                $items = $deliverables->map(function (Deliverable $deliverable) use ($project) {
                    return $this->shapeTeacherMatrixItem(
                        $project,
                        $deliverable,
                        $deliverable->deliveries->first()
                    );
                })->values();

                $approvedGrades = $items
                    ->pluck('calificacion')
                    ->filter(fn ($grade) => $grade !== null && (float) $grade >= 70)
                    ->values();

                return [
                    'student' => [
                        'id' => $student->id,
                        'nombres' => $student->nombres,
                        'apa' => $student->apa,
                        'ama' => $student->ama,
                        'semestre' => $student->semestre,
                        'grupo' => $student->grupo,
                    ],
                    'items' => $items,
                    'summary' => [
                        'total' => $items->count(),
                        'entregados' => $items->where('status', 'entregado')->count(),
                        'faltantes' => $items->where('status', 'faltante')->count(),
                        'aprobados' => $items->where('approved', true)->count(),
                        'reprobados' => $items->filter(fn ($item) => $item['status'] === 'entregado' && $item['calificacion'] !== null && ! $item['approved'])->count(),
                        'promedio' => $approvedGrades->count() ? round($approvedGrades->avg(), 2) : null,
                    ],
                ];
            })->values();

            return [
                'project' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'semestre' => $project->semestre,
                    'year' => $project->year,
                    'subject_group_id' => $project->subject_group_id,
                ],
                'subjects' => $project->asignaturas->map(fn ($subject) => [
                    'id' => $subject->id,
                    'nombre' => $subject->nombre,
                    'clave' => $subject->clave,
                ])->values(),
                'students' => $rows,
            ];
        })->filter(fn ($project) => $project['students']->isNotEmpty())->values();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request)
    {
        $originalNameSelection = Schema::hasColumn('documento_versiones', 'nombre_original')
            ? 'ultima_version.nombre_original'
            : DB::raw('NULL as nombre_original');

        $query = DB::table('entregables')
            ->join('cursos', 'cursos.id', '=', 'entregables.curso_id')
            ->leftJoin('asignaturas', 'asignaturas.id', '=', 'cursos.asignatura_id')
            ->join('proyectos', function ($join) {
                $join->on('proyectos.grupo_id', '=', 'cursos.grupo_id')
                    ->where('proyectos.activo', true);
            })
            ->leftJoin('entregas', function ($join) {
                $join->on('entregas.entregable_id', '=', 'entregables.id')
                    ->on('entregas.proyecto_id', '=', 'proyectos.id');
            })
            ->leftJoin('documento_versiones as ultima_version', function ($join) {
                $join->on('ultima_version.documento_id', '=', 'entregas.documento_id')
                    ->whereRaw('ultima_version.numero_version = (SELECT MAX(dv.numero_version) FROM documento_versiones dv WHERE dv.documento_id = entregas.documento_id)');
            })
            ->leftJoin('usuarios as remitentes', 'remitentes.id', '=', 'entregas.enviado_por')
            ->select([
                'entregables.id',
                'entregables.nombre',
                'entregables.descripcion',
                'entregables.tipo_documento',
                'entregables.estado',
                'entregables.activo',
                'entregables.creado_en',
                DB::raw('NULL as competencia_id'),
                'cursos.asignatura_id',
                'asignaturas.nombre as asignatura_nombre',
                'asignaturas.clave as asignatura_clave',
                'proyectos.id as project_id',
                'proyectos.titulo as project_title',
                'entregas.id as delivery_id',
                'entregas.documento_id',
                'entregas.enviado_por as submitted_by_id',
                'entregas.entregado_en',
                'entregas.calificacion',
                'entregas.comentarios_docente',
                'ultima_version.id as version_id',
                'ultima_version.numero_version',
                'ultima_version.ruta_archivo as archivo_path',
                'ultima_version.nombre_archivo',
                $originalNameSelection,
                'ultima_version.mime_type',
                'remitentes.nombres as submitted_by_nombres',
                'remitentes.apellido_paterno as submitted_by_apa',
                'remitentes.apellido_materno as submitted_by_ama',
            ]);
        $user = auth('api')->user();

        if ((int) $user->perfil_id === 2) {
            $query->whereExists(function ($subquery) use ($user) {
                $subquery->selectRaw('1')
                    ->from('curso_docentes')
                    ->whereColumn('curso_docentes.curso_id', 'entregables.curso_id')
                    ->where('curso_docentes.docente_id', $user->id)
                    ->where('curso_docentes.activo', true);
            });
        } elseif ((int) $user->perfil_id === 3) {
            $query->whereExists(function ($subquery) use ($user) {
                $subquery->selectRaw('1')
                    ->from('proyecto_integrantes')
                    ->join('proyectos as pi_proyectos', 'pi_proyectos.id', '=', 'proyecto_integrantes.proyecto_id')
                    ->whereColumn('pi_proyectos.grupo_id', 'cursos.grupo_id')
                    ->where('proyecto_integrantes.usuario_id', $user->id)
                    ->whereIn('proyecto_integrantes.rol', ['lider', 'integrante']);
            });
        }

        if ($request->filled('project_id')) {
            $query->where('proyectos.id', $request->project_id);
        }

        if ($request->filled('competencia_id')) {
            $query->where('cursos.asignatura_id', $request->competencia_id);
        }

        if ($request->filled('asignatura_id')) {
            $query->where('cursos.asignatura_id', $request->asignatura_id);
        }

        if ($request->filled('estado')) {
            $query->where('entregables.estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query->where(function ($q) use ($term) {
                $q->where('entregables.nombre', 'like', "%{$term}%")
                    ->orWhere('entregables.descripcion', 'like', "%{$term}%");
            });
        }

        $paginated = $query->orderByDesc('entregables.creado_en')->paginate(12);
        $paginated->getCollection()->transform(fn ($item) => $this->shapeCatalogDeliverable($item));

        return response()->json($paginated);
    }

    public function myDeliverables()
    {
        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 3) {
            return response()->json(['error' => 'Solo estudiantes pueden consultar sus entregables'], 403);
        }

        $projects = Project::with('subjectGroup')
            ->where('activo', true)
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id))
            ->get();
        $items = $projects->flatMap(function (Project $project) {
            return $this->deliverablesForProject($project)
                ->map(fn (Deliverable $deliverable) => $this->shapeStudentDeliverable(
                    $deliverable,
                    $project,
                    $deliverable->deliveries->first()
                ));
        })->sortByDesc('created_at')->values();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function evaluationDocuments()
    {
        $user = auth('api')->user();

        $projectsQuery = Project::select(['id', 'title', 'description', 'semestre', 'year', 'subject_group_id', 'authors'])
            ->with([
                'students:id,nombres,apa,ama',
                'advisors:id,nombres,apa,ama',
                'asignaturas:id,nombre,clave',
                'evaluations:id,project_id,evaluation_room_id,estado,resultado,fecha_exposicion',
                'evaluations.room:id,nombre,salon,fecha_evaluacion',
            ])
            ->where('activo', true)
            ->whereHas('subjectGroup', fn ($groupQuery) => $groupQuery->whereBetween('semestre', [5, 9]));

        if ((int) $user->perfil_id === 2) {
            $roomProjectIds = EvaluationRoom::where(function ($query) use ($user) {
                $query->where('responsible_teacher_id', $user->id)
                    ->orWhereHas('teachers', fn ($teacherQuery) => $teacherQuery->where('usuarios.id', $user->id));
            })
                ->with('projects:id')
                ->get()
                ->flatMap(fn ($room) => $room->projects->pluck('id'))
                ->unique()
                ->values();

            $projectsQuery->where(function ($query) use ($user, $roomProjectIds) {
                $query->whereHas('advisors', fn ($advisorQuery) => $advisorQuery->where('usuarios.id', $user->id));
                if ($roomProjectIds->isNotEmpty()) {
                    $query->orWhereIn('id', $roomProjectIds);
                }
            });
        } elseif ((int) $user->perfil_id === 3) {
            $projectsQuery->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id));
        } elseif (! $user->canManageProjects()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $projects = $projectsQuery->orderBy('title')->get();

        $data = $projects->map(function (Project $project) use ($user) {
            $this->ensureEvaluationDeliverables($project);
            $deliverables = $this->deliverablesForProject($project)
                ->filter(fn (Deliverable $deliverable) => in_array($this->deliverableCategory($deliverable), [
                    self::CATEGORY_RESEARCH_DOCUMENT,
                    self::CATEGORY_EVALUATION_SLIDES,
                ], true));

            return [
                'project' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'semestre' => $project->semestre,
                    'year' => $project->year,
                    'authors' => $project->authors,
                ],
                'integrantes' => $project->students->map(fn ($student) => [
                    'id' => $student->id,
                    'nombres' => $student->nombres,
                    'apa' => $student->apa,
                    'ama' => $student->ama,
                ])->values(),
                'asignaturas' => $project->asignaturas->map(fn ($subject) => [
                    'id' => $subject->id,
                    'nombre' => $subject->nombre,
                    'clave' => $subject->clave,
                ])->values(),
                'requiere_documento_investigacion' => $this->requiresResearchDocument($project),
                'puede_subir' => $user->canManageProjects() || $project->students->contains(fn ($student) => (string) $student->id === (string) $user->id),
                'evaluaciones' => $project->evaluations->map(fn ($evaluation) => [
                    'id' => $evaluation->id,
                    'estado' => $evaluation->estado,
                    'resultado' => $evaluation->resultado,
                    'fecha_exposicion' => optional($evaluation->fecha_exposicion)->toDateTimeString(),
                    'sala' => $evaluation->room ? [
                        'id' => $evaluation->room->id,
                        'nombre' => $evaluation->room->nombre,
                        'salon' => $evaluation->room->salon,
                        'fecha_evaluacion' => optional($evaluation->room->fecha_evaluacion)->toDateTimeString(),
                    ] : null,
                ])->values(),
                // Nombre legado del campo JSON; los datos provienen de entregables/entregas.
                'entregables_proyecto' => $deliverables->map(fn ($deliverable) => $this->shapeEvaluationDocument(
                    $deliverable,
                    $project,
                    $deliverable->deliveries->first()
                ))->values(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'project_id' => 'nullable|exists:proyectos,id',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string|max:255',
                'asignatura_id' => 'required_without:competencia_id|exists:asignaturas,id',
                'competencia_id' => 'required_without:asignatura_id|exists:asignaturas,id',
                'autores' => 'nullable|string|max:1000',
                'estado' => 'nullable|string',
            ]);

            $deliverable = Deliverable::create([
                'curso_id' => $this->courseIdForDeliverable((int) ($validated['asignatura_id'] ?? $validated['competencia_id']), $validated['project_id'] ?? null),
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'tipo_documento' => $validated['tipo_documento'] ?? 'documento',
                'estado' => $this->normalizedDeliverableState($validated['estado'] ?? 'publicado'),
                'activo' => true,
            ]);
            $project = ! empty($validated['project_id']) ? Project::find($validated['project_id']) : null;
            $this->notifyActivityEnabled($deliverable, $project, auth('api')->user());

            return response()->json(['message' => 'Entregable creado', 'deliverable' => $deliverable], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $deliverable = Deliverable::with([
            'course.subject',
            'deliveries.project',
            'deliveries.submittedBy',
            'deliveries.document.latestVersion',
        ])->find($id);
        if (! $deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }

        return response()->json($deliverable);
    }

    public function update(Request $request, $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (! $deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            $validated = $request->validate([
                'project_id' => 'nullable|exists:proyectos,id',
                'asignatura_id' => 'nullable|exists:asignaturas,id',
                'competencia_id' => 'nullable|exists:asignaturas,id',
                'nombre' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'estado' => 'nullable|string',
                'autores' => 'nullable|string|max:1000',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string|max:255',
                'activo' => 'nullable|boolean',
            ]);

            $payload = collect($validated)->only(['nombre', 'descripcion', 'tipo_documento', 'activo'])->toArray();
            if (isset($validated['estado'])) {
                $payload['estado'] = $this->normalizedDeliverableState($validated['estado']);
            }
            if (isset($validated['asignatura_id']) || isset($validated['competencia_id'])) {
                $payload['curso_id'] = $this->courseIdForDeliverable(
                    (int) ($validated['asignatura_id'] ?? $validated['competencia_id']),
                    $validated['project_id'] ?? null
                );
            }

            $deliverable->update($payload);

            return response()->json(['message' => 'Entregable actualizado', 'deliverable' => $deliverable]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $deliverable = Deliverable::find($id);
        if (! $deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }

        // Los documentos y sus versiones son históricos. Desactivar la actividad
        // evita que un borrado en cascada destruya la relación con sus entregas.
        $deliverable->update(['activo' => false]);

        return response()->json(['message' => 'Entregable eliminado']);
    }

    /**
     * Endpoint para calificar un entregable
     * POST /deliverables/{id}/calificar
     *
     * Solo docentes (asesores) y admin pueden calificar
     */
    public function calificar(Request $request, int $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (! $deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar que el usuario sea docente o admin
            $user = auth('api')->user();
            if (! in_array($user->perfil_id, [1, 2])) {
                return response()->json(['error' => 'Solo docentes y admin pueden calificar'], 403);
            }

            $validated = $request->validate([
                'project_id' => 'required|integer|exists:proyectos,id',
                'calificacion' => 'required|numeric|min:0|max:100',
            ]);
            $delivery = Delivery::with('project')
                ->where('entregable_id', $deliverable->id)
                ->where('proyecto_id', $validated['project_id'])
                ->first();
            if (! $delivery) {
                return response()->json(['error' => 'La entrega del proyecto no existe'], 404);
            }
            if (! $this->canAccessProject($user, $delivery->project)) {
                return response()->json(['error' => 'No tienes acceso a este entregable'], 403);
            }

            if ((float) $validated['calificacion'] < 0 || (float) $validated['calificacion'] > 100) {
                return response()->json(['error' => 'La calificación debe estar entre 0 y 100'], 422);
            }

            $grade = (float) $validated['calificacion'];
            $delivery->update([
                'calificacion' => $grade,
            ]);

            return response()->json([
                'message' => 'Entregable calificado exitosamente',
                'deliverable' => $deliverable,
                'delivery' => $delivery->fresh(['document.latestVersion']),
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para descargar un archivo de entregable
     * GET /deliverables/{id}/download
     */
    public function download(Request $request, int $id, ProtectedDownloadService $downloads)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (! $deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            $user = auth('api')->user();
            $request->validate(['project_id' => 'nullable|integer|exists:proyectos,id']);
            $deliveryQuery = Delivery::with(['project', 'document.latestVersion'])
                ->where('entregable_id', $deliverable->id);
            if ($request->filled('project_id')) {
                $deliveryQuery->where('proyecto_id', $request->integer('project_id'));
            }
            $accessibleDeliveries = $deliveryQuery->get()
                ->filter(fn (Delivery $candidate) => $candidate->project && $this->canAccessProject($user, $candidate->project))
                ->values();
            if ($accessibleDeliveries->isEmpty()) {
                return response()->json(['error' => 'No tienes acceso a este entregable'], 403);
            }
            if (! $request->filled('project_id') && $accessibleDeliveries->count() > 1) {
                throw ValidationException::withMessages([
                    'project_id' => ['Indica el proyecto para descargar este entregable.'],
                ]);
            }
            $delivery = $accessibleDeliveries->first();
            $version = $delivery->document?->latestVersion;
            if (! $version) {
                return response()->json(['error' => 'El entregable no tiene archivo asociado'], 404);
            }

            return $downloads->download($version, $version->original_name ?: $version->file_name);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para subir archivo de entregable
     * POST /deliverables/{id}/upload
     */
    public function upload(Request $request, int $id, DeliverySubmissionService $submissions)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (! $deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            $user = auth('api')->user();
            $maxFileSizeKb = ((int) SystemSetting::valueFor('max_file_size_mb', 50)) * 1024;
            $validated = $request->validate([
                'project_id' => 'required|integer|exists:proyectos,id',
                'archivo' => 'required|file|max:'.$maxFileSizeKb,
            ]);
            $project = Project::findOrFail((int) $validated['project_id']);

            $result = $submissions->submit(
                $deliverable,
                $project,
                $user,
                $request->file('archivo'),
                $this->allowedExtensionsFor($deliverable),
                (int) SystemSetting::valueFor('max_file_size_mb', 50)
            );
            $this->notifyStudentUpload($deliverable, $project, $user);
            $version = $result['version'];

            return response()->json([
                'message' => 'Archivo subido exitosamente',
                'deliverable' => $deliverable,
                'delivery' => $result['delivery'],
                'document' => $result['document'],
                'version' => $version,
                // Compatibilidad de lectura; no representa una columna de entregables.
                'archivo_path' => $version->file_path,
                'file_path' => $version->file_path,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function notifyActivityEnabled(Deliverable $deliverable, ?Project $project, User $actor): void
    {
        if ((int) $actor->perfil_id === 3 || ! $project) {
            return;
        }

        $project->loadMissing('students');
        $deliverable->loadMissing('course.subject');
        $recipientIds = $project->students->pluck('id');
        $subject = $deliverable->course?->subject?->nombre;
        $context = $subject ? " de {$subject}" : '';

        ActivityNotificationService::send(
            $recipientIds,
            (string) $actor->id,
            'actividad_habilitada',
            'Nueva actividad disponible',
            "{$deliverable->nombre}{$context} fue habilitada para tu proyecto.",
            '/pages/student/my-deliverables.php'
        );
    }

    private function shapeCatalogDeliverable(object $item): array
    {
        $submittedBy = $item->submitted_by_id ? [
            'id' => $item->submitted_by_id,
            'nombres' => $item->submitted_by_nombres,
            'apa' => $item->submitted_by_apa,
            'ama' => $item->submitted_by_ama,
        ] : null;

        return [
            'id' => $item->id,
            'project_id' => $item->project_id ? (int) $item->project_id : null,
            'competencia_id' => null,
            'asignatura_id' => $item->asignatura_id ? (int) $item->asignatura_id : null,
            'nombre' => $item->nombre,
            'descripcion' => $item->descripcion,
            'estado' => $item->estado,
            'archivo_path' => $item->archivo_path,
            'file_path' => $item->archivo_path,
            'tipo_documento' => $item->tipo_documento,
            'calificacion' => $item->calificacion !== null ? (float) $item->calificacion : null,
            'fecha_calificacion' => $item->entregado_en,
            'entregado_en' => $item->entregado_en,
            'submitted_by' => $item->submitted_by_id,
            'submittedBy' => $submittedBy,
            'delivery_id' => $item->delivery_id ? (int) $item->delivery_id : null,
            'document_id' => $item->documento_id ? (int) $item->documento_id : null,
            'version_id' => $item->version_id ? (int) $item->version_id : null,
            'version_number' => $item->numero_version ? (int) $item->numero_version : null,
            'nombre_archivo' => $item->nombre_original ?: $item->nombre_archivo,
            'mime_type' => $item->mime_type,
            'project' => $item->project_id ? [
                'id' => (int) $item->project_id,
                'title' => $item->project_title,
            ] : null,
            // Alias legado: representa la asignatura del curso, no una fila de competencias.
            'competencia' => $item->asignatura_id ? [
                'id' => (int) $item->asignatura_id,
                'nombre' => $item->asignatura_nombre ?: 'Actividad general',
                'asignatura_id' => (int) $item->asignatura_id,
                'asignatura' => [
                    'id' => (int) $item->asignatura_id,
                    'nombre' => $item->asignatura_nombre,
                    'clave' => $item->asignatura_clave,
                ],
            ] : null,
        ];
    }

    private function courseIdForDeliverable(int $subjectId, $projectId = null): int
    {
        $query = DB::table('cursos')
            ->where('carrera_id', app(CareerContext::class)->careerId())
            ->where('asignatura_id', $subjectId)
            ->where('activo', true);

        if ($projectId) {
            $groupId = Project::where('id', $projectId)->value('grupo_id');
            if ($groupId) {
                $courseId = (clone $query)->where('grupo_id', $groupId)->value('id');
                if ($courseId) {
                    return (int) $courseId;
                }
            }
        }

        $courseId = $query->orderBy('id')->value('id');
        if (! $courseId) {
            throw ValidationException::withMessages([
                'competencia_id' => ['La materia seleccionada no tiene curso activo para asociar el entregable.'],
            ]);
        }

        return (int) $courseId;
    }

    private function normalizedDeliverableState(?string $state): string
    {
        return match ($state) {
            'borrador' => 'borrador',
            'cerrado', 'aprobado', 'revisado' => 'cerrado',
            default => 'publicado',
        };
    }

    private function notifyStudentUpload(Deliverable $deliverable, Project $project, User $actor): void
    {
        if ((int) $actor->perfil_id !== 3) {
            return;
        }

        $project->loadMissing('advisors');
        $advisorIds = $project->advisors->pluck('id');
        $adminIds = User::admins()->where('activo', true)->pluck('id');
        $studentName = $actor->getFullName() ?: $actor->id;

        ActivityNotificationService::send(
            $advisorIds,
            (string) $actor->id,
            'actividad_entregada',
            'Actividad entregada',
            "{$studentName} subio la actividad \"{$deliverable->nombre}\".",
            '/pages/teacher/my-deliverables.php'
        );
        ActivityNotificationService::send(
            $adminIds,
            (string) $actor->id,
            'actividad_entregada',
            'Actividad entregada',
            "{$studentName} subio la actividad \"{$deliverable->nombre}\".",
            '/pages/admin/deliverables.php'
        );
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        if ($user->canManageProjects()) {
            return true;
        }
        if ((int) $user->perfil_id === 3) {
            return $project->students()->where('usuarios.id', $user->id)->exists();
        }
        if ((int) $user->perfil_id === 2) {
            return $project->advisors()->where('usuarios.id', $user->id)->exists()
                || EvaluationRoom::whereHas('projects', fn ($query) => $query->where('proyectos.id', $project->id))
                    ->where(function ($query) use ($user) {
                        $query->where('responsible_teacher_id', $user->id)
                            ->orWhereHas('teachers', fn ($teacherQuery) => $teacherQuery->where('usuarios.id', $user->id));
                    })->exists();
        }

        return false;
    }

    private function shapeTeacherMatrixItem(
        Project $project,
        Deliverable $deliverable,
        ?Delivery $delivery
    ): array {
        $grade = $delivery?->calificacion;
        $approved = $grade !== null && (float) $grade >= 70;
        $version = $delivery?->document?->latestVersion;

        $subject = $deliverable->course?->subject;

        return [
            // Alias legado sin relación ficticia: describe la asignatura del curso.
            'competencia' => $subject ? [
                'id' => $subject->id,
                'nombre' => $subject->nombre,
                'asignatura_id' => $subject->id,
                'fecha_inicio' => null,
                'fecha_fin' => null,
            ] : null,
            'asignatura' => [
                'id' => $subject?->id,
                'nombre' => $subject?->nombre,
                'clave' => $subject?->clave,
            ],
            'status' => $delivery ? 'entregado' : 'faltante',
            'approved' => $approved,
            'calificacion' => $grade,
            'calificacion_efectiva' => $approved ? (float) $grade : 0,
            'deliverable' => [
                'id' => $deliverable->id,
                'nombre' => $deliverable->nombre,
                'descripcion' => $deliverable->descripcion,
                'estado' => $deliverable->estado,
                'project_id' => $project->id,
                'delivery_id' => $delivery?->id,
                'document_id' => $delivery?->documento_id,
                'archivo_path' => $version?->ruta_archivo,
                'file_path' => $version?->ruta_archivo,
                'tipo_documento' => $deliverable->tipo_documento,
                'fecha_calificacion' => optional($delivery?->entregado_en)->toDateTimeString(),
                'calificado_por' => null,
            ],
        ];
    }

    private function shapeStudentDeliverable(Deliverable $deliverable, Project $project, ?Delivery $delivery): array
    {
        $version = $delivery?->document?->latestVersion;
        $subject = $deliverable->course?->subject;

        return [
            'id' => $deliverable->id,
            'project_id' => $project->id,
            'delivery_id' => $delivery?->id,
            'document_id' => $delivery?->documento_id,
            'competencia_id' => null,
            'nombre' => $deliverable->nombre,
            'descripcion' => $deliverable->descripcion,
            'estado' => $deliverable->estado,
            'archivo_path' => $version?->ruta_archivo,
            'file_path' => $version?->ruta_archivo,
            'nombre_archivo' => $version?->nombre_original ?: $version?->nombre_archivo,
            'version_number' => $version?->numero_version,
            'tipo_documento' => $deliverable->tipo_documento,
            'calificacion' => $delivery?->calificacion !== null ? (float) $delivery->calificacion : null,
            'fecha_calificacion' => optional($delivery?->entregado_en)->toDateTimeString(),
            'created_at' => optional($deliverable->created_at)->toDateTimeString(),
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'subject_group_id' => $project->subject_group_id,
            ],
            // Alias legado: es la asignatura del curso, no una competencia persistida.
            'competencia' => $subject ? [
                'id' => $subject->id,
                'nombre' => $subject->nombre,
                'asignatura_id' => $subject->id,
                'asignatura' => $subject,
            ] : null,
            'submitted_by' => $delivery?->enviado_por,
            'submittedBy' => $delivery?->submittedBy,
            'calificado_por' => null,
            'calificadoPor' => null,
        ];
    }

    private function ensureEvaluationDeliverables(Project $project): void
    {
        $courses = Course::with('subject')
            ->where('carrera_id', $project->carrera_id)
            ->where('grupo_id', $project->grupo_id)
            ->where('activo', true)
            ->orderByDesc('es_seguimiento_proyecto')
            ->orderBy('id')
            ->get();
        $presentationCourse = $courses->first();
        if ($presentationCourse) {
            Deliverable::firstOrCreate(
                ['curso_id' => $presentationCourse->id, 'nombre' => 'Diapositivas de evaluacion'],
                [
                    'carrera_id' => $project->carrera_id,
                    'descripcion' => 'Archivo de apoyo para la presentacion del proyecto en evaluaciones.',
                    'tipo_documento' => 'presentacion',
                    'estado' => 'publicado',
                    'activo' => true,
                ]
            );
        }

        $researchCourse = $courses->first(fn (Course $course) => $course->subject && $this->isResearchSubject(
            (string) $course->subject->nombre,
            (string) $course->subject->clave
        ));
        if ($researchCourse) {
            Deliverable::firstOrCreate(
                ['curso_id' => $researchCourse->id, 'nombre' => 'Documento de investigacion'],
                [
                    'carrera_id' => $project->carrera_id,
                    'descripcion' => 'Documento adicional requerido para proyectos con Taller de Investigacion I o II.',
                    'tipo_documento' => 'documento',
                    'estado' => 'publicado',
                    'activo' => true,
                ]
            );
        }
    }

    private function requiresResearchDocument(Project $project): bool
    {
        $project->loadMissing('asignaturas:id,nombre,clave');

        return $project->asignaturas->contains(fn ($subject) => $this->isResearchSubject(
            (string) $subject->nombre,
            (string) $subject->clave
        ));
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function allowedExtensionsFor(Deliverable $deliverable): ?array
    {
        return match ($this->deliverableCategory($deliverable)) {
            self::CATEGORY_RESEARCH_DOCUMENT => ['pdf', 'doc', 'docx'],
            self::CATEGORY_EVALUATION_SLIDES => ['ppt', 'pptx', 'pdf'],
            default => SystemSetting::valueFor('allowed_file_types', config('uploads.default_extensions')),
        };
    }

    private function shapeEvaluationDocument(Deliverable $deliverable, Project $project, ?Delivery $delivery): array
    {
        $version = $delivery?->document?->latestVersion;
        $submittedBy = $delivery?->submittedBy;

        return [
            'id' => $deliverable->id,
            'nombre' => $deliverable->nombre,
            'descripcion' => $deliverable->descripcion,
            'categoria' => $this->deliverableCategory($deliverable),
            'estado' => $deliverable->estado,
            'project_id' => $project->id,
            'delivery_id' => $delivery?->id,
            'document_id' => $delivery?->documento_id,
            'archivo_path' => $version?->ruta_archivo,
            'file_path' => $version?->ruta_archivo,
            'tipo_documento' => $deliverable->tipo_documento,
            'calificacion' => $delivery?->calificacion !== null ? (float) $delivery->calificacion : null,
            'fecha_calificacion' => optional($delivery?->entregado_en)->toDateTimeString(),
            'submitted_by' => $submittedBy ? [
                'id' => $submittedBy->id,
                'nombres' => $submittedBy->nombres,
                'apa' => $submittedBy->apa,
                'ama' => $submittedBy->ama,
            ] : null,
            'calificado_por' => null,
            'version_number' => $version?->numero_version,
            'allowed_extensions' => $this->allowedExtensionsFor($deliverable),
        ];
    }

    private function deliverablesForProject(Project $project, ?int $subjectId = null)
    {
        $query = Deliverable::with([
            'course.subject',
            'deliveries' => fn ($delivery) => $delivery
                ->where('proyecto_id', $project->id)
                ->with(['document.latestVersion', 'submittedBy']),
        ])
            ->where('activo', true)
            ->whereHas('course', function ($course) use ($project, $subjectId) {
                $course->where('grupo_id', $project->grupo_id)->where('activo', true);
                if ($subjectId) {
                    $course->where('asignatura_id', $subjectId);
                }
            });

        return $query->orderByDesc('creado_en')->get();
    }

    private function isResearchSubject(string $name, string $key): bool
    {
        $name = $this->normalizeText($name);
        $key = $this->normalizeText($key);

        return preg_match('/\btaller de investigacion (i|ii|1|2)\b/', $name) === 1
            || in_array($key, ['ac009', 'ac010'], true);
    }

    private function deliverableCategory(Deliverable $deliverable): string
    {
        return match ($deliverable->nombre) {
            'Diapositivas de evaluacion' => self::CATEGORY_EVALUATION_SLIDES,
            'Documento de investigacion' => self::CATEGORY_RESEARCH_DOCUMENT,
            default => 'materia',
        };
    }
}
