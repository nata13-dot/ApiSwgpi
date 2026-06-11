<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('configuraciones_sistema')
            ->where('clave', 'active_academic_period')
            ->value('valor');
        $decoded = is_string($setting) ? json_decode($setting, true) : $setting;
        $periodName = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        $periodId = $periodName
            ? DB::table('periodos_academicos')->where('nombre', $periodName)->value('id')
            : null;
        $periodId ??= DB::table('periodos_academicos')->orderByDesc('id')->value('id');

        DB::table('periodos_academicos')->update(['activo' => false]);
        if ($periodId) {
            DB::table('periodos_academicos')->where('id', $periodId)->update(['activo' => true]);
        }
    }

    public function down(): void
    {
        // No se reactivan periodos historicos al revertir una normalizacion.
    }
};
