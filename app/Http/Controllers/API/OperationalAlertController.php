<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\OperationalAlertService;
use Illuminate\Http\Request;

class OperationalAlertController extends Controller
{
    public function index(OperationalAlertService $alerts)
    {
        return response()->json($alerts->summary());
    }

    public function scan(OperationalAlertService $alerts)
    {
        return response()->json($alerts->scan());
    }

    public function summary(OperationalAlertService $alerts)
    {
        return response()->json($alerts->activeSummary());
    }

    public function acknowledge(Request $request, int $alert, OperationalAlertService $alerts)
    {
        return response()->json([
            'message' => 'Alerta marcada como atendida.',
            'alert' => $alerts->acknowledge($alert, (string) $request->user('api')->id),
        ]);
    }
}
