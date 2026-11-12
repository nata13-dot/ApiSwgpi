<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las ventanas de autoregistro abiertas antes de que se crearan solos los grupos de 5° no tienen grupos para
 * alumnos nuevos, y sin ellos el inicio de sesión no muestra «Crear cuenta». Se crean los que falten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $windows = DB::table('configuraciones_carrera')->where('clave', 'self_registration_until')->get(['carrera_id', 'valor']);
        foreach ($windows as $window) {
            $until = json_decode((string) $window->valor, true)['data'] ?? null;
            if (!$until || Carbon::parse($until)->isPast()) {
                continue;
            }
            $period = DB::table('carrera_periodos as cp')->join('periodos_academicos as p', 'p.id', '=', 'cp.periodo_id')
                ->where('cp.carrera_id', $window->carrera_id)->whereIn('cp.estado', ['abierto', 'en_cierre'])->first(['p.id', 'p.fecha_inicio']);
            if ($period && \App\Support\NewStudentGroups::allowedIn($period, \App\Support\NewStudentGroups::semester((int) $window->carrera_id))) {
                \App\Support\NewStudentGroups::ensure((int) $window->carrera_id, (int) $period->id);
            }
        }
    }

    public function down(): void
    {
        // Los grupos pueden tener alumnos ya inscritos: no se borran.
    }
};
