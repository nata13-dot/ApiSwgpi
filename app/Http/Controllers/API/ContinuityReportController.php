<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ContinuityReportService;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContinuityReportController extends Controller
{
    public function show(ContinuityReportService $reports)
    {
        return response()->json($reports->build());
    }

    public function pdf(Request $request, ContinuityReportService $reports)
    {
        abort_unless(app()->bound('dompdf.wrapper') || class_exists(\Dompdf\Dompdf::class), 501, 'El generador PDF no está disponible.');
        $data = $reports->build();
        $data['generated_by'] = trim(($request->user('api')->nombres ?? '').' '.($request->user('api')->apellido_paterno ?? ''));

        DB::table('auditoria_carreras')->insert([
            'carrera_id' => app(CareerContext::class)->careerId(),
            'actor_id' => $request->user('api')?->id,
            'metodo' => 'GET',
            'ruta' => '/api/admin/continuity-report.pdf',
            'accion' => self::class.'@pdf',
            'estado_http' => 200,
            'direccion_ip' => $request->ip(),
            'agente_usuario' => mb_substr((string) $request->userAgent(), 0, 255),
            'detalle' => json_encode(['readiness_score' => $data['readiness_score']], JSON_UNESCAPED_UNICODE),
            'creado_en' => now(),
        ]);

        $pdf = app('dompdf.wrapper');
        $pdf->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHTML(view('reports.continuity-pdf', $data)->render());

        return $pdf->download('continuidad_operativa_'.now()->format('Ymd_His').'.pdf');
    }

    public function history(Request $request, ContinuityReportService $reports)
    {
        return response()->json([
            'data' => $reports->history((int) $request->integer('limit', 30)),
            'trend' => $reports->trend(),
        ]);
    }

    public function store(Request $request, ContinuityReportService $reports)
    {
        return response()->json([
            'message' => 'Medición de continuidad almacenada.',
            'measurement' => $reports->store('manual', $request->user('api')?->id),
        ], 201);
    }
}
