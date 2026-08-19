<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OperationalAlertService
{
    public function __construct(
        private readonly MulticareerIntegrityService $integrity,
        private readonly DatabaseBackupService $backups,
        private readonly ContinuityPolicyService $policy,
    ) {
    }

    public function scan(): array
    {
        $integrity = $this->integrity->report();
        $storage = $this->backups->storageStatus();
        $latestBackup = DB::table('respaldos_base_datos')
            ->whereNull('eliminado_en')
            ->where('estado', 'completado')
            ->orderByDesc('id')
            ->first();
        $policy = $this->policy->get();
        $stale = !$latestBackup || Carbon::parse($latestBackup->creado_en)
            ->lt(now()->subHours($policy['max_backup_age_hours']));
        $measurements = DB::table('mediciones_continuidad')->orderByDesc('id')->limit(2)->get();
        $currentMeasurement = $measurements->get(0);
        $previousMeasurement = $measurements->get(1);
        $targetReadiness = $policy['target_readiness'];
        $criticalReadiness = $policy['critical_readiness'];
        $readinessDelta = $currentMeasurement && $previousMeasurement
            ? (int) $currentMeasurement->indice_preparacion - (int) $previousMeasurement->indice_preparacion
            : 0;

        $this->sync(
            'multicareer-integrity',
            !$integrity['healthy'],
            'integridad_multicarrera',
            'critica',
            'Se detectaron cruces entre carreras',
            "{$integrity['violations']} relaciones o configuraciones requieren revisión.",
            ['violations' => $integrity['violations'], 'checks_passed' => $integrity['checks_passed']]
        );
        $this->sync(
            'backup-storage-integrity',
            $storage['missing'] > 0 || $storage['altered'] > 0,
            'almacenamiento_respaldos',
            'critica',
            'Hay respaldos faltantes o alterados',
            "{$storage['missing']} archivos faltantes y {$storage['altered']} con checksum incorrecto.",
            ['missing' => $storage['missing'], 'altered' => $storage['altered']]
        );
        $this->sync(
            'backup-not-recent',
            $stale,
            'vigencia_respaldos',
            'critica',
            'No existe un respaldo reciente',
            $latestBackup ? "La copia más reciente tiene más de {$policy['max_backup_age_hours']} horas." : 'No existe ninguna copia disponible.',
            ['latest_backup_at' => $latestBackup?->creado_en]
        );
        $this->sync(
            'backup-not-verified',
            $storage['verified'] === 0,
            'restauracion_respaldos',
            'advertencia',
            'No hay respaldos verificados como restaurables',
            'Ejecuta un simulacro de restauración para confirmar la recuperación.',
            ['verified' => $storage['verified']]
        );
        $this->sync(
            'continuity-below-target',
            $currentMeasurement && (int) $currentMeasurement->indice_preparacion < $targetReadiness,
            'continuidad_institucional',
            $currentMeasurement && (int) $currentMeasurement->indice_preparacion < $criticalReadiness ? 'critica' : 'advertencia',
            'El índice de continuidad está debajo del objetivo',
            $currentMeasurement
                ? "Índice actual: {$currentMeasurement->indice_preparacion}%. Objetivo: {$targetReadiness}%."
                : 'Aún no existen mediciones de continuidad.',
            ['current' => $currentMeasurement?->indice_preparacion, 'target' => $targetReadiness]
        );
        $this->sync(
            'continuity-degradation',
            $readinessDelta < 0,
            'tendencia_continuidad',
            'advertencia',
            'El índice de continuidad disminuyó',
            $currentMeasurement && $previousMeasurement
                ? "Cambió de {$previousMeasurement->indice_preparacion}% a {$currentMeasurement->indice_preparacion}%."
                : 'No hay suficientes mediciones para calcular la variación.',
            ['delta' => $readinessDelta]
        );

        return $this->summary();
    }

    public function acknowledge(int $id, string $actorId): array
    {
        $updated = DB::table('alertas_operativas')
            ->where('id', $id)
            ->where('estado', 'abierta')
            ->update([
                'estado' => 'atendida',
                'atendida_por' => $actorId,
                'atendida_en' => now(),
                'actualizada_en' => now(),
            ]);
        if (!$updated) {
            throw new \RuntimeException('La alerta no está abierta o no existe.');
        }

        return (array) DB::table('alertas_operativas')->find($id);
    }

    public function summary(): array
    {
        $alerts = DB::table('alertas_operativas as alert')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'alert.atendida_por')
            ->select([
                'alert.*',
                'actor.nombres as actor_nombres',
                'actor.apellido_paterno as actor_apellido',
            ])
            ->orderByRaw("FIELD(alert.estado, 'abierta', 'atendida', 'resuelta')")
            ->orderByRaw("FIELD(alert.severidad, 'critica', 'advertencia', 'informativa')")
            ->orderByDesc('alert.actualizada_en')
            ->limit(100)
            ->get();

        return [
            'open' => $alerts->where('estado', 'abierta')->count(),
            'acknowledged' => $alerts->where('estado', 'atendida')->count(),
            'critical' => $alerts->whereIn('estado', ['abierta', 'atendida'])->where('severidad', 'critica')->count(),
            'data' => $alerts,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function activeSummary(): array
    {
        $alerts = DB::table('alertas_operativas')
            ->whereIn('estado', ['abierta', 'atendida'])
            ->orderByRaw("FIELD(severidad, 'critica', 'advertencia', 'informativa')")
            ->orderByDesc('actualizada_en')
            ->limit(5)
            ->get([
                'id', 'severidad', 'estado', 'titulo', 'detalle',
                'detectada_en', 'actualizada_en',
            ]);

        return [
            'total' => DB::table('alertas_operativas')->whereIn('estado', ['abierta', 'atendida'])->count(),
            'open' => DB::table('alertas_operativas')->where('estado', 'abierta')->count(),
            'critical' => DB::table('alertas_operativas')
                ->whereIn('estado', ['abierta', 'atendida'])
                ->where('severidad', 'critica')
                ->count(),
            'data' => $alerts,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function sync(
        string $fingerprint,
        bool $active,
        string $type,
        string $severity,
        string $title,
        string $detail,
        array $data,
    ): void {
        $existing = DB::table('alertas_operativas')->where('huella', $fingerprint)->first();
        if ($active) {
            if (!$existing) {
                DB::table('alertas_operativas')->insert([
                    'huella' => $fingerprint,
                    'tipo' => $type,
                    'severidad' => $severity,
                    'estado' => 'abierta',
                    'titulo' => $title,
                    'detalle' => $detail,
                    'datos' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'detectada_en' => now(),
                    'actualizada_en' => now(),
                ]);
                return;
            }
            DB::table('alertas_operativas')->where('id', $existing->id)->update([
                'severidad' => $severity,
                'titulo' => $title,
                'detalle' => $detail,
                'datos' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'estado' => $existing->estado === 'resuelta' ? 'abierta' : $existing->estado,
                'resuelta_en' => null,
                'actualizada_en' => now(),
            ]);
            return;
        }

        if ($existing && $existing->estado !== 'resuelta') {
            DB::table('alertas_operativas')->where('id', $existing->id)->update([
                'estado' => 'resuelta',
                'resuelta_en' => now(),
                'actualizada_en' => now(),
            ]);
        }
    }
}
