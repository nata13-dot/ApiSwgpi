<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Support\Profiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Simulación de nivel: el administrador general revisa una carrera con los permisos de otro perfil.
 * El token es del propio administrador (nunca de otra persona), de sólo lectura, y lleva
 * simulated_profile; ResolveCareerContext lo aplica y EnsureUserIsActive bloquea toda escritura.
 * Las secciones personales (mis proyectos, mis entregas) salen vacías porque no pertenecen a nadie.
 */
class ViewAsController extends Controller
{
    public function start(Request $request)
    {
        $admin = auth('api')->user();
        abort_unless($admin?->isGeneralAdmin(), 403, 'Sólo el administrador general puede simular otro nivel.');
        abort_if((bool) JWTAuth::parseToken()->getPayload()->get('read_only'), 409, 'Vuelve a tu sesión antes de abrir otra simulación.');
        $validated = $request->validate([
            'carrera_id' => 'required|integer|exists:carreras,id',
            'perfil_id' => 'required|integer|'.Profiles::rule(),
        ]);

        $career = Career::query()->whereKey($validated['carrera_id'])->where('activa', true)->firstOrFail();
        $profile = (int) $validated['perfil_id'];
        $token = JWTAuth::claims([
            'remember' => false,
            'career_id' => $career->id,
            'career_role' => $profile,
            'view_as_by' => $admin->id,
            'simulated_profile' => $profile,
            'read_only' => true,
        ])->fromUser($admin);

        DB::table('bitacora_academica')->insert([
            'carrera_id' => $career->id, 'actor_id' => $admin->id, 'accion' => 'vista.simular_nivel', 'entidad' => 'perfil',
            'entidad_id' => (string) $profile, 'antes' => null,
            'despues' => json_encode(['perfil' => Profiles::label($profile), 'solo_lectura' => true]), 'creado_en' => now(),
        ]);

        return response()->json(['access_token' => $token, 'token_type' => 'bearer', 'profile' => Profiles::label($profile)]);
    }
}
