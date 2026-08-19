<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareerAuditController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'career_id' => 'nullable|integer|exists:carreras,id',
            'actor_id' => 'nullable|string|max:20',
            'method' => 'nullable|in:POST,PUT,PATCH,DELETE',
            'status' => 'nullable|integer|min:100|max:599',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $query = DB::table('auditoria_carreras as audit')
            ->leftJoin('carreras as career', 'career.id', '=', 'audit.carrera_id')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'audit.actor_id')
            ->select([
                'audit.id', 'audit.carrera_id', 'career.clave as carrera_clave',
                'career.nombre_corto as carrera_nombre', 'audit.actor_id',
                'actor.nombres as actor_nombres', 'actor.apellido_paterno as actor_apellido',
                'audit.metodo', 'audit.ruta', 'audit.accion', 'audit.estado_http',
                'audit.direccion_ip', 'audit.detalle', 'audit.creado_en',
            ])
            ->when($validated['career_id'] ?? null, fn ($q, $value) => $q->where('audit.carrera_id', $value))
            ->when($validated['actor_id'] ?? null, fn ($q, $value) => $q->where('audit.actor_id', $value))
            ->when($validated['method'] ?? null, fn ($q, $value) => $q->where('audit.metodo', $value))
            ->when($validated['status'] ?? null, fn ($q, $value) => $q->where('audit.estado_http', $value))
            ->when($validated['date_from'] ?? null, fn ($q, $value) => $q->whereDate('audit.creado_en', '>=', $value))
            ->when($validated['date_to'] ?? null, fn ($q, $value) => $q->whereDate('audit.creado_en', '<=', $value))
            ->orderByDesc('audit.id');

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 25)));
    }
}
