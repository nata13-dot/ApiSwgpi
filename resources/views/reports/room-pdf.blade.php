@php
    $teacherName = fn ($teacher) => $roomTeacherLabels[(string) $teacher->id]
        ?? (($limitedReport ?? false) ? 'Docente' : trim(collect([$teacher->nombres, $teacher->apa, $teacher->ama])->filter()->join(' ')));
    $tally = collect($projectRows)->countBy(fn ($row) => $row['verdict']['tone']);
    $roomVerdict = \App\Support\RubricScale::verdict(count($projectRows) ? (float) $roomAverage : null);
@endphp
@extends('reports.layout', [
    'documentTitle' => 'Reporte de sala de evaluación',
    'documentSubtitle' => $room->nombre.($room->salon ? ' · Salón '.$room->salon : '').' · '.$room->semestre.'° semestre',
    'documentKind' => ($limitedReport ?? false) ? 'Versión para docentes' : 'Sala de evaluación',
    'landscape' => true,
])

@section('content')
    @if ($limitedReport ?? false)
        <div class="note">Versión con datos limitados: los evaluadores aparecen como «Docente 1, 2…» y sólo se incluyen sus comentarios generales. El reporte completo lo descargan la jefatura de carrera y la administración general.</div>
    @endif

    <table class="kpis"><tr>
        <td class="{{ $roomVerdict['tone'] }}"><span class="label">Promedio de la sala</span><span class="value">{{ $roomAverage }}%</span><span class="hint">Proyectos con calificaciones</span></td>
        <td><span class="label">Proyectos</span><span class="value">{{ count($projectRows) }}</span><span class="hint">En el orden de exposición</span></td>
        <td class="success"><span class="label">Aprobados</span><span class="value">{{ $tally['success'] ?? 0 }}</span><span class="hint">80 % o más</span></td>
        <td class="warning"><span class="label">Apenas cumplen</span><span class="value">{{ $tally['warning'] ?? 0 }}</span><span class="hint">70 a 79 %</span></td>
        <td class="danger"><span class="label">Reprobados</span><span class="value">{{ $tally['danger'] ?? 0 }}</span><span class="hint">Menos de 70 %</span></td>
    </tr></table>

    <h2>Datos generales</h2>
    <table class="summary">
        <tr><th>Fecha de evaluación</th><td>{{ optional($room->fecha_evaluacion)->format('d/m/Y H:i') ?? '—' }}</td></tr>
        <tr><th>Responsable</th><td>{{ $room->responsibleTeacher ? $teacherName($room->responsibleTeacher) : '—' }}</td></tr>
        <tr><th>Docentes</th><td>{{ $room->teachers->map($teacherName)->filter()->join(', ') ?: '—' }}</td></tr>
    </table>

    <h2>Proyectos evaluados</h2>
    <table class="data" style="table-layout: fixed">
        <thead>
            <tr>
                <th style="width: 5%" class="center">#</th>
                <th style="width: 33%">Proyecto</th>
                <th style="width: 28%">Equipo</th>
                <th style="width: 7%" class="center">Docentes</th>
                <th style="width: 13%">Promedio</th>
                <th style="width: 14%">Recomendación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projectRows as $project)
                <tr>
                    <td class="center">{{ $project['order'] ?: '—' }}</td>
                    <td><strong>{{ $project['project_title'] }}</strong></td>
                    <td>{{ $project['students'] ?: '—' }}</td>
                    <td class="center">{{ $project['teachers_count'] }}</td>
                    <td><strong class="text-{{ $project['verdict']['tone'] }}">{{ $project['average'] }}%</strong> <span class="badge {{ $project['verdict']['tone'] }}">{{ $project['verdict']['label'] }}</span></td>
                    <td>{{ $project['titulation_apt_result'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted center">No hay proyectos evaluados en esta sala.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Promedio por proyecto</h2>
    @include('reports.partials.bars', ['items' => $chartItems])

    @foreach ($evaluationReports as $index => $report)
        <div style="page-break-before: always">
            <h2>{{ $index + 1 }}. {{ $report['project']?->title ?? 'Proyecto sin título' }}</h2>
            <p>
                <strong class="text-{{ $report['verdict']['tone'] }}">{{ $report['globalAverage'] }}%</strong>
                <span class="badge {{ $report['verdict']['tone'] }}">{{ $report['verdict']['label'] }}</span>
                @if ($report['titulationAptSummary']['applies'])
                    <span class="muted"> · Recomendación (sin efecto de egreso): <strong>{{ $report['titulationAptSummary']['label'] }}</strong></span>
                @endif
            </p>
            <p class="muted">Equipo: {{ collect($report['studentLabels'] ?? [])->filter()->join(', ') ?: '—' }}</p>
            @include('reports.partials.matrix', ['matrix' => $report['matrix'], 'teachers' => $report['teachers'], 'teacherLabels' => $report['teacherLabels']])
            <h3>Comentarios</h3>
            @include('reports.partials.comments', ['comments' => $report['comments'], 'titulationAptSummary' => $report['titulationAptSummary']])
        </div>
    @endforeach
@endsection
