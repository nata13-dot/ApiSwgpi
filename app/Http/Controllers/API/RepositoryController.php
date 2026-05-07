<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\DocumentTag;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Deliverable::with(['project', 'tags', 'submittedBy'])
                            ->where('estado', '!=', 'pendiente')
                            ->orderBy('created_at', 'desc');

        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query->where(function($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%")
                  ->orWhereHas('project', function($pq) use ($term) {
                      $pq->where('title', 'like', "%{$term}%");
                  });
            });
        }

        return response()->json($query->paginate(12));
    }

    public function search(Request $request)
    {
        return $this->index($request);
    }

    public function byProject($projectId)
    {
        $deliverables = Deliverable::where('project_id', $projectId)
                                   ->where('estado', '!=', 'pendiente')
                                   ->with(['tags', 'submittedBy'])
                                   ->paginate(12);
        return response()->json($deliverables);
    }

    public function byTag($tagId)
    {
        $deliverables = Deliverable::whereHas('tags', function($q) use ($tagId) {
                                        $q->where('document_tags.id', $tagId);
                                    })
                                   ->where('estado', '!=', 'pendiente')
                                   ->with(['project', 'submittedBy'])
                                   ->paginate(12);
        return response()->json($deliverables);
    }

    public function show($id)
    {
        $deliverable = Deliverable::with(['project', 'tags', 'submittedBy'])->find($id);
        if (!$deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }
        return response()->json($deliverable);
    }
}
