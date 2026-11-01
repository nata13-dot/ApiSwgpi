@extends('reports.layout', [
    'documentTitle' => 'Reporte de evaluación',
    'documentSubtitle' => $project?->title ?? 'Proyecto sin título',
    'documentKind' => ($limitedReport ?? false) ? 'Versión para docentes' : 'Evaluación de proyecto',
    'landscape' => count($teachers) > 5,
])

@section('content')
    @if ($limitedReport ?? false)
        <div class="note">Versión con datos limitados: los evaluadores aparecen como «Docente 1, 2…» y sólo se incluyen sus comentarios generales. El reporte completo lo descargan la jefatura de carrera y la administración general.</div>
    @endif

    <table class="kpis"><tr>
        <td class="{{ $verdict['tone'] }}"><span class="label">Promedio global</span><span class="value">{{ $globalAverage }}%</span><span class="hint">Aprobado desde 80 %</span></td>
        <td class="{{ $verdict['tone'] }}"><span class="label">Resultado</span><span class="value">{{ $verdict['label'] }}</span><span class="hint">Apenas cumple de 70 a 79 %</span></td>
        <td><span class="label">Docentes evaluadores</span><span class="value">{{ count($teachers) }}</span><span class="hint">{{ count($matrix) }} criterios</span></td>
        @if ($titulationAptSummary['applies'])
            <td><span class="label">Recomendación</span><span class="value" style="font-size: 11px">{{ $titulationAptSummary['label'] }}</span><span class="hint">Sin efecto de egreso</span></td>
        @endif
    </tr></table>

    <h2>Datos generales</h2>
    <table class="summary">
        <tr><th>Proyecto</th><td>{{ $project?->title ?? '—' }}</td></tr>
        <tr><th>Equipo</th><td>{{ collect($studentLabels ?? [])->filter()->join(', ') ?: '—' }}</td></tr>
        <tr><th>Semestre y etapa</th><td>{{ $evaluation->semestre }}° semestre · {{ ucfirst((string) $evaluation->etapa) }}</td></tr>
        <tr><th>Sala</th><td>{{ $evaluation->room?->nombre ?? $evaluation->sala ?? '—' }}</td></tr>
        <tr><th>Fecha de evaluación</th><td>{{ optional($evaluation->fecha_exposicion)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    </table>

    <h2>Promedio por criterio</h2>
    @include('reports.partials.bars', ['items' => $chartItems])

    <h2>Calificaciones por criterio y docente</h2>
    @include('reports.partials.matrix')

    <h2>{{ ($limitedReport ?? false) ? 'Comentarios generales' : 'Comentarios de los docentes' }}</h2>
    @include('reports.partials.comments')
@endsection
