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
            'nivel' => 'nullable|in:importante,edicion,rutina',
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
            ->when($validated['nivel'] ?? null, function ($q, $level) {
                if ($level === 'edicion') {
                    $q->where(fn ($w) => $w->whereNull('audit.accion')->orWhereNotIn('audit.accion', array_merge(\App\Support\AuditDescriber::actionsOf('importante'), \App\Support\AuditDescriber::actionsOf('rutina'))));
                } else {
                    $q->whereIn('audit.accion', \App\Support\AuditDescriber::actionsOf($level));
                }
            })
            ->orderByDesc('audit.id');

        $page = $query->paginate((int) ($validated['per_page'] ?? 25));
        $this->shapeRows($page->getCollection());

        return response()->json($page);
    }

    /**
     * Historial de una persona: lo que hizo (como actor) y lo que le hicieron (aprobarla, editarla, desactivarla,
     * cambiarla de grupo…). Acepta número de control o de empleado; sirve también para solicitudes rechazadas.
     */
    public function person(string $id)
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,20}$/', $id), 422, 'Identificador no válido.');
        $user = DB::table('usuarios')->where('id', $id)->first(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'activo']);
        $base = fn () => DB::table('auditoria_carreras as audit')
            ->leftJoin('carreras as career', 'career.id', '=', 'audit.carrera_id')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'audit.actor_id')
            ->select(['audit.id', 'audit.carrera_id', 'career.clave as carrera_clave', 'audit.actor_id', 'actor.nombres as actor_nombres',
                'actor.apellido_paterno as actor_apellido', 'audit.metodo', 'audit.ruta', 'audit.accion', 'audit.estado_http',
                'audit.direccion_ip', 'audit.agente_usuario', 'audit.detalle', 'audit.creado_en'])
            ->orderByDesc('audit.id')->limit(150);
        $did = $base()->where('audit.actor_id', $id)->get();
        // Lo que le hicieron: aparece en «afectados» (registros nuevos) o es el usuario de la ruta (registros anteriores).
        $userActions = array_map(fn ($a) => 'App\\Http\\Controllers\\API\\UserController@'.$a, ['update', 'destroy', 'toggleActive', 'setGeneralAdmin']);
        $received = $base()->where(function ($q) use ($id, $userActions) {
            $q->whereRaw("JSON_SEARCH(audit.detalle, 'one', ?, NULL, '$.afectados') IS NOT NULL", [$id])
                ->orWhere(fn ($old) => $old->whereIn('audit.accion', $userActions)->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(audit.detalle, '$.route_parameters.id')) = ?", [$id]));
        })->where(fn ($q) => $q->whereNull('audit.actor_id')->orWhere('audit.actor_id', '!=', $id))->get();
        $this->shapeRows($did);
        $this->shapeRows($received);
        $count = fn ($rows) => collect(array_keys(\App\Support\AuditDescriber::LEVELS))->mapWithKeys(fn ($l) => [$l => $rows->where('nivel', $l)->count()]);

        return response()->json(['data' => [
            'persona' => $user ? ['id' => $user->id, 'nombre' => trim("{$user->nombres} {$user->apellido_paterno} {$user->apellido_materno}"), 'activo' => (bool) $user->activo]
                : ['id' => $id, 'nombre' => null, 'activo' => null],
            'hizo' => $did->values(), 'recibio' => $received->values(),
            'resumen' => ['hizo' => $count($did), 'recibio' => $count($received)],
        ]]);
    }

    /** Descripción, nivel, detalles, personas afectadas (con nombre) y dispositivo de cada registro. */
    private function shapeRows($rows): void
    {
        $details = $rows->mapWithKeys(fn ($row) => [$row->id => json_decode((string) $row->detalle, true) ?: []]);
        $ids = $details->flatMap(fn ($d) => (array) ($d['afectados'] ?? []))->unique()->take(500)->values();
        $names = $ids->isEmpty() ? collect() : DB::table('usuarios')->whereIn('id', $ids)->get(['id', 'nombres', 'apellido_paterno'])
            ->mapWithKeys(fn ($u) => [(string) $u->id => trim("{$u->nombres} {$u->apellido_paterno}")]);
        $rows->transform(function ($row) use ($details, $names) {
            $detail = $details[$row->id] ?? [];
            $row->dispositivo = \App\Support\DeviceLabel::from($row->agente_usuario, $detail['cliente'] ?? null);
            // Registros anteriores a la descripción: se deduce de la acción y los parámetros guardados.
            $row->descripcion = $detail['descripcion'] ?? \App\Support\AuditDescriber::fromStored($row->accion, (string) $row->metodo, (string) $row->ruta, (array) ($detail['route_parameters'] ?? []), (int) $row->estado_http);
            $row->objetivo = $detail['objetivo'] ?? null;
            $row->detalles = $detail['detalles'] ?? [];
            $row->nivel = $detail['nivel'] ?? \App\Support\AuditDescriber::level($row->accion);
            $row->afectados = collect((array) ($detail['afectados'] ?? []))->take(20)->map(fn ($id) => ['id' => (string) $id, 'nombre' => $names[(string) $id] ?? null])->values();
            unset($row->detalle);

            return $row;
        });
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
