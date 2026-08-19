<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; color: #102a4c; font-size: 10px; }
    h1 { font-size: 22px; margin: 0 0 4px; color: #072f5f; }
    h2 { font-size: 13px; margin: 20px 0 8px; color: #072f5f; border-bottom: 1px solid #d8e1eb; padding-bottom: 5px; }
    .muted { color: #65758b; }
    .score { margin: 18px 0; padding: 16px; border-radius: 8px; background: {{ $readiness_score === 100 ? '#eaf7ee' : '#fff4df' }}; }
    .score strong { font-size: 28px; color: {{ $readiness_score === 100 ? '#168b37' : '#b26a00' }}; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th { text-align: left; background: #eef3f8; color: #183b66; }
    th, td { padding: 7px; border: 1px solid #dbe3ec; vertical-align: top; }
    .ok { color: #168b37; font-weight: bold; }
    .fail { color: #b42318; font-weight: bold; }
    .footer { margin-top: 20px; font-size: 8px; color: #718096; }
</style>
</head>
<body>
    <h1>Reporte institucional de continuidad</h1>
    <div class="muted">SGPI ITSSMT · Generado {{ $generated_at->format('d/m/Y H:i:s') }} por {{ $generated_by ?: 'Administrador general' }}</div>

    <div class="score">
        <strong>{{ $readiness_score }}%</strong><br>
        Índice de preparación · {{ $controls_passed }} de {{ $controls_total }} controles correctos
    </div>

    <h2>Controles de continuidad</h2>
    <table>
        <thead><tr><th>Control</th><th>Resultado</th><th>Evidencia</th></tr></thead>
        <tbody>
        @foreach($controls as $control)
            <tr>
                <td>{{ $control['name'] }}</td>
                <td class="{{ $control['passed'] ? 'ok' : 'fail' }}">{{ $control['passed'] ? 'CORRECTO' : 'REVISAR' }}</td>
                <td>{{ $control['detail'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Estado de recuperación</h2>
    <table>
        <tbody>
            <tr><th>Respaldos disponibles</th><td>{{ $storage['available'] }}</td><th>Copias restaurables</th><td>{{ $storage['verified'] }}</td></tr>
            <tr><th>Archivos faltantes</th><td>{{ $storage['missing'] }}</td><th>Archivos alterados</th><td>{{ $storage['altered'] }}</td></tr>
            <tr><th>Espacio utilizado</th><td>{{ number_format($storage['total_bytes'] / 1024, 1) }} KB</td><th>Espacio libre</th><td>{{ number_format(($storage['disk_free_bytes'] ?? 0) / 1073741824, 1) }} GB</td></tr>
            <tr><th>Último respaldo</th><td colspan="3">{{ $latest_backup?->creado_en ?? 'No disponible' }}</td></tr>
            <tr><th>Último simulacro</th><td colspan="3">{{ $latest_verification?->creado_en ?? 'No disponible' }}</td></tr>
        </tbody>
    </table>

    <h2>Resumen multicarrera</h2>
    <table>
        <thead><tr><th>Clave</th><th>Carrera</th><th>Estado</th><th>Usuarios</th><th>Proyectos</th></tr></thead>
        <tbody>
        @foreach($careers as $career)
            <tr>
                <td>{{ $career->clave }}</td><td>{{ $career->nombre }}</td>
                <td>{{ $career->activa ? 'Activa' : 'Inactiva' }}</td>
                <td>{{ $career->usuarios }}</td><td>{{ $career->proyectos }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Alertas operativas</h2>
    <p>{{ $alerts['total'] }} alertas activas · {{ $alerts['critical'] }} críticas · {{ $integrity['violations'] }} incidencias de integridad.</p>
    @if(count($alerts['data']))
        <table>
            <thead><tr><th>Severidad</th><th>Estado</th><th>Alerta</th></tr></thead>
            <tbody>
            @foreach($alerts['data'] as $alert)
                <tr><td>{{ strtoupper($alert->severidad) }}</td><td>{{ $alert->estado }}</td><td><strong>{{ $alert->titulo }}</strong><br>{{ $alert->detalle }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ok">No existen alertas operativas activas.</p>
    @endif

    <div class="footer">Documento generado automáticamente por SGPI. El reporte no contiene contraseñas, tokens ni rutas privadas de respaldo.</div>
</body>
</html>
