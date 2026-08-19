<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MulticareerIntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareerIntegrityController extends Controller
{
    public function index(MulticareerIntegrityService $integrity)
    {
        return response()->json($integrity->report());
    }

    public function run(Request $request, MulticareerIntegrityService $integrity)
    {
        $report = $integrity->report();
        $report['run_id'] = $integrity->store($report, 'manual', $request->user('api')?->id);

        return response()->json($report);
    }

    public function history()
    {
        return response()->json([
            'data' => DB::table('ejecuciones_integridad as run')
                ->leftJoin('usuarios as actor', 'actor.id', '=', 'run.ejecutado_por')
                ->select([
                    'run.id', 'run.origen', 'run.saludable', 'run.incidencias',
                    'run.verificaciones_correctas', 'run.verificaciones_totales',
                    'run.creado_en', 'run.ejecutado_por',
                    'actor.nombres as actor_nombres', 'actor.apellido_paterno as actor_apellido',
                ])
                ->orderByDesc('run.id')
                ->limit(30)
                ->get(),
        ]);
    }
}
