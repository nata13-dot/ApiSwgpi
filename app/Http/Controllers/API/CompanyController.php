<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Empresa::query()->withCount('projects');
        if (!$request->user('api')->canManageProjects()) $query->where('estado_validacion', 'aprobada');
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$term}%")->orWhere('rfc', 'like', "%{$term}%"));
        }
        if ($request->filled('status') && $request->user('api')->canManageProjects()) $query->where('estado_validacion', $request->status);
        return response()->json($query->orderBy('nombre')->limit(50)->get());
    }

    public function review(Request $request, Empresa $company)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['aprobada', 'rechazada'])],
            'comment' => 'nullable|string|max:1000',
        ]);
        $company->update([
            'estado_validacion' => $validated['status'], 'comentario_validacion' => $validated['comment'] ?? null,
            'validada_por' => $request->user('api')->id, 'validada_en' => now(),
        ]);
        return response()->json(['message' => 'Empresa revisada.', 'company' => $company->fresh()]);
    }
}
