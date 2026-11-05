<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EvaluationDocumentRelease;
use App\Models\Project;
use App\Models\RepositoryDocument;
use App\Models\SystemSetting;
use App\Services\ProtectedDownloadService;
use App\Services\RepositoryFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RepositoryController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'epub'];

    public function __construct(
        private readonly RepositoryFileService $files,
        private readonly ProtectedDownloadService $downloads
    ) {}

    public function index(Request $request)
    {
        return $this->repositoryIndex($request, true);
    }

    public function adminIndex(Request $request)
    {
        $user = auth('api')->user();
        if (! $user || ! $user->isAdmin()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return $this->repositoryIndex($request, false);
    }

    public function studentIndex(Request $request)
    {
        $user = auth('api')->user();
        if (! $user || (int) $user->perfil_id !== 3) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return $this->repositoryIndex($request, false, $user);
    }

    private function repositoryIndex(Request $request, bool $publicOnly, $studentUser = null)
    {
        $query = RepositoryDocument::with(['tags', 'uploader', 'latestVersion'])
            ->where('activo', true);

        if (! $this->repositoryDocumentsHas('visibility')) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 12,
                'total' => 0,
            ]);
        }

        if ($publicOnly) {
            $query->where('visibility', RepositoryDocument::VISIBILITY_PUBLIC)
                ->whereNotNull('published_at');
        }

        if ($studentUser) {
            $query->where(function ($scope) use ($studentUser) {
                $scope->where(fn ($public) => $public
                    ->where('visibility', RepositoryDocument::VISIBILITY_PUBLIC)
                    ->whereNotNull('published_at'))
                    ->orWhere('uploaded_by', $studentUser->id)
                    ->orWhereHas('project.students', fn ($studentQuery) => $studentQuery->where('usuarios.id', $studentUser->id));
            });
        }

        if ($request->filled('categoria') && $this->repositoryDocumentsHas('document_category')) {
            $categories = match ($request->query('categoria')) {
                'tesis' => [RepositoryDocument::CATEGORY_THESIS_GENERAL],
                'residencias' => [RepositoryDocument::CATEGORY_THESIS_RESIDENCY],
                'desarrollo' => [RepositoryDocument::CATEGORY_EVALUATION_DOCUMENT],
                'general' => [RepositoryDocument::CATEGORY_REPOSITORY],
                default => null,
            };
            if ($categories) {
                $query->whereIn('document_category', $categories);
            }
        }

        if ($request->filled('buscar')) {
            \App\Support\TextSearch::apply($query, $request->buscar, ['nombre', 'descripcion', 'autor_nombre'], function ($scope, $pattern) {
                $scope->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('etiquetas.nombre', 'like', $pattern));
            });
        }

        match ($request->query('ordenar', 'reciente')) {
            'antiguo' => $query->orderBy('created_at'),
            'nombre_asc' => $query->orderBy('nombre'),
            'nombre_desc' => $query->orderByDesc('nombre'),
            default => $query->orderByDesc('created_at'),
        };

        return response()->json($query->paginate(12));
    }

    public function search(Request $request)
    {
        return $this->index($request);
    }

    public function byProject($projectId)
    {
        return response()->json([
            'data' => [],
            'message' => 'El repositorio es independiente de los proyectos.',
        ]);
    }

    public function byTag($tagId)
    {
        if (! $this->repositoryDocumentsHas('visibility')) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 12,
                'total' => 0,
            ]);
        }

        $documents = RepositoryDocument::whereHas('tags', function ($q) use ($tagId) {
            $q->where('etiquetas.id', $tagId);
        })
            ->where('activo', true)
            ->with(['tags', 'uploader', 'latestVersion'])
            ->where('visibility', RepositoryDocument::VISIBILITY_PUBLIC)
            ->whereNotNull('published_at')
            ->paginate(12);

        return response()->json($documents);
    }

    public function show($id)
    {
        $document = RepositoryDocument::with(['tags', 'uploader', 'latestVersion'])->find($id);
        if (! $document || ! $document->activo || ! $this->canAccessDocument($document)) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        return response()->json($document);
    }

    public function evaluationDocuments()
    {
        if (! $this->repositoryVisibilityEnabled() || ! $this->repositoryDocumentsHas('project_id')) {
            return response()->json(['error' => 'El repositorio privado requiere ejecutar las migraciones pendientes en la API.'], 503);
        }

        $user = auth('api')->user();
        $projectsQuery = Project::select(['id', 'title', 'description', 'semestre', 'year', 'authors', 'subject_group_id'])
            ->with([
                'students:id,nombres,apa,ama,semestre,grupo',
                'advisors:id,nombres,apa,ama',
                'asignaturas:id,nombre,clave',
                'repositoryDocuments' => fn ($query) => $query
                    ->where('activo', true)
                    ->whereIn('document_category', [
                        RepositoryDocument::CATEGORY_EVALUATION_RELEASE,
                        RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION,
                        RepositoryDocument::CATEGORY_EVALUATION_REPORT,
                    ])
                    ->with([
                        'uploader:id,nombres,apa,ama',
                        'publisher:id,nombres,apa,ama',
                        'releaseStatuses',
                    ])
                    ->orderByDesc('created_at'),
            ])
            ->where('activo', true)
            ->whereHas('subjectGroup', fn ($groupQuery) => $groupQuery->whereIn('semestre', app(\App\Services\SemesterManagementService::class)->careerSemesters()));

        if ((int) $user->perfil_id === 3) {
            $projectsQuery->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id));
        } elseif ((int) $user->perfil_id === 2) {
            // Los docentes sólo consultan las entregas de los proyectos que asesoran.
            $projectsQuery->whereHas('advisors', fn ($query) => $query->where('usuarios.id', $user->id));
        } elseif (! $user->canManageProjects()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $projects = $projectsQuery->orderBy('title')->get()->withRelationshipAutoloading();
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $period = rescue(fn () => $workflow->period(), null, false);
        $requirements = app(\App\Services\EvaluationRequirementsService::class);
        // Etapa que presentará cada proyecto este periodo (incluye casos especiales autorizados).
        $semesterOf = fn (Project $project) => (int) (app(\App\Services\SemesterManagementService::class)->presentationSemester(
            $project->id, $period ? $workflow->projectSemester($project, $period->id) : (int) $project->semestre, $period
        ) ?: $project->semestre);

        return response()->json([
            'period' => $period?->only(['id', 'nombre']),
            'presentation_max_mb' => \App\Services\EvaluationRequirementsService::PRESENTATION_MAX_MB,
            'data' => $projects->map(fn (Project $project) => [
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
                    'semestre' => $student->semestre,
                    'grupo' => $student->grupo,
                ])->values(),
                'asignaturas' => $project->asignaturas->map(fn ($subject) => [
                    'id' => $subject->id,
                    'nombre' => $subject->nombre,
                    'clave' => $subject->clave,
                ])->values(),
                'puede_subir' => (int) $user->perfil_id === 3
                    && $project->students->contains(fn ($student) => (string) $student->id === (string) $user->id),
                'puede_revisar' => \App\Services\EvaluationRequirementsService::canReview($user, $project),
                'release_sheet' => $this->shapeEvaluationDelivery(
                    $project,
                    $project->repositoryDocuments->firstWhere(
                        'document_category',
                        RepositoryDocument::CATEGORY_EVALUATION_RELEASE
                    ),
                    'release_sheet'
                ),
                'presentation' => $this->shapeEvaluationDelivery(
                    $project,
                    $project->repositoryDocuments->firstWhere(
                        'document_category',
                        RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION
                    ),
                    'presentation'
                ),
                'report' => $this->shapeEvaluationDelivery(
                    $project,
                    $project->repositoryDocuments->firstWhere(
                        'document_category',
                        RepositoryDocument::CATEGORY_EVALUATION_REPORT
                    ),
                    'report'
                ),
                'evaluation_semester' => $semesterOf($project),
                'readiness' => $requirements->status($project, $semesterOf($project), $period),
            ])->values(),
        ]);
    }

    public function storeEvaluationDocument(Request $request)
    {
        try {
            if (! $this->repositoryVisibilityEnabled() || ! $this->repositoryDocumentsHas('project_id')) {
                return response()->json(['error' => 'El repositorio privado requiere ejecutar las migraciones pendientes en la API.'], 503);
            }

            $user = auth('api')->user();
            if ((int) $user->perfil_id !== 3) {
                return response()->json(['error' => 'Solo estudiantes pueden subir documentos de evaluacion al repositorio.'], 403);
            }

            $validated = $request->validate([
                'project_id' => 'required|integer|exists:proyectos,id',
                'document_type' => 'required|in:release_sheet,presentation,report',
                'nombre' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'autores' => 'nullable|string|max:1000',
                'archivo' => $this->fileValidationRule(
                    true,
                    $this->evaluationExtensions($request->input('document_type')),
                    $this->evaluationMaxMb($request->input('document_type'))
                ),
            ], [
                'archivo.max' => $request->input('document_type') === 'presentation'
                    ? 'La presentación no debe pasar de ' . \App\Services\EvaluationRequirementsService::PRESENTATION_MAX_MB . ' MB.'
                    : 'El archivo excede el tamaño máximo permitido.',
            ]);

            $project = Project::with(['students:id,nombres,apa,ama', 'asignaturas:id,nombre,clave'])
                ->where('activo', true)
                ->find($validated['project_id']);

            if (! $project || ! $project->students->contains(fn ($student) => (string) $student->id === (string) $user->id)) {
                return response()->json(['error' => 'No puedes subir documentos para este proyecto.'], 403);
            }

            $type = $validated['document_type'];
            if ($type === 'report') {
                $workflow = app(\App\Services\AcademicWorkflowService::class);
                $period = $workflow->period();
                $semester = (int) (app(\App\Services\SemesterManagementService::class)->presentationSemester($project->id, $period ? $workflow->projectSemester($project, $period->id) : null, $period) ?: $project->semestre);
                if (!\App\Services\EvaluationRequirementsService::requirements($semester)['report']) {
                    return response()->json(['errors' => ['document_type' => ['El documento de reporte se entrega a partir de 6° semestre.']]], 422);
                }
            }
            if ($type === 'presentation' && $this->presentationHasVideo($request->file('archivo')->getRealPath())) {
                return response()->json(['errors' => ['archivo' => ['La presentación trae video. Sube aquí la versión sin video y la que tiene video, en tu enlace de Drive.']]], 422);
            }

            $category = match ($type) {
                'release_sheet' => RepositoryDocument::CATEGORY_EVALUATION_RELEASE,
                'presentation' => RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION,
                default => RepositoryDocument::CATEGORY_EVALUATION_REPORT,
            };
            $defaultName = match ($type) {
                'release_sheet' => 'Hoja de liberacion',
                'presentation' => 'Presentacion',
                default => 'Documento de reporte',
            };

            $allowedExtensions = $this->evaluationExtensions($type);
            $result = $this->files->persist(
                $request->file('archivo'),
                $user,
                function () use ($category, $defaultName, $project, $user, $validated) {
                    $period = app(\App\Services\AcademicWorkflowService::class)->period();
                    $document = RepositoryDocument::query()
                        ->whereBetween('creado_en', [$period->fecha_inicio, $period->fecha_fin->endOfDay()])
                        ->where('project_id', $project->id)
                        ->where('document_category', $category)
                        ->where('activo', true)
                        ->lockForUpdate()
                        ->first();
                    $attributes = [
                        'project_id' => $project->id,
                        'nombre' => trim((string) ($validated['nombre'] ?? '')) ?: $defaultName,
                        'descripcion' => trim((string) ($validated['descripcion'] ?? '')),
                        'autores' => trim((string) ($validated['autores'] ?? '')) ?: $this->projectAuthors($project),
                        'document_category' => $category,
                        'visibility' => RepositoryDocument::VISIBILITY_PRIVATE,
                        'uploaded_by' => $user->id,
                        'activo' => true,
                    ];

                    if ($document) {
                        $document->update($attributes);

                        return $document;
                    }

                    $document = RepositoryDocument::create($attributes);
                    if ($category === RepositoryDocument::CATEGORY_EVALUATION_RELEASE) {
                        $document->releaseStatuses()->createMany(
                            $project->students->map(fn ($student) => [
                                'alumno_id' => $student->id,
                                'liberado' => false,
                            ])->all()
                        );
                    }

                    return $document;
                },
                $allowedExtensions,
                $this->evaluationMaxMb($type),
                "repository/career-{$project->carrera_id}/project-{$project->id}"
            );
            $document = $result['document'];
            $period = app(\App\Services\AcademicWorkflowService::class)->period();
            if ($period && in_array($type, ['presentation', 'report'], true)) {
                \App\Services\EvaluationRequirementsService::resetReview($project->id, $period->id, $type);
            }

            return response()->json([
                'message' => 'Entrega guardada para revision.',
                'document' => $this->shapeEvaluationDelivery(
                    $project,
                    $document->load(['uploader', 'publisher', 'releaseStatuses']),
                    $validated['document_type']
                ),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function reviewEvaluationRelease(Request $request, $id)
    {
        $user = auth('api')->user();
        $document = RepositoryDocument::with(['project.students', 'releaseStatuses'])
            ->where('activo', true)
            ->where('document_category', RepositoryDocument::CATEGORY_EVALUATION_RELEASE)
            ->find($id);
        if (! $document || ! $document->project) {
            return response()->json(['error' => 'Hoja de liberacion no encontrada.'], 404);
        }
        if (! \App\Services\EvaluationRequirementsService::canReview($user, $document->project)) {
            return response()->json(['error' => 'La liberación la deciden el administrador general, la jefatura, la asistencia o la coordinación de proyectos.'], 403);
        }

        $validated = $request->validate([
            'students' => 'required|array',
            'students.*.student_id' => 'required|string|distinct',
            'students.*.released' => 'required|boolean',
        ]);
        $projectStudentIds = $document->project->students->pluck('id')->map(fn ($id) => (string) $id);
        $submittedIds = collect($validated['students'])->pluck('student_id')->map(fn ($id) => (string) $id);
        if ($submittedIds->diff($projectStudentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'students' => ['La revision contiene alumnos que no pertenecen al proyecto.'],
            ]);
        }

        DB::transaction(function () use ($document, $validated, $user) {
            foreach ($validated['students'] as $student) {
                EvaluationDocumentRelease::updateOrCreate(
                    [
                        'documento_repositorio_id' => $document->id,
                        'alumno_id' => $student['student_id'],
                    ],
                    [
                        'liberado' => $student['released'],
                        'revisado_por' => $user->id,
                        'revisado_en' => now(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Liberacion actualizada.',
            'document' => $this->shapeEvaluationDelivery(
                $document->project,
                $document->fresh(['uploader', 'publisher', 'releaseStatuses']),
                'release_sheet'
            ),
        ]);
    }

    public function thesisDocuments()
    {
        if (! $this->repositoryVisibilityEnabled()) {
            return response()->json(['error' => 'El apartado de tesis y residencias requiere ejecutar las migraciones pendientes en la API.'], 503);
        }

        $user = auth('api')->user();
        $query = RepositoryDocument::with(['uploader:id,nombres,apa,ama,semestre,grupo', 'publisher:id,nombres,apa,ama'])
            ->where('activo', true)
            ->whereIn('document_category', [
                RepositoryDocument::CATEGORY_THESIS_GENERAL,
                RepositoryDocument::CATEGORY_THESIS_RESIDENCY,
            ])
            ->orderByDesc('created_at');

        if ((int) $user->perfil_id === 3) {
            $query->where('uploaded_by', $user->id);
        } elseif (! ($user->canManageProjects() || (int) $user->perfil_id === 2)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $query->paginate(12),
        ]);
    }

    public function storeThesisDocument(Request $request)
    {
        if ($request->input('tipo') === 'residencias') return response()->json(['message' => 'Registra los avances desde el expediente individual de Residencias. Los documentos anteriores siguen disponibles para consulta.'], 409);
        try {
            if (! $this->repositoryVisibilityEnabled()) {
                return response()->json(['error' => 'El apartado de tesis y residencias requiere ejecutar las migraciones pendientes en la API.'], 503);
            }

            $user = auth('api')->user();
            if ((int) $user->perfil_id !== 3) {
                return response()->json(['error' => 'Solo estudiantes pueden subir avances de tesis o residencias.'], 403);
            }

            if ((int) $user->semestre !== 9) {
                return response()->json(['error' => 'Solo estudiantes de 9no semestre pueden subir avances de tesis o residencias.'], 403);
            }

            $validated = $request->validate([
                'tipo' => 'required|in:tesis,residencias',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'autores' => 'nullable|string|max:1000',
                'archivo' => $this->fileValidationRule(true, ['pdf', 'doc', 'docx']),
            ], [], [
                'tipo' => 'tipo de documento',
                'nombre' => 'nombre del documento',
                'archivo' => 'archivo',
            ]);

            $category = $validated['tipo'] === 'residencias'
                ? RepositoryDocument::CATEGORY_THESIS_RESIDENCY
                : RepositoryDocument::CATEGORY_THESIS_GENERAL;
            $result = $this->files->persist(
                $request->file('archivo'),
                $user,
                fn () => RepositoryDocument::create([
                    'nombre' => trim($validated['nombre']),
                    'descripcion' => trim((string) ($validated['descripcion'] ?? '')),
                    'autores' => trim((string) ($validated['autores'] ?? '')) ?: trim("{$user->nombres} {$user->apa} {$user->ama}"),
                    'document_category' => $category,
                    'visibility' => RepositoryDocument::VISIBILITY_PRIVATE,
                    'uploaded_by' => $user->id,
                    'activo' => true,
                ]),
                ['pdf', 'doc', 'docx'],
                (int) SystemSetting::valueFor('max_file_size_mb', 50),
                'repository/thesis'
            );
            $document = $result['document'];

            return response()->json([
                'message' => 'Avance guardado en repositorio privado para revision.',
                'document' => $document->load(['uploader', 'publisher']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            if (! $user || ! ($user->canManageProjects() || (int) $user->perfil_id === 3)) {
                return response()->json(['error' => 'Solo administradores o estudiantes con proyecto asignado pueden subir documentos.'], 403);
            }

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'required|string|max:5000',
                'autores' => 'required|string|max:1000',
                'project_id' => 'nullable|integer|exists:proyectos,id',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:etiquetas,id',
                'visibility' => 'nullable|in:public,private',
                'archivo' => $this->fileValidationRule(true),
            ]);

            $project = null;
            if ((int) $user->perfil_id === 3) {
                if (empty($validated['project_id'])) {
                    return response()->json(['errors' => ['project_id' => ['Selecciona el proyecto al que pertenece el documento.']]], 422);
                }

                $project = Project::with('students:id,nombres,apa,ama')
                    ->where('activo', true)
                    ->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id))
                    ->find($validated['project_id']);

                if (! $project) {
                    return response()->json(['error' => 'No puedes subir documentos para un proyecto que no tienes asignado.'], 403);
                }
            } elseif (! empty($validated['project_id'])) {
                $project = Project::where('activo', true)->find($validated['project_id']);
            }

            $documentData = [
                'nombre' => trim($validated['nombre']),
                'descripcion' => trim($validated['descripcion']),
                'autores' => trim($validated['autores']),
                'project_id' => $project?->id,
                'uploaded_by' => $user->id,
                'activo' => true,
            ];

            if ($this->repositoryVisibilityEnabled()) {
                $visibility = (int) $user->perfil_id === 3
                    ? RepositoryDocument::VISIBILITY_PRIVATE
                    : ($validated['visibility'] ?? RepositoryDocument::VISIBILITY_PUBLIC);
                $documentData['document_category'] = $project
                    ? RepositoryDocument::CATEGORY_EVALUATION_DOCUMENT
                    : RepositoryDocument::CATEGORY_REPOSITORY;
                $documentData['visibility'] = $visibility;
                $documentData['published_at'] = $visibility === RepositoryDocument::VISIBILITY_PUBLIC ? now() : null;
                $documentData['published_by'] = $visibility === RepositoryDocument::VISIBILITY_PUBLIC ? $user->id : null;
            }

            $result = $this->files->persist(
                $request->file('archivo'),
                $user,
                function () use ($documentData, $validated) {
                    $document = RepositoryDocument::create($documentData);
                    $document->tags()->sync($validated['tag_ids'] ?? []);

                    return $document;
                },
                self::ALLOWED_EXTENSIONS,
                (int) SystemSetting::valueFor('max_file_size_mb', 50),
                'repository/general'
            );
            $document = $result['document'];

            return response()->json([
                'message' => 'Documento agregado al repositorio',
                'document' => $document->load(['tags', 'uploader']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $document = RepositoryDocument::find($id);
        abort_if($document && $document->document_category !== RepositoryDocument::CATEGORY_REPOSITORY, 409, 'La evidencia académica se gestiona desde su entrega o expediente; conserva su historial.');
            if (! $document || ! $document->activo) {
                return response()->json(['error' => 'Documento no encontrado'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'required|string|max:5000',
                'autores' => 'required|string|max:1000',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:etiquetas,id',
                'visibility' => 'nullable|in:public,private',
                'archivo' => $this->fileValidationRule(false),
            ]);

            $updates = [
                'nombre' => trim($validated['nombre']),
                'descripcion' => trim($validated['descripcion']),
                'autores' => trim($validated['autores']),
            ];

            if ($this->repositoryVisibilityEnabled() && isset($validated['visibility'])) {
                $updates['visibility'] = $validated['visibility'];
                $updates['published_at'] = $validated['visibility'] === RepositoryDocument::VISIBILITY_PUBLIC ? now() : null;
                $updates['published_by'] = $validated['visibility'] === RepositoryDocument::VISIBILITY_PUBLIC ? auth('api')->id() : null;
            }

            if ($request->hasFile('archivo')) {
                $result = $this->files->persist(
                    $request->file('archivo'),
                    auth('api')->user(),
                    function () use ($document, $updates, $validated) {
                        $locked = RepositoryDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                        $locked->update($updates);
                        $locked->tags()->sync($validated['tag_ids'] ?? []);

                        return $locked;
                    },
                    self::ALLOWED_EXTENSIONS,
                    (int) SystemSetting::valueFor('max_file_size_mb', 50),
                    'repository/general'
                );
                $document = $result['document'];
            } else {
                DB::transaction(function () use ($document, $updates, $validated): void {
                    $document->update($updates);
                    $document->tags()->sync($validated['tag_ids'] ?? []);
                });
            }

            return response()->json([
                'message' => 'Documento actualizado',
                'document' => $document->fresh()->load(['tags', 'uploader']),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $document = RepositoryDocument::find($id);
        abort_if($document && $document->document_category !== RepositoryDocument::CATEGORY_REPOSITORY, 409, 'La evidencia académica se gestiona desde su entrega o expediente; conserva su historial.');
        if (! $document || ! $document->activo) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $document->tags()->detach();
        $document->update(['activo' => false]);

        return response()->json(['message' => 'Documento eliminado']);
    }

    public function publish(Request $request, $id)
    {
        if (! $this->repositoryVisibilityEnabled()) {
            return response()->json(['error' => 'Publicar documentos requiere ejecutar las migraciones pendientes en la API.'], 503);
        }

        $user = auth('api')->user();
        if (! $user->isAdmin()) {
            return response()->json(['error' => 'Solo administradores pueden publicar documentos.'], 403);
        }

        $document = RepositoryDocument::where('activo', true)->find($id);
        if (! $document) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $makePublic = $request->boolean('public', true);
        $document->update([
            'visibility' => $makePublic ? RepositoryDocument::VISIBILITY_PUBLIC : RepositoryDocument::VISIBILITY_PRIVATE,
            'published_at' => $makePublic ? now() : null,
            'published_by' => $makePublic ? $user->id : null,
        ]);

        return response()->json([
            'message' => $makePublic ? 'Documento publicado en el repositorio.' : 'Documento marcado como privado.',
            'document' => $document->fresh()->load(['tags', 'uploader', 'publisher']),
        ]);
    }

    public function download($id)
    {
        $document = RepositoryDocument::with('latestVersion')->find($id);
        if (! $document || ! $document->activo || ! $document->latestVersion || ! $this->canAccessDocument($document)) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        return $this->downloads->download($document->latestVersion);
    }

    public function view($id)
    {
        $document = RepositoryDocument::with('latestVersion')->find($id);
        if (! $document || ! $document->activo || ! $document->latestVersion || ! $this->canAccessDocument($document)) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        return $this->downloads->download($document->latestVersion, null, true);
    }

    /** Hoja de liberación: PDF o Word. Presentación: sólo PPTX. Reporte: PDF o Word. */
    private function evaluationExtensions(?string $type): array
    {
        return $type === 'presentation' ? ['pptx'] : ['pdf', 'doc', 'docx'];
    }

    private function evaluationMaxMb(?string $type): int
    {
        return $type === 'presentation'
            ? \App\Services\EvaluationRequirementsService::PRESENTATION_MAX_MB
            : max(25, (int) SystemSetting::valueFor('max_file_size_mb', 50));
    }

    /** Un PPTX es un ZIP: el video incrustado vive en ppt/media/ con extensión de video. */
    private function presentationHasVideo(?string $path): bool
    {
        if (!$path || !class_exists(\ZipArchive::class)) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $hasVideo = false;
        for ($index = 0; $index < $zip->numFiles && !$hasVideo; $index++) {
            $name = strtolower((string) $zip->getNameIndex($index));
            $hasVideo = str_starts_with($name, 'ppt/media/') && preg_match('/\.(mp4|m4v|mov|avi|wmv|mkv|webm|mpg|mpeg|3gp|flv)$/', $name);
        }
        $zip->close();

        return $hasVideo;
    }

    /** Un encargado aprueba una entrega o la devuelve con observaciones para el equipo. */
    public function reviewEvaluationDelivery(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:proyectos,id',
            'delivery' => 'required|in:presentation,link,report',
            'status' => 'required|in:aprobada,con_observaciones',
            'comment' => 'nullable|required_if:status,con_observaciones|string|max:1000',
        ], ['comment.required_if' => 'Escribe qué debe corregir el equipo.']);
        $user = auth('api')->user();
        $project = Project::where('activo', true)->findOrFail($validated['project_id']);
        if (! \App\Services\EvaluationRequirementsService::canReview($user, $project)) {
            return response()->json(['error' => 'Las entregas las revisan el administrador general, la jefatura, la asistencia o la coordinación de proyectos.'], 403);
        }
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $period = $workflow->period();
        abort_unless($period, 409, 'La carrera no tiene un periodo abierto.');
        $semester = (int) (app(\App\Services\SemesterManagementService::class)->presentationSemester($project->id, $workflow->projectSemester($project, $period->id), $period) ?: $project->semestre);
        $status = app(\App\Services\EvaluationRequirementsService::class)->status($project, $semester, $period);
        $delivery = $status['deliveries'][$validated['delivery']] ?? null;
        if (! $delivery) {
            return response()->json(['errors' => ['delivery' => ['Esta entrega no se pide en la etapa del proyecto.']]], 422);
        }
        if ($delivery['state'] === 'faltante') {
            return response()->json(['errors' => ['delivery' => ['El equipo todavía no hace esta entrega.']]], 422);
        }
        DB::table('revisiones_entregas_evaluacion')->updateOrInsert(
            ['proyecto_id' => $project->id, 'periodo_id' => $period->id, 'entrega' => $validated['delivery']],
            ['estado' => $validated['status'], 'comentario' => $validated['status'] === 'aprobada' ? ($validated['comment'] ?? null) : $validated['comment'],
                'revisado_por' => $user->id, 'revisado_en' => now()]
        );
        $workflow->audit('evaluacion.entrega_revisada', 'proyecto', $project->id, $delivery, $validated);

        return response()->json(['message' => $validated['status'] === 'aprobada' ? 'Entrega aprobada.' : 'Observaciones enviadas al equipo.']);
    }

    /** Enlace de Drive del proyecto para el periodo (video y presentación con video). Desde 6°. */
    public function storeEvaluationLink(Request $request)
    {
        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 3) {
            return response()->json(['error' => 'Sólo los integrantes del proyecto registran el enlace.'], 403);
        }
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:proyectos,id',
            'url' => 'required|string|max:500',
        ]);
        if (!\App\Services\EvaluationRequirementsService::isDriveLink($validated['url'])) {
            return response()->json(['errors' => ['url' => ['Pega un enlace de Google Drive que empiece con https://drive.google.com o https://docs.google.com.']]], 422);
        }
        $project = Project::with('students:id')->where('activo', true)->find($validated['project_id']);
        if (!$project || !$project->students->contains(fn ($student) => (string) $student->id === (string) $user->id)) {
            return response()->json(['error' => 'No puedes registrar el enlace de este proyecto.'], 403);
        }
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $period = $workflow->period();
        abort_unless($period, 409, 'La carrera no tiene un periodo abierto.');
        $previous = DB::table('enlaces_evaluacion')->where(['proyecto_id' => $project->id, 'periodo_id' => $period->id])->value('url');
        if ($previous !== trim($validated['url'])) {
            \App\Services\EvaluationRequirementsService::resetReview($project->id, $period->id, 'link');
        }
        DB::table('enlaces_evaluacion')->updateOrInsert(
            ['proyecto_id' => $project->id, 'periodo_id' => $period->id],
            ['url' => trim($validated['url']), 'registrado_por' => $user->id, 'actualizado_en' => now()]
        );
        $workflow->audit('evaluacion.enlace', 'proyecto', $project->id, null, ['url' => trim($validated['url'])]);

        return response()->json(['message' => 'Enlace guardado.', 'url' => trim($validated['url'])]);
    }

    private function fileValidationRule(bool $required, ?array $extensions = null, ?int $maxMb = null): string
    {
        $maxFileSizeKb = ($maxMb ?? (int) SystemSetting::valueFor('max_file_size_mb', 50)) * 1024;
        $presence = $required ? 'required' : 'nullable';
        $extensions = $extensions ?: self::ALLOWED_EXTENSIONS;

        return $presence.'|file|max:'.$maxFileSizeKb;
    }

    private function canAccessDocument(RepositoryDocument $document): bool
    {
        if (! $this->repositoryVisibilityEnabled()) {
            return false;
        }

        if ($document->visibility === RepositoryDocument::VISIBILITY_PUBLIC && $document->published_at !== null) {
            return true;
        }

        $user = auth('api')->user();
        if (! $user) {
            return false;
        }

        if ($user->canManageProjects()) {
            return true;
        }

        // El docente consulta las entregas de los proyectos que asesora (y documentos sin proyecto, como tesis).
        if ((int) $user->perfil_id === 2) {
            return !$document->project_id || Project::where('id', $document->project_id)
                ->whereHas('advisors', fn ($query) => $query->where('usuarios.id', $user->id))->exists();
        }

        if ((int) $user->perfil_id === 3 && $document->project_id) {
            return Project::where('id', $document->project_id)
                ->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id))
                ->exists();
        }

        if ((int) $user->perfil_id === 3 && in_array($document->document_category, [RepositoryDocument::CATEGORY_THESIS_GENERAL, RepositoryDocument::CATEGORY_THESIS_RESIDENCY], true)) {
            return (string) $document->uploaded_by === (string) $user->id;
        }

        return false;
    }

    private function requiresResearchDocument(Project $project): bool
    {
        $project->loadMissing('asignaturas:id,nombre,clave');

        return $project->asignaturas->contains(function ($subject) {
            $name = $this->normalizeText((string) $subject->nombre);
            $key = $this->normalizeText((string) $subject->clave);

            return preg_match('/\btaller de investigacion( i| ii| 1| 2)?\b/', $name) === 1
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

    private function projectAuthors(Project $project): string
    {
        $names = $project->students
            ->map(fn ($student) => trim("{$student->nombres} {$student->apa} {$student->ama}"))
            ->filter()
            ->values();

        return $names->isNotEmpty() ? $names->join(', ') : (string) $project->authors;
    }

    private function shapeEvaluationDelivery(
        Project $project,
        ?RepositoryDocument $document,
        string $type
    ): array {
        $statuses = $document?->releaseStatuses?->keyBy(fn ($status) => (string) $status->alumno_id)
            ?? collect();

        return [
            'uploaded' => $document !== null,
            'document' => $document ? $this->shapeEvaluationRepositoryDocument($document) : null,
            'allowed_extensions' => $this->evaluationExtensions($type),
            'max_mb' => $this->evaluationMaxMb($type),
            'students' => $project->students->map(function ($student) use ($statuses) {
                $status = $statuses->get((string) $student->id);

                return [
                    'id' => $student->id,
                    'nombres' => $student->nombres,
                    'apa' => $student->apa,
                    'ama' => $student->ama,
                    'released' => (bool) ($status?->liberado ?? false),
                    'reviewed' => $status?->revisado_en !== null,
                ];
            })->values(),
        ];
    }

    private function shapeEvaluationRepositoryDocument(RepositoryDocument $document): array
    {
        return [
            'id' => $document->id,
            'project_id' => $document->project_id,
            'nombre' => $document->nombre,
            'descripcion' => $document->descripcion,
            'autores' => $document->autores,
            'archivo_tipo' => $document->archivo_tipo,
            'document_category' => $document->document_category,
            'visibility' => $document->visibility,
            'is_public' => $document->visibility === RepositoryDocument::VISIBILITY_PUBLIC,
            'created_at' => optional($document->created_at)->toDateTimeString(),
            'published_at' => optional($document->published_at)->toDateTimeString(),
            'uploaded_by' => $document->uploader ? [
                'id' => $document->uploader->id,
                'nombres' => $document->uploader->nombres,
                'apa' => $document->uploader->apa,
                'ama' => $document->uploader->ama,
            ] : null,
            'published_by' => $document->publisher ? [
                'id' => $document->publisher->id,
                'nombres' => $document->publisher->nombres,
                'apa' => $document->publisher->apa,
                'ama' => $document->publisher->ama,
            ] : null,
            'allowed_extensions' => $document->document_category === RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION
                ? ['pdf', 'ppt', 'pptx']
                : ['pdf', 'doc', 'docx'],
        ];
    }

    private function repositoryVisibilityEnabled(): bool
    {
        return $this->repositoryDocumentsHas('visibility') && $this->repositoryDocumentsHas('document_category');
    }

    private function repositoryDocumentsHas(string $column): bool
    {
        static $columns = [];
        $normalizedColumns = [
            'project_id' => 'proyecto_id',
            'document_category' => 'categoria',
            'visibility' => 'visibilidad',
            'published_at' => 'publicado_en',
            'published_by' => 'publicado_por',
            'uploaded_by' => 'subido_por',
            'created_at' => 'creado_en',
            'updated_at' => 'actualizado_en',
        ];
        $databaseColumn = $normalizedColumns[$column] ?? $column;

        if (! array_key_exists($column, $columns)) {
            $columns[$column] = \App\Support\SchemaCache::hasColumn('documentos', $databaseColumn);
        }

        return $columns[$column];
    }
}
