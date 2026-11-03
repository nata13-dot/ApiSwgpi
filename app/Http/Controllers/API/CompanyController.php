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
        $companies = $query->orderBy('nombre')->limit(50)->get();
        // RFC y datos de contacto sólo para quienes gestionan proyectos.
        if (!$request->user('api')->canManageProjects()) {
            $companies = $companies->map(fn (Empresa $company) => $company->only(['id', 'nombre', 'giro']))->values();
        }

        return response()->json($companies);
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
