<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTag;
use App\Models\Project;
use App\Models\RepositoryDocument;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RepositoryController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'epub'];

    public function index(Request $request)
    {
        return $this->repositoryIndex($request, true);
    }

    public function adminIndex(Request $request)
    {
        $user = auth('api')->user();
        if (!$user || (int) $user->perfil_id !== 1) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return $this->repositoryIndex($request, false);
    }

    public function studentIndex(Request $request)
    {
        $user = auth('api')->user();
        if (!$user || (int) $user->perfil_id !== 3) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return $this->repositoryIndex($request, false, $user);
    }

    private function repositoryIndex(Request $request, bool $publicOnly, $studentUser = null)
    {
        $query = RepositoryDocument::with(['tags', 'uploader'])
            ->where('activo', true);

        if (!$this->repositoryDocumentsHas('visibility')) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 12,
                'total' => 0,
            ]);
        }

        if ($publicOnly) {
            $query->where('visibility', RepositoryDocument::VISIBILITY_PUBLIC);
        }

        if ($studentUser) {
            $query->where(function ($scope) use ($studentUser) {
                $scope->where('visibility', RepositoryDocument::VISIBILITY_PUBLIC)
                    ->orWhere('uploaded_by', $studentUser->id)
                    ->orWhereHas('project.students', fn ($studentQuery) => $studentQuery->where('users.id', $studentUser->id));
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
            $term = $request->buscar;
            $query->where(function($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%")
                  ->orWhere('autores', 'like', "%{$term}%");
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
        if (!$this->repositoryDocumentsHas('visibility')) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 12,
                'total' => 0,
            ]);
        }

        $documents = RepositoryDocument::whereHas('tags', function($q) use ($tagId) {
                                        $q->where('document_tags.id', $tagId);
                                   })
                                   ->where('activo', true)
                                   ->with(['tags', 'uploader'])
                                   ->where('visibility', RepositoryDocument::VISIBILITY_PUBLIC)
                                   ->paginate(12);
        return response()->json($documents);
    }

    public function show($id)
    {
        $document = RepositoryDocument::with(['tags', 'uploader'])->find($id);
        if (!$document || !$document->activo || !$this->canAccessDocument($document)) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        return response()->json($document);
    }

    public function evaluationDocuments()
    {
        if (!$this->repositoryVisibilityEnabled() || !$this->repositoryDocumentsHas('project_id')) {
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
                    ->where('document_category', RepositoryDocument::CATEGORY_EVALUATION_DOCUMENT)
                    ->with(['uploader:id,nombres,apa,ama', 'publisher:id,nombres,apa,ama'])
                    ->orderByDesc('created_at'),
            ])
            ->where('activo', true);

        if ((int) $user->perfil_id === 3) {
            $projectsQuery->whereHas('students', fn ($query) => $query->where('users.id', $user->id));
        } elseif (!in_array((int) $user->perfil_id, [1, 2], true)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $projects = $projectsQuery->orderBy('title')->get()
            ->filter(fn (Project $project) => $this->requiresResearchDocument($project))
            ->values();

        return response()->json([
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
                'puede_publicar' => (int) $user->perfil_id === 1,
                'documents' => $project->repositoryDocuments->map(fn (RepositoryDocument $document) => $this->shapeEvaluationRepositoryDocument($document))->values(),
            ])->values(),
        ]);
    }

    public function storeEvaluationDocument(Request $request)
    {
        try {
            if (!$this->repositoryVisibilityEnabled() || !$this->repositoryDocumentsHas('project_id')) {
                return response()->json(['error' => 'El repositorio privado requiere ejecutar las migraciones pendientes en la API.'], 503);
            }

            $user = auth('api')->user();
            if ((int) $user->perfil_id !== 3) {
                return response()->json(['error' => 'Solo estudiantes pueden subir documentos de evaluacion al repositorio.'], 403);
            }

            $validated = $request->validate([
                'project_id' => 'required|integer|exists:projects,id',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:5000',
                'autores' => 'nullable|string|max:1000',
                'archivo' => $this->fileValidationRule(true, ['pdf', 'doc', 'docx']),
            ]);

            $project = Project::with(['students:id,nombres,apa,ama', 'asignaturas:id,nombre,clave'])
                ->where('activo', true)
                ->find($validated['project_id']);

            if (!$project || !$project->students->contains(fn ($student) => (string) $student->id === (string) $user->id)) {
                return response()->json(['error' => 'No puedes subir documentos para este proyecto.'], 403);
            }

            if (!$this->requiresResearchDocument($project)) {
                return response()->json(['error' => 'Este proyecto no tiene cargada Taller de Investigacion I o II.'], 403);
            }

            [$path, $extension] = $this->storeRepositoryFile($request->file('archivo'));
            if (!$path) {
                return response()->json(['message' => 'No se pudo guardar el archivo.'], 500);
            }

            $document = RepositoryDocument::create([
                'project_id' => $project->id,
                'nombre' => trim($validated['nombre']),
                'descripcion' => trim((string) ($validated['descripcion'] ?? '')),
                'autores' => trim((string) ($validated['autores'] ?? '')) ?: $this->projectAuthors($project),
                'archivo_path' => $path,
                'archivo_tipo' => $extension,
                'document_category' => RepositoryDocument::CATEGORY_EVALUATION_DOCUMENT,
                'visibility' => RepositoryDocument::VISIBILITY_PRIVATE,
                'uploaded_by' => $user->id,
                'activo' => true,
            ]);

            return response()->json([
                'message' => 'Documento guardado en repositorio privado para revision.',
                'document' => $this->shapeEvaluationRepositoryDocument($document->load(['uploader', 'publisher'])),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function thesisDocuments()
    {
        if (!$this->repositoryVisibilityEnabled()) {
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
        } elseif (!in_array((int) $user->perfil_id, [1, 2], true)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $query->paginate(12),
        ]);
    }

    public function storeThesisDocument(Request $request)
    {
        try {
            if (!$this->repositoryVisibilityEnabled()) {
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

            [$path, $extension] = $this->storeRepositoryFile($request->file('archivo'));
            if (!$path) {
                return response()->json(['message' => 'No se pudo guardar el archivo.'], 500);
            }

            $category = $validated['tipo'] === 'residencias'
                ? RepositoryDocument::CATEGORY_THESIS_RESIDENCY
                : RepositoryDocument::CATEGORY_THESIS_GENERAL;

            $document = RepositoryDocument::create([
                'nombre' => trim($validated['nombre']),
                'descripcion' => trim((string) ($validated['descripcion'] ?? '')),
                'autores' => trim((string) ($validated['autores'] ?? '')) ?: trim("{$user->nombres} {$user->apa} {$user->ama}"),
                'archivo_path' => $path,
                'archivo_tipo' => $extension,
                'document_category' => $category,
                'visibility' => RepositoryDocument::VISIBILITY_PRIVATE,
                'uploaded_by' => $user->id,
                'activo' => true,
            ]);

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
            if (!$user || !in_array((int) $user->perfil_id, [1, 3], true)) {
                return response()->json(['error' => 'Solo administradores o estudiantes con proyecto asignado pueden subir documentos.'], 403);
            }

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'required|string|max:5000',
                'autores' => 'required|string|max:1000',
                'project_id' => 'nullable|integer|exists:projects,id',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:document_tags,id',
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
                    ->whereHas('students', fn ($query) => $query->where('users.id', $user->id))
                    ->find($validated['project_id']);

                if (!$project) {
                    return response()->json(['error' => 'No puedes subir documentos para un proyecto que no tienes asignado.'], 403);
                }
            } elseif (!empty($validated['project_id'])) {
                $project = Project::where('activo', true)->find($validated['project_id']);
            }

            $file = $request->file('archivo');
            [$path, $extension] = $this->storeRepositoryFile($file);
            if (!$path) {
                return response()->json(['message' => 'No se pudo guardar el archivo.'], 500);
            }

            $documentData = [
                'nombre' => trim($validated['nombre']),
                'descripcion' => trim($validated['descripcion']),
                'autores' => trim($validated['autores']),
                'project_id' => $project?->id,
                'archivo_path' => $path,
                'archivo_tipo' => $extension,
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

            $document = RepositoryDocument::create($documentData);
            $document->tags()->sync($validated['tag_ids'] ?? []);

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
            if (!$document || !$document->activo) {
                return response()->json(['error' => 'Documento no encontrado'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'required|string|max:5000',
                'autores' => 'required|string|max:1000',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:document_tags,id',
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
                [$path, $extension] = $this->storeRepositoryFile($request->file('archivo'));
                if (!$path) {
                    return response()->json(['message' => 'No se pudo guardar el archivo.'], 500);
                }

                if ($document->archivo_path && Storage::disk('public')->exists($document->archivo_path)) {
                    Storage::disk('public')->delete($document->archivo_path);
                }

                $updates['archivo_path'] = $path;
                $updates['archivo_tipo'] = $extension;
            }

            $document->update($updates);
            $document->tags()->sync($validated['tag_ids'] ?? []);

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
        if (!$document || !$document->activo) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if ($document->archivo_path && Storage::disk('public')->exists($document->archivo_path)) {
            Storage::disk('public')->delete($document->archivo_path);
        }

        $document->tags()->detach();
        $document->update(['activo' => false]);

        return response()->json(['message' => 'Documento eliminado']);
    }

    public function publish(Request $request, $id)
    {
        if (!$this->repositoryVisibilityEnabled()) {
            return response()->json(['error' => 'Publicar documentos requiere ejecutar las migraciones pendientes en la API.'], 503);
        }

        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 1) {
            return response()->json(['error' => 'Solo administradores pueden publicar documentos.'], 403);
        }

        $document = RepositoryDocument::where('activo', true)->find($id);
        if (!$document) {
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
        $document = RepositoryDocument::find($id);
        if (!$document || !$document->activo || !$document->archivo_path || !$this->canAccessDocument($document)) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if (!Storage::disk('public')->exists($document->archivo_path)) {
            return response()->json(['error' => 'El archivo no existe en el servidor'], 404);
        }

        return Storage::disk('public')->download($document->archivo_path);
    }

    public function view($id)
    {
        $document = RepositoryDocument::find($id);
        if (!$document || !$document->activo || !$document->archivo_path || !$this->canAccessDocument($document)) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if (!Storage::disk('public')->exists($document->archivo_path)) {
            return response()->json(['error' => 'El archivo no existe en el servidor'], 404);
        }

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'epub' => 'application/epub+zip',
        ];
        $type = strtolower($document->archivo_tipo ?: pathinfo($document->archivo_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('public')->path($document->archivo_path), [
            'Content-Type' => $mimeTypes[$type] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($document->archivo_path) . '"',
        ]);
    }

    private function fileValidationRule(bool $required, ?array $extensions = null): string
    {
        $maxFileSizeKb = ((int) SystemSetting::valueFor('max_file_size_mb', 50)) * 1024;
        $presence = $required ? 'required' : 'nullable';
        $extensions = $extensions ?: self::ALLOWED_EXTENSIONS;

        return $presence . '|file|mimes:' . implode(',', $extensions) . '|max:' . $maxFileSizeKb;
    }

    private function storeRepositoryFile($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'archivo' => ['Tipo de archivo no permitido. Permitidos: ' . strtoupper(implode(', ', self::ALLOWED_EXTENSIONS)) . '.'],
            ]);
        }

        $fileName = 'repo_' . auth('api')->id() . '_' . time() . '_' . uniqid() . '.' . $extension;
        $path = Storage::disk('public')->putFileAs('repositorio', $file, $fileName);

        return [$path, $extension];
    }

    private function canAccessDocument(RepositoryDocument $document): bool
    {
        if (!$this->repositoryVisibilityEnabled()) {
            return false;
        }

        if ($document->visibility === RepositoryDocument::VISIBILITY_PUBLIC) {
            return true;
        }

        $user = auth('api')->user();
        if (!$user) {
            return false;
        }

        if ((int) $user->perfil_id === 1) {
            return true;
        }

        if ((int) $user->perfil_id === 2) {
            return true;
        }

        if ((int) $user->perfil_id === 3 && $document->project_id) {
            return Project::where('id', $document->project_id)
                ->whereHas('students', fn ($query) => $query->where('users.id', $user->id))
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

    private function shapeEvaluationRepositoryDocument(RepositoryDocument $document): array
    {
        return [
            'id' => $document->id,
            'project_id' => $document->project_id,
            'nombre' => $document->nombre,
            'descripcion' => $document->descripcion,
            'autores' => $document->autores,
            'archivo_tipo' => $document->archivo_tipo,
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
            'allowed_extensions' => ['pdf', 'doc', 'docx'],
        ];
    }

    private function repositoryVisibilityEnabled(): bool
    {
        return $this->repositoryDocumentsHas('visibility') && $this->repositoryDocumentsHas('document_category');
    }

    private function repositoryDocumentsHas(string $column): bool
    {
        static $columns = [];

        if (!array_key_exists($column, $columns)) {
            $columns[$column] = Schema::hasColumn('repository_documents', $column);
        }

        return $columns[$column];
    }
}
