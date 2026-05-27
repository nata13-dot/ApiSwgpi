<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Sala</title>
    <style>
        @page { margin: 18px 16px; size: letter landscape; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8.8px; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 15px 0 6px; color: #1f2937; }
        h3 { font-size: 11px; margin: 14px 0 6px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
        th { background: #e5eefc; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary td:first-child { width: 30%; font-weight: bold; background: #f9fafb; }
        .projects-table { table-layout: fixed; }
        .projects-table th:nth-child(1), .projects-table td:nth-child(1) { width: 7%; }
        .projects-table th:nth-child(4), .projects-table td:nth-child(4),
        .projects-table th:nth-child(5), .projects-table td:nth-child(5),
        .projects-table th:nth-child(6), .projects-table td:nth-child(6),
        .projects-table th:nth-child(7), .projects-table td:nth-child(7) { width: 10%; }
        .matrix-table { table-layout: fixed; }
        .matrix-table th, .matrix-table td { font-size: 7px; line-height: 1.25; }
        .matrix-table th:first-child, .matrix-table td:first-child { width: 18%; font-weight: bold; }
        .matrix-table th:last-child, .matrix-table td:last-child { width: 8%; }
        .chart { text-align: center; margin-top: 8px; }
        .chart img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; }
        .project-block { page-break-inside: avoid; margin-top: 14px; }
        .comment { border: 1px solid #d1d5db; padding: 6px; margin-bottom: 6px; page-break-inside: avoid; }
        .comment strong { display: block; margin-bottom: 3px; }
        .criterion-comment { margin: 3px 0 0 10px; }
        .score { white-space: normal; }
    </style>
</head>
<body>
    <h1>Reporte de Sala de Evaluacion</h1>
    <div class="muted">{{ $room->nombre }} · {{ $room->salon ?: 'Sin salon' }}</div>

    <h2>Datos Generales</h2>
    <table class="summary">
        <tbody>
            <tr><td>Sala</td><td>{{ $room->nombre }}</td></tr>
            <tr><td>Salon</td><td>{{ $room->salon ?: '-' }}</td></tr>
            <tr><td>Semestre</td><td>{{ $room->semestre }}</td></tr>
            <tr><td>Fecha de evaluacion</td><td>{{ optional($room->fecha_evaluacion)->format('d/m/Y H:i') ?? '-' }}</td></tr>
            <tr><td>Responsable</td><td>{{ $room->responsibleTeacher ? ($roomTeacherLabels[(string) $room->responsibleTeacher->id] ?? trim(collect([$room->responsibleTeacher->nombres, $room->responsibleTeacher->apa, $room->responsibleTeacher->ama])->filter()->join(' '))) : '-' }}</td></tr>
            <tr><td>Docentes</td><td>{{ $room->teachers->map(fn ($teacher) => $roomTeacherLabels[(string) $teacher->id] ?? trim(collect([$teacher->nombres, $teacher->apa, $teacher->ama])->filter()->join(' ')))->filter()->join(', ') ?: '-' }}</td></tr>
            <tr><td>Proyectos evaluados</td><td>{{ count($projectRows) }}</td></tr>
            <tr><td>Promedio global de sala</td><td>{{ $roomAverage }}%</td></tr>
            <tr><td>Fecha de reporte</td><td>{{ $generatedAt->format('d/m/Y H:i') }}</td></tr>
        </tbody>
    </table>

    <h2>Proyectos Evaluados</h2>
    <table class="projects-table">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Proyecto</th>
                <th>Equipo</th>
                <th>Docentes</th>
                <th>Promedio</th>
                <th>Apto titulacion</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projectRows as $project)
                <tr>
                    <td>{{ $project['order'] ?: '-' }}</td>
                    <td>{{ $project['project_title'] }}</td>
                    <td>{{ $project['students'] ?: '-' }}</td>
                    <td>{{ $project['teachers_count'] }}</td>
                    <td><strong>{{ $project['average'] }}%</strong></td>
                    <td>{{ $project['titulation_apt_result'] }}</td>
                    <td>{{ $project['status'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No hay proyectos evaluados en esta sala.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Grafica de Promedio por Proyecto</h2>
    <div class="chart">
        <img src="{{ $chartUrl }}" alt="Grafica de promedio por proyecto">
    </div>

    <h2>Detalle por Proyecto</h2>
    @foreach ($evaluationReports as $report)
        <div class="project-block">
            <h3>{{ $report['project']?->title ?? 'Proyecto sin titulo' }} · {{ $report['globalAverage'] }}%</h3>
            @if ($report['titulationAptSummary']['applies'])
                <p class="muted">
                    Apto para titulacion: <strong>{{ $report['titulationAptSummary']['label'] }}</strong>
                </p>
            @endif
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th>Criterio</th>
                        @foreach ($report['teachers'] as $teacher)
                            <th>{{ $report['teacherLabels'][(string) $teacher->id] ?? (trim(collect([$teacher->nombres, $teacher->apa])->filter()->join(' ')) ?: $teacher->id) }}</th>
                        @endforeach
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['matrix'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @foreach ($row['teacher_scores'] as $score)
                                <td class="score">
                                    @if ($score['percentage'] === null)
                                        -
                                    @else
                                        {{ $score['value'] }}<br>
                                        <span class="muted">{{ $score['percentage'] }}% · {{ $score['level'] }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="score"><strong>{{ $row['average'] }}%</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h3>Comentarios</h3>
            @forelse ($report['comments'] as $comment)
                <div class="comment">
                    <strong>{{ $comment['teacher_name'] }}</strong>
                    @if ($report['titulationAptSummary']['applies'])
                        <div class="muted">
                            Apto para titulacion:
                            @if ($comment['apto_titulacion'] === null)
                                Sin respuesta
                            @else
                                {{ $comment['apto_titulacion'] ? 'Si' : 'No' }}
                            @endif
                        </div>
                    @endif
                    <div>{{ $comment['general_comment'] ?: 'Sin comentario general.' }}</div>
                    @foreach ($comment['criterion_comments'] as $criterionComment)
                        <div class="criterion-comment">
                            <span class="muted">{{ $criterionComment['criterion'] }}:</span>
                            {{ $criterionComment['comment'] }}
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="muted">Sin comentarios registrados.</p>
            @endforelse
        </div>
    @endforeach
</body>
</html>
