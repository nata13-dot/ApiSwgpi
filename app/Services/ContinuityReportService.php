<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContinuityReportService
{
    public function __construct(
        private readonly MulticareerIntegrityService $integrity,
        private readonly DatabaseBackupService $backups,
        private readonly OperationalAlertService $alerts,
        private readonly ContinuityPolicyService $policy,
    ) {
    }

    public function build(): array
    {
        $integrity = $this->integrity->report();
        $storage = $this->backups->storageStatus();
        $alerts = $this->alerts->activeSummary();
        $latestBackup = DB::table('respaldos_base_datos')
            ->whereNull('eliminado_en')
            ->where('estado', 'completado')
            ->orderByDesc('id')
            ->first();
        $latestVerification = DB::table('verificaciones_restauracion')
            ->where('estado', 'correcto')
            ->orderByDesc('id')
            ->first();
        $policy = $this->policy->get();
        $recentBackup = $latestBackup
            && Carbon::parse($latestBackup->creado_en)->gte(now()->subHours($policy['max_backup_age_hours']));
        $controls = [
            ['name' => 'Integridad multicarrera', 'passed' => $integrity['healthy'], 'detail' => "{$integrity['checks_passed']}/{$integrity['checks_total']} verificaciones correctas"],
            ['name' => 'Almacenamiento de respaldos', 'passed' => $storage['healthy'], 'detail' => "{$storage['available']} disponibles, {$storage['altered']} alterados"],
            ['name' => 'Respaldo reciente', 'passed' => (bool) $recentBackup, 'detail' => $latestBackup?->creado_en ?: 'Sin respaldos'],
            ['name' => 'Restauración comprobada', 'passed' => $storage['verified'] > 0, 'detail' => "{$storage['verified']} copias verificadas"],
        ];
        $passed = collect($controls)->where('passed', true)->count();

        return [
            'generated_at' => now(),
            'readiness_score' => (int) round(($passed / count($controls)) * 100),
            'controls_passed' => $passed,
            'controls_total' => count($controls),
            'controls' => $controls,
            'integrity' => $integrity,
            'storage' => $storage,
            'alerts' => $alerts,
            'latest_backup' => $latestBackup,
            'latest_verification' => $latestVerification,
            'careers' => DB::table('carreras as career')
                ->leftJoin('usuario_carrera as membership', function ($join) {
                    $join->on('membership.carrera_id', '=', 'career.id')->where('membership.activo', true);
                })
                ->leftJoin('proyectos as project', function ($join) {
                    $join->on('project.carrera_id', '=', 'career.id')->where('project.activo', true);
                })
                ->select([
                    'career.id', 'career.clave', 'career.nombre', 'career.activa',
                    DB::raw('COUNT(DISTINCT membership.usuario_id) as usuarios'),
                    DB::raw('COUNT(DISTINCT project.id) as proyectos'),
                ])
                ->groupBy('career.id', 'career.clave', 'career.nombre', 'career.activa')
                ->orderBy('career.id')
                ->get(),
        ];
    }

    public function store(string $source = 'manual', ?string $actorId = null): array
    {
        $source = in_array($source, ['manual', 'programado', 'consola'], true) ? $source : 'consola';
        $report = $this->build();
        $id = DB::table('mediciones_continuidad')->insertGetId([
            'medido_por' => $actorId,
            'origen' => $source,
            'indice_preparacion' => $report['readiness_score'],
            'controles_correctos' => $report['controls_passed'],
            'controles_totales' => $report['controls_total'],
            'incidencias_integridad' => $report['integrity']['violations'],
            'respaldos_disponibles' => $report['storage']['available'],
            'respaldos_verificados' => $report['storage']['verified'],
            'alertas_activas' => $report['alerts']['total'],
            'alertas_criticas' => $report['alerts']['critical'],
            'instantanea' => json_encode([
                'controls' => $report['controls'],
                'storage' => $report['storage'],
                'alerts' => [
                    'total' => $report['alerts']['total'],
                    'critical' => $report['alerts']['critical'],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'creado_en' => now(),
        ]);

        return (array) DB::table('mediciones_continuidad')->find($id);
    }

    public function history(int $limit = 30)
    {
        return DB::table('mediciones_continuidad as measurement')
            ->leftJoin('usuarios as actor', 'actor.id', '=', 'measurement.medido_por')
            ->select([
                'measurement.id', 'measurement.medido_por', 'measurement.origen',
                'measurement.indice_preparacion', 'measurement.controles_correctos',
                'measurement.controles_totales', 'measurement.incidencias_integridad',
                'measurement.respaldos_disponibles', 'measurement.respaldos_verificados',
                'measurement.alertas_activas', 'measurement.alertas_criticas',
                'measurement.creado_en', 'actor.nombres as actor_nombres',
                'actor.apellido_paterno as actor_apellido',
            ])
            ->orderByDesc('measurement.id')
            ->limit(max(1, min(90, $limit)))
            ->get();
    }

    public function trend(): array
    {
        $measurements = DB::table('mediciones_continuidad')
            ->orderByDesc('id')
            ->limit(2)
            ->get(['id', 'indice_preparacion', 'creado_en']);
        $current = $measurements->get(0);
        $previous = $measurements->get(1);
        $delta = $current && $previous
            ? (int) $current->indice_preparacion - (int) $previous->indice_preparacion
            : null;

        return [
            'current' => $current ? (int) $current->indice_preparacion : null,
            'previous' => $previous ? (int) $previous->indice_preparacion : null,
            'delta' => $delta,
            'direction' => $delta === null || $delta === 0 ? 'stable' : ($delta > 0 ? 'up' : 'down'),
            'target' => $this->policy->get()['target_readiness'],
        ];
    }
}
