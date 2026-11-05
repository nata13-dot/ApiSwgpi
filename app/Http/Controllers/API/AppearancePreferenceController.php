<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Apariencia de la cuenta. Sin registro, cada front usa el tema claro y la letra del equipo. */
class AppearancePreferenceController extends Controller
{
    public const FONT_SCALES = [90, 100, 115, 130];

    public function show(): JsonResponse
    {
        $row = DB::table('preferencias_apariencia')->where('usuario_id', auth('api')->id())->first();

        return response()->json(['data' => $row ? ['theme' => $row->tema, 'font_scale' => (int) $row->escala_letra] : null]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'font_scale' => ['required', 'integer', Rule::in(self::FONT_SCALES)],
        ]);
        DB::table('preferencias_apariencia')->updateOrInsert(
            ['usuario_id' => (string) auth('api')->id()],
            ['tema' => $validated['theme'], 'escala_letra' => $validated['font_scale'], 'actualizado_en' => now()]
        );

        return response()->json(['message' => 'Apariencia guardada', 'data' => $validated]);
    }
}
