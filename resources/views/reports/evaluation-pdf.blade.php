<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Evaluacion</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        h2 { font-size: 15px; margin: 22px 0 8px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px; vertical-align: top; }
        th { background: #e5eefc; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary td:first-child { width: 32%; font-weight: bold; background: #f9fafb; }
        .score { white-space: nowrap; }
        .chart { text-align: center; margin-top: 8px; }
        .chart img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; }
        .comment { border: 1px solid #d1d5db; padding: 8px; margin-bottom: 8px; }
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
    <table>
        <thead>
            <tr>
                <th>Criterio</th>
                @foreach ($teachers as $teacher)
                    <th>{{ trim(collect([$teacher->nombres, $teacher->apa])->filter()->join(' ')) ?: $teacher->id }}</th>
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
