<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Evaluacion</title>
    <style>
        @page { margin: 18px 16px; size: letter landscape; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 15px 0 6px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
        th { background: #e5eefc; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary td:first-child { width: 32%; font-weight: bold; background: #f9fafb; }
        .matrix-table { table-layout: fixed; }
        .matrix-table th, .matrix-table td { font-size: 7.2px; line-height: 1.25; }
        .matrix-table th:first-child, .matrix-table td:first-child { width: 18%; font-weight: bold; }
        .matrix-table th:last-child, .matrix-table td:last-child { width: 8%; }
        .score { white-space: normal; }
        .chart { text-align: center; margin-top: 8px; }
        .chart img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; }
        .comment { border: 1px solid #d1d5db; padding: 6px; margin-bottom: 6px; page-break-inside: avoid; }
        .comment strong { display: block; margin-bottom: 4px; }
        .criterion-comment { margin: 4px 0 0 12px; }
    </style>
</head>
<body>
    <h1>Reporte de Evaluacion</h1>
    <div class="muted">{{ $project?->title ?? 'Proyecto sin titulo' }}</div>

    <h2>Datos Generales</h2>
    <table class="summary">
        <tbody>
            <tr><td>Proyecto</td><td>{{ $project?->title ?? '-' }}</td></tr>
            <tr><td>Equipo</td><td>{{ $students->map(fn ($student) => trim(collect([$student->nombres, $student->apa, $student->ama])->filter()->join(' ')))->filter()->join(', ') ?: '-' }}</td></tr>
            <tr><td>Semestre</td><td>{{ $evaluation->semestre }}</td></tr>
            <tr><td>Etapa</td><td>{{ ucfirst((string) $evaluation->etapa) }}</td></tr>
            <tr><td>Sala</td><td>{{ $evaluation->room?->nombre ?? $evaluation->sala ?? '-' }}</td></tr>
            <tr><td>Fecha de evaluacion</td><td>{{ optional($evaluation->fecha_exposicion)->format('d/m/Y H:i') ?? '-' }}</td></tr>
            <tr><td>Promedio global</td><td>{{ $globalAverage }}%</td></tr>
            @if ($titulationAptSummary['applies'])
                <tr>
                    <td>Apto para titulacion</td>
                    <td>{{ $titulationAptSummary['label'] }}</td>
                </tr>
            @endif
            <tr><td>Fecha de reporte</td><td>{{ $generatedAt->format('d/m/Y H:i') }}</td></tr>
        </tbody>
    </table>

    <h2>Tabla de Calificaciones por Criterio</h2>
    <table class="matrix-table">
        <thead>
            <tr>
                <th>Criterio</th>
                @foreach ($teachers as $teacher)
                    <th>{{ $teacherLabels[(string) $teacher->id] ?? (trim(collect([$teacher->nombres, $teacher->apa])->filter()->join(' ')) ?: $teacher->id) }}</th>
                @endforeach
                <th>Promedio</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($matrix as $row)
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

    <h2>Grafica de Barras</h2>
    <div class="chart">
        <img src="{{ $chartUrl }}" alt="Grafica de promedio por criterio">
    </div>

    <h2>Comentarios por Docente</h2>
    @forelse ($comments as $comment)
        <div class="comment">
            <strong>{{ $comment['teacher_name'] }}</strong>
            @if ($titulationAptSummary['applies'])
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
</body>
</html>
