{{-- Calificaciones por criterio y docente. Cada celda muestra el puntaje y su porcentaje; el color indica el nivel. --}}
@php
    $tint = fn ($percentage) => $percentage === null ? '' : ($percentage >= 80 ? '#dcfce7' : ($percentage >= 70 ? '#fef3c7' : '#fee2e2'));
    $levels = collect($matrix)->flatMap(fn ($row) => $row['teacher_scores'])
        ->filter(fn ($score) => $score['percentage'] !== null && $score['level'])
        ->sortByDesc('percentage')->unique('value')
        ->map(fn ($score) => "{$score['value']} = {$score['level']}")->values();
    $teacherName = fn ($teacher) => $teacherLabels[(string) $teacher->id]
        ?? (($limitedReport ?? false) ? 'Docente' : (trim(collect([$teacher->nombres, $teacher->apa])->filter()->join(' ')) ?: $teacher->id));
    $count = max(1, count($teachers));
    $criterionWidth = $count > 8 ? 22 : 30;
@endphp
<table class="data matrix" style="table-layout: fixed">
    <thead>
        <tr>
            <th style="width: {{ $criterionWidth }}%">Criterio</th>
            @foreach ($teachers as $teacher)
                <th class="center" style="font-size: {{ $count > 8 ? 6.5 : 7.5 }}px">{{ $teacherName($teacher) }}</th>
            @endforeach
            <th class="center" style="width: 8%">Promedio</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($matrix as $row)
            <tr>
                <td style="font-size: {{ $count > 8 ? 7 : 8 }}px"><strong>{{ $row['label'] }}</strong></td>
                @foreach ($row['teacher_scores'] as $score)
                    <td class="center" style="background: {{ $tint($score['percentage']) }}; font-size: 7.5px">
                        @if ($score['percentage'] === null)
                            <span class="muted">—</span>
                        @else
                            <strong>{{ $score['value'] }}</strong><br><span class="muted small">{{ $score['percentage'] }}%</span>
                        @endif
                    </td>
                @endforeach
                @php $tone = \App\Support\RubricScale::verdict($row['average'] === null ? null : (float) $row['average'])['tone']; @endphp
                <td class="center"><strong class="text-{{ $tone }}">{{ $row['average'] }}%</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>
@if ($levels->isNotEmpty())
    <div class="legend">Escala: {{ $levels->join(' · ') }}. Fondo verde: 80 % o más; ámbar: 70 a 79 %; rojo: menos de 70 %.</div>
@endif
