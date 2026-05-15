<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTag;
use App\Models\RepositoryDocument;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RepositoryController extends Controller
{
    public function index(Request $request)
    {
        $query = RepositoryDocument::with(['tags', 'uploader'])
            ->where('activo', true);

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
        $documents = RepositoryDocument::whereHas('tags', function($q) use ($tagId) {
                                        $q->where('document_tags.id', $tagId);
                                    })
                                   ->where('activo', true)
                                   ->with(['tags', 'uploader'])
                                   ->paginate(12);
        return response()->json($documents);
    }

    public function show($id)
    {
        $document = RepositoryDocument::with(['tags', 'uploader'])->find($id);
        if (!$document || !$document->activo) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        return response()->json($document);
    }

    public function store(Request $request)
    {
        try {
            $maxFileSizeKb = ((int) SystemSetting::valueFor('max_file_size_mb', 50)) * 1024;
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'required|string|max:5000',
                'autores' => 'required|string|max:1000',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'integer|exists:document_tags,id',
                'archivo' => 'required|file|mimes:pdf,doc,docx,epub|max:' . $maxFileSizeKb,
            ]);

            $file = $request->file('archivo');
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, ['pdf', 'doc', 'docx', 'epub'], true)) {
                throw ValidationException::withMessages([
                    'archivo' => ['Solo se permiten archivos PDF, Word o EPUB.'],
                ]);
            }

            $fileName = 'repo_' . auth('api')->id() . '_' . time() . '_' . uniqid() . '.' . $extension;
            $path = Storage::disk('public')->putFileAs('repositorio', $file, $fileName);
            if (!$path) {
                return response()->json(['message' => 'No se pudo guardar el archivo.'], 500);
            }

            $document = RepositoryDocument::create([
                'nombre' => trim($validated['nombre']),
                'descripcion' => trim($validated['descripcion']),
                'autores' => trim($validated['autores']),
                'archivo_path' => $path,
                'archivo_tipo' => $extension,
                'uploaded_by' => auth('api')->id(),
                'activo' => true,
            ]);
            $document->tags()->sync($validated['tag_ids'] ?? []);

            return response()->json([
                'message' => 'Documento agregado al repositorio',
                'document' => $document->load(['tags', 'uploader']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function download($id)
    {
        $document = RepositoryDocument::find($id);
        if (!$document || !$document->activo || !$document->archivo_path) {
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
        if (!$document || !$document->activo || !$document->archivo_path) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if (!Storage::disk('public')->exists($document->archivo_path)) {
            return response()->json(['error' => 'El archivo no existe en el servidor'], 404);
        }

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'epub' => 'application/epub+zip',
        ];
        $type = strtolower($document->archivo_tipo ?: pathinfo($document->archivo_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('public')->path($document->archivo_path), [
            'Content-Type' => $mimeTypes[$type] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($document->archivo_path) . '"',
        ]);
    }
}
