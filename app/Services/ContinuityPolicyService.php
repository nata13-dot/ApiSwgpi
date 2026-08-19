<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ContinuityPolicyService
{
    public function get(): array
    {
        $policy = DB::table('politica_continuidad')->where('id', 1)->first();

        return [
            'target_readiness' => (int) ($policy?->objetivo_preparacion ?? config('continuity.target_readiness', 100)),
            'critical_readiness' => (int) ($policy?->umbral_critico ?? config('continuity.critical_readiness', 75)),
            'max_backup_age_hours' => (int) ($policy?->antiguedad_maxima_respaldo_horas ?? config('continuity.max_backup_age_hours', 26)),
            'backup_retention_days' => (int) ($policy?->retencion_respaldos_dias ?? 30),
            'updated_by' => $policy?->actualizado_por,
            'updated_at' => $policy?->actualizado_en,
        ];
    }

    public function update(array $values, string $actorId): array
    {
        DB::table('politica_continuidad')->updateOrInsert(['id' => 1], [
            'objetivo_preparacion' => $values['target_readiness'],
            'umbral_critico' => $values['critical_readiness'],
            'antiguedad_maxima_respaldo_horas' => $values['max_backup_age_hours'],
            'retencion_respaldos_dias' => $values['backup_retention_days'],
            'actualizado_por' => $actorId,
            'actualizado_en' => now(),
        ]);

        return $this->get();
    }
}
