<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Competencia;
use App\Models\Deliverable;
use App\Models\EvaluationRoom;
use App\Models\Project;
use App\Services\BusinessValidationService;
use App\Services\FileService;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Exception;

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
                'asignaturas.competencias' => function ($query) use ($subjectFilter) {
                    $query->select(['id', 'asignatura_id', 'nombre', 'fecha_inicio', 'fecha_fin']);
                    if ($subjectFilter) {
                        $query->where('asignatura_id', $subjectFilter);
                    }
                },
                'deliverables' => function ($query) use ($subjectFilter) {
                    $query->select([
                        'id', 'project_id', 'competencia_id', 'nombre', 'descripcion', 'estado',
                        'archivo_path', 'tipo_documento', 'submitted_by', 'calificacion',
                        'fecha_calificacion', 'calificado_por',
                    ])->with([
                        'competencia:id,asignatura_id,nombre,fecha_inicio,fecha_fin',
                        'competencia.asignatura:id,nombre,clave',
                        'submittedBy:id,nombres,apa,ama',
                        'calificadoPor:id,nombres,apa,ama',
                    ]);

                    if ($subjectFilter) {
                        $query->whereHas('competencia', fn ($competenciaQuery) => $competenciaQuery->where('asignatura_id', $subjectFilter));
                    }
                },
            ])
            ->where('activo', true)
            ->whereHas('advisors', fn ($query) => $query->where('users.id', $user->id))
            ->orderBy('title')
            ->get();

        $studentFilter = trim((string) $request->query('student', ''));

        $data = $projects->map(function (Project $project) use ($studentFilter, $subjectFilter) {
            $competencias = $project->asignaturas
                ->flatMap(fn ($asignatura) => $asignatura->competencias->map(function (Competencia $competencia) use ($asignatura) {
                    $competencia->setRelation('asignatura', $asignatura);
                    return $competencia;
                }))
                ->when($subjectFilter, fn ($items) => $items->filter(fn ($competencia) => (int) $competencia->asignatura_id === (int) $subjectFilter))
                ->sortBy(fn ($competencia) => ($competencia->asignatura?->nombre ?? '') . ' ' . $competencia->nombre)
                ->values();

            $students = $project->students
                ->when($studentFilter !== '', function ($items) use ($studentFilter) {
                    $term = mb_strtolower($studentFilter);
                    return $items->filter(function ($student) use ($term) {
                        $haystack = mb_strtolower(trim("{$student->id} {$student->nombres} {$student->apa} {$student->ama}"));
                        return str_contains($haystack, $term);
                    });
                })
                ->values();

            $rows = $students->map(function ($student) use ($project, $competencias) {
                $items = $competencias->map(function (Competencia $competencia) use ($project, $student) {
                    $deliverable = $project->deliverables
                        ->where('competencia_id', $competencia->id)
                        ->where('submitted_by', $student->id)
                        ->sortByDesc('id')
                        ->first();

                    return $this->shapeTeacherMatrixItem($competencia, $deliverable);
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
                        'reprobados' => $items->filter(fn ($item) => $item['status'] === 'entregado' && $item['calificacion'] !== null && !$item['approved'])->count(),
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
        $query = Deliverable::with(['project.advisors', 'competencia.asignatura', 'tags', 'submittedBy', 'calificadoPor']);
        $user = auth('api')->user();

        if ((int) $user->perfil_id === 2) {
            $query->whereHas('project.advisors', fn ($q) => $q->where('users.id', $user->id));
        } elseif ((int) $user->perfil_id === 3) {
            $query->where('submitted_by', $user->id);
        }
        
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('competencia_id')) {
            $query->where('competencia_id', $request->competencia_id);
        }

        if ($request->filled('asignatura_id')) {
            $query->whereHas('competencia', fn ($q) => $q->where('asignatura_id', $request->asignatura_id));
        }
        
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query->where(function($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%");
            });
        }

        return response()->json($query->paginate(12));
    }

    public function evaluationDocuments()
    {
        $user = auth('api')->user();

        $projectsQuery = Project::select(['id', 'title', 'description', 'semestre', 'year', 'subject_group_id', 'authors'])
            ->with([
                'students:id,nombres,apa,ama',
                'advisors:id,nombres,apa,ama',
                'asignaturas:id,nombre,clave',
                'deliverables' => fn ($query) => $this->evaluationDocumentsQuery($query)
                    ->with(['submittedBy:id,nombres,apa,ama', 'calificadoPor:id,nombres,apa,ama']),
                'evaluations:id,project_id,evaluation_room_id,estado,resultado,fecha_exposicion',
                'evaluations.room:id,nombre,salon,fecha_evaluacion',
            ])
            ->where('activo', true);

        if ((int) $user->perfil_id === 2) {
            $roomProjectIds = EvaluationRoom::where(function ($query) use ($user) {
                    $query->where('responsible_teacher_id', $user->id)
                        ->orWhereHas('teachers', fn ($teacherQuery) => $teacherQuery->where('users.id', $user->id));
                })
                ->with('projects:id')
                ->get()
                ->flatMap(fn ($room) => $room->projects->pluck('id'))
                ->unique()
                ->values();

            $projectsQuery->where(function ($query) use ($user, $roomProjectIds) {
                $query->whereHas('advisors', fn ($advisorQuery) => $advisorQuery->where('users.id', $user->id));
                if ($roomProjectIds->isNotEmpty()) {
                    $query->orWhereIn('id', $roomProjectIds);
                }
            });
        } elseif ((int) $user->perfil_id === 3) {
            $projectsQuery->whereHas('students', fn ($query) => $query->where('users.id', $user->id));
        } elseif ((int) $user->perfil_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $projects = $projectsQuery->orderBy('title')->get();

        $data = $projects->map(function (Project $project) use ($user) {
            $this->ensureEvaluationDeliverables($project);
            $project->load([
                'deliverables' => fn ($query) => $this->evaluationDocumentsQuery($query)
                    ->with(['submittedBy:id,nombres,apa,ama', 'calificadoPor:id,nombres,apa,ama']),
            ]);

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
                'puede_subir' => (int) $user->perfil_id === 1 || $project->students->contains(fn ($student) => (string) $student->id === (string) $user->id),
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
                'deliverables' => $project->deliverables->map(fn ($deliverable) => $this->shapeEvaluationDocument($deliverable))->values(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'project_id' => 'nullable|exists:projects,id',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string|max:255',
                'competencia_id' => 'required|exists:competencias,id',
                'autores' => 'nullable|string|max:1000',
            ]);

            $validated['submitted_by'] = auth('api')->id();
            $validated['estado'] = 'pendiente';
            $deliverable = Deliverable::create($validated);

            return response()->json(['message' => 'Entregable creado', 'deliverable' => $deliverable], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $deliverable = Deliverable::with(['project', 'competencia', 'tags', 'submittedBy', 'calificadoPor'])->find($id);
        if (!$deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }
        return response()->json($deliverable);
    }

    public function update(Request $request, $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            $validated = $request->validate([
                'project_id' => 'nullable|exists:projects,id',
                'competencia_id' => 'nullable|exists:competencias,id',
                'nombre' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'estado' => 'nullable|in:pendiente,enviado,revisado,aprobado',
                'autores' => 'nullable|string|max:1000',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string|max:255',
                'activo' => 'nullable|boolean',
            ]);

            $deliverable->update($validated);
            return response()->json(['message' => 'Entregable actualizado', 'deliverable' => $deliverable]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $deliverable = Deliverable::find($id);
        if (!$deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }
        
        // Eliminar archivo si existe
        if ($deliverable->archivo_path) {
            FileService::deleteFile($deliverable->archivo_path);
        }
        
        $deliverable->delete();
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
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar que el usuario sea docente o admin
            $user = auth('api')->user();
            if (!in_array($user->perfil_id, [1, 2])) {
                return response()->json(['error' => 'Solo docentes y admin pueden calificar'], 403);
            }

            // Validar acceso: docente solo puede calificar sus proyectos
            if (!BusinessValidationService::validateAccesoEntrega($id, $user->id, $user->perfil_id)) {
                return response()->json(['error' => 'No tienes acceso a este entregable'], 403);
            }

            $validated = $request->validate([
                'calificacion' => 'required|numeric|min:0|max:100',
            ]);

            // Validar que calificación esté en rango
            if (!BusinessValidationService::validateCalificacion($validated['calificacion'])) {
                return response()->json(['error' => 'La calificación debe estar entre 0 y 100'], 422);
            }

            // Actualizar entregable
            $grade = (float) $validated['calificacion'];

            $deliverable->update([
                'calificacion' => $grade,
                'fecha_calificacion' => now(),
                'calificado_por' => $user->id,
                'estado' => $grade >= 70 ? 'aprobado' : 'revisado',
            ]);

            return response()->json([
                'message' => 'Entregable calificado exitosamente',
                'deliverable' => $deliverable->load('calificadoPor')
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para descargar un archivo de entregable
     * GET /deliverables/{id}/download
     */
    public function download(int $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar acceso
            $user = auth('api')->user();
            if (!BusinessValidationService::validateAccesoEntrega($id, $user->id, $user->perfil_id)) {
                return response()->json(['error' => 'No tienes acceso a este entregable'], 403);
            }

            // Descargar archivo
            return FileService::downloadDeliverable($deliverable);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para subir archivo de entregable
     * POST /deliverables/{id}/upload
     */
    public function upload(Request $request, int $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar que el usuario pueda subir este entregable.
            $user = auth('api')->user();
            $deliverable->loadMissing('project.students');
            $isSpecialEvaluationDocument = in_array($this->deliverableCategory($deliverable), [self::CATEGORY_RESEARCH_DOCUMENT, self::CATEGORY_EVALUATION_SLIDES], true);
            $isProjectMember = $deliverable->project && $deliverable->project->students->contains(fn ($student) => (string) $student->id === (string) $user->id);

            if ($isSpecialEvaluationDocument) {
                $canUpload = (int) $user->perfil_id === 1 || ((int) $user->perfil_id === 3 && $isProjectMember);
            } else {
                $canUpload = $user->id === $deliverable->submitted_by || (int) $user->perfil_id === 1;
            }

            if (!$canUpload) {
                return response()->json(['error' => 'No puedes subir archivo a este entregable'], 403);
            }

            // Validar que haya archivo
            $maxFileSizeKb = ((int) SystemSetting::valueFor('max_file_size_mb', 50)) * 1024;
            $request->validate([
                'archivo' => 'required|file|max:' . $maxFileSizeKb,
            ]);

            // Guardar archivo
            $result = FileService::storeDeliverableFile(
                $request->file('archivo'),
                $deliverable->id,
                $user->id,
                $this->allowedExtensionsFor($deliverable)
            );

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 422);
            }

            // Actualizar entregable
            if ($deliverable->archivo_path) {
                FileService::deleteFile($deliverable->archivo_path);
            }

            $deliverable->update([
                'archivo_path' => $result['path'],
                'estado' => 'enviado',
                'submitted_by' => $deliverable->submitted_by ?: $user->id,
            ]);

            return response()->json([
                'message' => 'Archivo subido exitosamente',
                'deliverable' => $deliverable,
                'archivo_url' => FileService::getPublicUrl($result['path'])
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function shapeTeacherMatrixItem(Competencia $competencia, ?Deliverable $deliverable): array
    {
        $grade = $deliverable?->calificacion;
        $approved = $grade !== null && (float) $grade >= 70;

        return [
            'competencia' => [
                'id' => $competencia->id,
                'nombre' => $competencia->nombre,
                'fecha_inicio' => optional($competencia->fecha_inicio)->toDateString(),
                'fecha_fin' => optional($competencia->fecha_fin)->toDateString(),
            ],
            'asignatura' => [
                'id' => $competencia->asignatura?->id,
                'nombre' => $competencia->asignatura?->nombre,
                'clave' => $competencia->asignatura?->clave,
            ],
            'status' => $deliverable ? 'entregado' : 'faltante',
            'approved' => $approved,
            'calificacion' => $grade,
            'calificacion_efectiva' => $approved ? (float) $grade : 0,
            'deliverable' => $deliverable ? [
                'id' => $deliverable->id,
                'nombre' => $deliverable->nombre,
                'descripcion' => $deliverable->descripcion,
                'estado' => $deliverable->estado,
                'archivo_path' => $deliverable->archivo_path,
                'tipo_documento' => $deliverable->tipo_documento,
                'fecha_calificacion' => optional($deliverable->fecha_calificacion)->toDateTimeString(),
                'calificado_por' => $deliverable->calificadoPor,
            ] : null,
        ];
    }

    private function ensureEvaluationDeliverables(Project $project): void
    {
        Deliverable::firstOrCreate(
            $this->deliverableIdentity($project, self::CATEGORY_EVALUATION_SLIDES),
            array_merge($this->deliverableCategoryPayload(self::CATEGORY_EVALUATION_SLIDES), [
                'competencia_id' => null,
                'nombre' => 'Diapositivas de evaluacion',
                'descripcion' => 'Archivo de apoyo para la presentacion del proyecto en evaluaciones.',
                'tipo_documento' => 'presentacion',
                'estado' => 'pendiente',
                'activo' => true,
            ])
        );

        if ($this->requiresResearchDocument($project)) {
            Deliverable::firstOrCreate(
                $this->deliverableIdentity($project, self::CATEGORY_RESEARCH_DOCUMENT),
                array_merge($this->deliverableCategoryPayload(self::CATEGORY_RESEARCH_DOCUMENT), [
                    'competencia_id' => null,
                    'nombre' => 'Documento de investigacion',
                    'descripcion' => 'Documento adicional requerido para proyectos con Taller de Investigacion I o II.',
                    'tipo_documento' => 'documento',
                    'estado' => 'pendiente',
                    'activo' => true,
                ])
            );
        }
    }

    private function requiresResearchDocument(Project $project): bool
    {
        $project->loadMissing('asignaturas:id,nombre,clave');

        return $project->asignaturas->contains(function ($subject) {
            $name = $this->normalizeText((string) $subject->nombre);
            $key = $this->normalizeText((string) $subject->clave);

            return preg_match('/\btaller de investigacion (i|ii|1|2)\b/', $name) === 1
                || in_array($key, ['ac009', 'ac010'], true);
        });
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
            default => null,
        };
    }

    private function shapeEvaluationDocument(Deliverable $deliverable): array
    {
        return [
            'id' => $deliverable->id,
            'nombre' => $deliverable->nombre,
            'descripcion' => $deliverable->descripcion,
            'categoria' => $this->deliverableCategory($deliverable),
            'estado' => $deliverable->estado,
            'archivo_path' => $deliverable->archivo_path,
            'tipo_documento' => $deliverable->tipo_documento,
            'calificacion' => $deliverable->calificacion,
            'fecha_calificacion' => optional($deliverable->fecha_calificacion)->toDateTimeString(),
            'submitted_by' => $deliverable->submittedBy ? [
                'id' => $deliverable->submittedBy->id,
                'nombres' => $deliverable->submittedBy->nombres,
                'apa' => $deliverable->submittedBy->apa,
                'ama' => $deliverable->submittedBy->ama,
            ] : null,
            'calificado_por' => $deliverable->calificadoPor,
            'allowed_extensions' => $this->allowedExtensionsFor($deliverable),
        ];
    }

    private function evaluationDocumentsQuery($query)
    {
        if ($this->supportsDeliverableCategories()) {
            return $query
                ->whereIn('categoria', [self::CATEGORY_RESEARCH_DOCUMENT, self::CATEGORY_EVALUATION_SLIDES])
                ->orderBy('categoria');
        }

        return $query
            ->whereIn('nombre', ['Diapositivas de evaluacion', 'Documento de investigacion'])
            ->orderBy('nombre');
    }

    private function supportsDeliverableCategories(): bool
    {
        static $hasColumn = null;
        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('deliverables', 'categoria');
        }

        return $hasColumn;
    }

    private function deliverableIdentity(Project $project, string $category): array
    {
        if ($this->supportsDeliverableCategories()) {
            return ['project_id' => $project->id, 'categoria' => $category];
        }

        return [
            'project_id' => $project->id,
            'nombre' => $category === self::CATEGORY_EVALUATION_SLIDES ? 'Diapositivas de evaluacion' : 'Documento de investigacion',
        ];
    }

    private function deliverableCategoryPayload(string $category): array
    {
        return $this->supportsDeliverableCategories() ? ['categoria' => $category] : [];
    }

    private function deliverableCategory(Deliverable $deliverable): string
    {
        $category = $deliverable->getAttribute('categoria');
        if ($category) {
            return $category;
        }

        return match ($deliverable->nombre) {
            'Diapositivas de evaluacion' => self::CATEGORY_EVALUATION_SLIDES,
            'Documento de investigacion' => self::CATEGORY_RESEARCH_DOCUMENT,
            default => 'materia',
        };
    }
}
