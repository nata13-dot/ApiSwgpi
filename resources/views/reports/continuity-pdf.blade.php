@php
    $bytes = function ($value) {
        $value = (float) ($value ?? 0);
        if ($value <= 0) return '—';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = min((int) floor(log($value, 1024)), count($units) - 1);
        return number_format($value / (1024 ** $unit), $unit ? 1 : 0).' '.$units[$unit];
    };
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i') : 'No disponible';
    $scoreTone = $readiness_score >= 100 ? 'success' : ($readiness_score >= 50 ? 'warning' : 'danger');
@endphp
@extends('reports.layout', [
    'documentTitle' => 'Reporte institucional de continuidad',
    'documentSubtitle' => 'Respaldos, capacidad de recuperación, integridad multicarrera y alertas operativas',
    'documentKind' => 'Continuidad operativa',
    'generatedAt' => $generated_at,
    'generatedBy' => $generated_by ?: 'Administración general',
])

@section('content')
    <table class="kpis"><tr>
        <td class="{{ $scoreTone }}"><span class="label">Índice de preparación</span><span class="value">{{ $readiness_score }}%</span><span class="hint">{{ $controls_passed }} de {{ $controls_total }} controles correctos</span></td>
        <td class="{{ $storage['healthy'] ? 'success' : 'danger' }}"><span class="label">Respaldos disponibles</span><span class="value">{{ $storage['available'] }}</span><span class="hint">{{ $storage['verified'] }} con restauración comprobada</span></td>
        <td class="{{ $alerts['critical'] ? 'danger' : ($alerts['total'] ? 'warning' : 'success') }}"><span class="label">Alertas activas</span><span class="value">{{ $alerts['total'] }}</span><span class="hint">{{ $alerts['critical'] }} críticas</span></td>
        <td class="{{ $integrity['violations'] ? 'danger' : 'success' }}"><span class="label">Incidencias de integridad</span><span class="value">{{ $integrity['violations'] }}</span><span class="hint">{{ $integrity['checks_passed'] }}/{{ $integrity['checks_total'] }} verificaciones</span></td>
    </tr></table>

    <h2>Controles de continuidad</h2>
    <table class="data">
        <thead><tr><th style="width: 34%">Control</th><th style="width: 14%">Resultado</th><th>Evidencia</th></tr></thead>
        <tbody>
        @foreach ($controls as $control)
            <tr>
                <td><strong>{{ $control['name'] }}</strong></td>
                <td><span class="badge {{ $control['passed'] ? 'success' : 'danger' }}">{{ $control['passed'] ? 'Correcto' : 'Revisar' }}</span></td>
                <td>{{ $control['detail'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Estado de recuperación</h2>
    <table class="summary">
        <tr><th>Último respaldo</th><td>{{ $date($latest_backup?->creado_en) }}@if ($latest_backup) · {{ $bytes($latest_backup->tamano_bytes) }}@endif</td></tr>
        <tr><th>Último simulacro de restauración</th><td>{{ $date($latest_verification?->creado_en) }}@if ($latest_verification) · {{ $latest_verification->tablas_encontradas }} tablas esenciales y {{ number_format($latest_verification->filas_verificadas) }} filas comprobadas @endif</td></tr>
        <tr><th>Archivos faltantes o alterados</th><td>{{ $storage['missing'] }} faltantes · {{ $storage['altered'] }} alterados</td></tr>
        <tr><th>Espacio de respaldos</th><td>{{ $bytes($storage['total_bytes']) }} usados · {{ $bytes($storage['disk_free_bytes']) }} libres en disco</td></tr>
        <tr><th>Retención</th><td>{{ $storage['retention_days'] }} días · {{ $storage['eligible_count'] }} copias elegibles para depurar</td></tr>
    </table>

    <h2>Resumen multicarrera</h2>
    <table class="data">
        <thead><tr><th style="width: 10%">Clave</th><th>Carrera</th><th style="width: 12%">Estado</th><th style="width: 12%" class="right">Usuarios</th><th style="width: 12%" class="right">Proyectos</th></tr></thead>
        <tbody>
        @foreach ($careers as $career)
            <tr>
                <td><strong>{{ $career->clave }}</strong></td>
                <td>{{ $career->nombre }}</td>
                <td><span class="badge {{ $career->activa ? 'success' : 'secondary' }}">{{ $career->activa ? 'Activa' : 'Inactiva' }}</span></td>
                <td class="right">{{ number_format($career->usuarios) }}</td>
                <td class="right">{{ number_format($career->proyectos) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Alertas operativas</h2>
    @if (count($alerts['data']))
        <table class="data">
            <thead><tr><th style="width: 13%">Severidad</th><th style="width: 13%">Estado</th><th>Alerta</th></tr></thead>
            <tbody>
            @foreach ($alerts['data'] as $alert)
                <tr>
                    <td><span class="badge {{ $alert->severidad === 'critica' ? 'danger' : ($alert->severidad === 'advertencia' ? 'warning' : 'secondary') }}">{{ ucfirst($alert->severidad) }}</span></td>
                    <td>{{ ucfirst($alert->estado) }}</td>
                    <td><strong>{{ $alert->titulo }}</strong><br><span class="muted">{{ $alert->detalle }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="text-success"><strong>No hay alertas operativas activas.</strong></p>
    @endif

    <p class="muted small" style="margin-top: 14px">Documento generado automáticamente por SGPI. No contiene contraseñas, tokens ni rutas privadas de los respaldos.</p>
@endsection
