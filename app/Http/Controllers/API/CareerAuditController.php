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
            'q' => 'nullable|string|max:120',
        ]);

        $query = DB::table('auditoria_carreras as audit')
            ->leftJoin('carreras as career', 'career.id', '=', 'audit.carrera_id')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'audit.actor_id')
            ->select([
                'audit.id', 'audit.carrera_id', 'career.clave as carrera_clave',
                'career.nombre_corto as carrera_nombre', 'audit.actor_id',
                'actor.nombres as actor_nombres', 'actor.apellido_paterno as actor_apellido',
                'audit.metodo', 'audit.ruta', 'audit.accion', 'audit.estado_http',
                'audit.direccion_ip', 'audit.agente_usuario', 'audit.detalle', 'audit.creado_en',
            ])
            ->when($validated['career_id'] ?? null, fn ($q, $value) => $q->where('audit.carrera_id', $value))
            ->when($validated['actor_id'] ?? null, fn ($q, $value) => $q->where('audit.actor_id', $value))
            ->when($validated['method'] ?? null, fn ($q, $value) => $q->where('audit.metodo', $value))
            ->when($validated['status'] ?? null, fn ($q, $value) => $q->where('audit.estado_http', $value))
            ->when($validated['date_from'] ?? null, fn ($q, $value) => $q->whereDate('audit.creado_en', '>=', $value))
            ->when($validated['date_to'] ?? null, fn ($q, $value) => $q->whereDate('audit.creado_en', '<=', $value))
            // Texto libre: descripción, objetivo y detalles (por ejemplo, el nombre o número de control de un alumno), actor o ruta.
            ->when(trim((string) ($validated['q'] ?? '')) !== '', function ($q) use ($validated) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($validated['q'])).'%';
                $q->where(fn ($w) => $w->where('audit.detalle', 'like', $term)->orWhere('audit.ruta', 'like', $term)->orWhere('audit.actor_id', 'like', $term)
                    ->orWhereRaw("CONCAT_WS(' ', actor.nombres, actor.apellido_paterno) LIKE ?", [$term]));
            })
            ->orderByDesc('audit.id');

        $page = $query->paginate((int) ($validated['per_page'] ?? 25));
        $page->getCollection()->transform(function ($row) {
            $detail = json_decode((string) $row->detalle, true) ?: [];
            $row->dispositivo = \App\Support\DeviceLabel::from($row->agente_usuario, $detail['cliente'] ?? null);
            // Registros anteriores a la descripción: se deduce de la acción y los parámetros guardados.
            $row->descripcion = $detail['descripcion'] ?? \App\Support\AuditDescriber::fromStored($row->accion, (string) $row->metodo, (string) $row->ruta, (array) ($detail['route_parameters'] ?? []), (int) $row->estado_http);
            $row->objetivo = $detail['objetivo'] ?? null;
            $row->detalles = $detail['detalles'] ?? [];

            return $row;
        });

        return response()->json($page);
    }

    /** Retención: plazo, cuántos registros se depurarían hoy y las copias ya generadas. */
    public function retention(\App\Services\AuditRetentionService $retention)
    {
        return response()->json(['ready' => $retention::ready(), 'preview' => $retention->preview(), 'archives' => $retention->history()]);
    }

    /** Depurar ahora (lo mismo que corre cada noche): copia los registros vencidos junto a los respaldos y los borra. */
    public function prune(Request $request, \App\Services\AuditRetentionService $retention)
    {
        $result = $retention->run('manual', (string) $request->user('api')->id);

        return response()->json($result + ['preview' => $retention->preview(), 'archives' => $retention->history()]);
    }

    public function downloadArchive(int $archive, \App\Services\AuditRetentionService $retention)
    {
        [$path, $row] = $retention->pathFor($archive);

        return response()->download($path, $row->archivo, ['Content-Type' => 'application/gzip', 'X-Checksum-SHA256' => $row->sha256]);
    }

    /** Cómo ve el sistema a quien consulta: IP detectada y cabeceras del proxy, para comprobar que la auditoría es exacta. */
    public function ipCheck(Request $request)
    {
        return response()->json(\App\Support\ClientIp::diagnostics($request));
    }
}
