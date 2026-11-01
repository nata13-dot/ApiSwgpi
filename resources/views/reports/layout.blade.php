{{--
    Base común de los informes PDF (DomPDF): encabezado institucional, pie con paginación y estilos compartidos.
    Variables: $documentTitle, $documentSubtitle (opcional), $documentKind (etiqueta corta), $generatedAt,
    $landscape (bool). DomPDF no soporta flexbox ni grid: la maquetación usa tablas.
--}}
@php
    $logoPath = resource_path('images/itssmt.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $generatedAt = $generatedAt ?? now();
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $documentTitle }}</title>
<style>
    @page { size: letter {{ ($landscape ?? false) ? 'landscape' : 'portrait' }}; margin: 26px 30px 42px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 9px; line-height: 1.4; }
    h2 { font-size: 11.5px; color: #0f2f57; margin: 16px 0 7px; padding-bottom: 4px; border-bottom: 1.5px solid #0f2f57; page-break-after: avoid; }
    h3 { font-size: 10px; color: #0f2f57; margin: 12px 0 6px; page-break-after: avoid; }
    p { margin: 0 0 6px; }
    .muted { color: #64748b; }
    .small { font-size: 7.5px; }
    .nowrap { white-space: nowrap; }
    .right { text-align: right; }
    .center { text-align: center; }

    /* Encabezado institucional (sólo primera página) */
    .doc-header { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .doc-header td { border: 0; padding: 0; vertical-align: middle; }
    .doc-header .logo { width: 46px; }
    .doc-header .logo img { width: 40px; height: 40px; }
    .doc-header .institution strong { display: block; font-size: 10px; color: #0f2f57; }
    .doc-header .institution span { font-size: 8px; color: #64748b; }
    .doc-header .meta { text-align: right; font-size: 8px; color: #64748b; }
    .doc-header .meta strong { display: block; font-size: 8.5px; color: #0f2f57; text-transform: uppercase; letter-spacing: .6px; }
    .doc-title { background: #0f2f57; color: #fff; padding: 10px 14px; margin-bottom: 12px; }
    .doc-title h1 { font-size: 16px; margin: 0; color: #fff; }
    .doc-title div { font-size: 9px; color: #cbd5e1; margin-top: 2px; }

    /* Pie con paginación en todas las páginas */
    /* El número de página lo dibuja App\Support\PdfReport a la derecha de esta línea. */
    .doc-footer { position: fixed; bottom: -28px; left: 0; right: 0; height: 18px; border-top: 1px solid #cbd5e1; padding-top: 4px; font-size: 7.5px; color: #64748b; }

    /* Indicadores */
    .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 10px; }
    .kpis td { background: #f1f5f9; border: 1px solid #e2e8f0; border-left: 3px solid #0f2f57; padding: 7px 9px; vertical-align: top; }
    .kpis .label { display: block; font-size: 7.5px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .kpis .value { display: block; font-size: 15px; font-weight: bold; color: #0f2f57; margin-top: 2px; }
    .kpis .hint { display: block; font-size: 7.5px; color: #64748b; }
    .kpis td.success { border-left-color: #15803d; } .kpis td.success .value { color: #15803d; }
    .kpis td.warning { border-left-color: #b45309; } .kpis td.warning .value { color: #b45309; }
    .kpis td.danger { border-left-color: #b91c1c; } .kpis td.danger .value { color: #b91c1c; }

    /* Tablas */
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.data th { background: #0f2f57; color: #fff; font-weight: bold; text-align: left; padding: 5px 6px; font-size: 8px; }
    table.data td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    table.data tbody tr:nth-child(even) td { background: #f8fafc; }
    table.data tr { page-break-inside: avoid; }
    table.summary { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.summary th { width: 26%; text-align: left; background: #f1f5f9; color: #334155; padding: 5px 7px; border-bottom: 1px solid #e2e8f0; font-size: 8.5px; }
    table.summary td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; }

    /* Etiquetas de estado */
    .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: bold; white-space: nowrap; }
    .badge.success { background: #dcfce7; color: #166534; }
    .badge.warning { background: #fef3c7; color: #92400e; }
    .badge.danger { background: #fee2e2; color: #991b1b; }
    .badge.secondary { background: #e2e8f0; color: #475569; }
    .text-success { color: #15803d; } .text-warning { color: #b45309; } .text-danger { color: #b91c1c; } .text-secondary { color: #64748b; }

    /* Gráfica de barras horizontal */
    table.bars { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    table.bars td { padding: 3px 4px; vertical-align: middle; border: 0; }
    table.bars tr { page-break-inside: avoid; }
    table.bars .bar-label { width: 38%; font-size: 8px; }
    table.bars .bar-value { width: 9%; text-align: right; font-weight: bold; white-space: nowrap; }
    .bar-track { background: #e2e8f0; height: 11px; width: 100%; position: relative; }
    .bar-fill { height: 11px; }
    .bar-fill.success { background: #16a34a; } .bar-fill.warning { background: #d97706; } .bar-fill.danger { background: #dc2626; } .bar-fill.secondary { background: #94a3b8; }
    .bar-scale { font-size: 7px; color: #94a3b8; }

    .note { border-left: 3px solid #d97706; background: #fff7ed; color: #7c2d12; padding: 6px 9px; margin: 0 0 10px; }
    .legend { font-size: 7.5px; color: #64748b; margin: 2px 0 8px; }
    .legend span { margin-right: 10px; }
    .swatch { display: inline-block; width: 8px; height: 8px; margin-right: 3px; }
    @yield('styles')
</style>
</head>
<body>
    <div class="doc-footer">SGPI · ITSSMT — {{ $documentTitle }} · Generado {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <table class="doc-header"><tr>
        @if ($logo)<td class="logo"><img src="{{ $logo }}" alt="ITSSMT"></td>@endif
        <td class="institution">
            <strong>Instituto Tecnológico Superior de San Martín Texmelucan</strong>
            <span>Sistema de Gestión de Proyectos Integradores (SGPI)</span>
        </td>
        <td class="meta">
            <strong>{{ $documentKind ?? 'Informe' }}</strong>
            {{ $generatedAt->format('d/m/Y H:i') }}@isset($generatedBy) · {{ $generatedBy }}@endisset
        </td>
    </tr></table>

    <div class="doc-title">
        <h1>{{ $documentTitle }}</h1>
        @if (!empty($documentSubtitle))<div>{{ $documentSubtitle }}</div>@endif
    </div>

    @yield('content')
</body>
</html>
