<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El número de control es generación + clave de carrera + consecutivo: la regla de Sistemas deja de exigir
 * que empiece con 222 (generación 22 de la carrera 24) y guarda sólo la clave de la carrera.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isc = DB::table('carreras')->where('clave', 'ISC')->value('id');
        if (!$isc) {
            return;
        }
        $identity = ['carrera_id' => $isc, 'clave' => 'self_registration_rule'];
        $stored = json_decode((string) DB::table('configuraciones_carrera')->where($identity)->value('valor'), true)['data'] ?? [];
        $values = [
            'valor' => json_encode(['data' => ['career_code' => '24', 'domain' => $stored['domain'] ?? 'smartin.tecnm.mx']]),
            'tipo' => 'array',
            'descripcion' => 'Formato del número de control y del correo institucional para el autoregistro',
            'actualizada_en' => now(),
        ];

        DB::table('configuraciones_carrera')->updateOrInsert($identity, $values);
    }

    public function down(): void
    {
        // El formato anterior era incorrecto: no se restaura.
    }
};
