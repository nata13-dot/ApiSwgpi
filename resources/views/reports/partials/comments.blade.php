{{-- Comentarios de los docentes. Quienes no escribieron nada se agrupan en una línea en lugar de repetir "Sin comentario". --}}
@php
    $withText = collect($comments)->filter(fn ($comment) => filled($comment['general_comment']) || count($comment['criterion_comments']));
    $silent = collect($comments)->reject(fn ($comment) => filled($comment['general_comment']) || count($comment['criterion_comments']));
@endphp
@forelse ($withText as $comment)
    <div style="border-left: 3px solid #0f2f57; background: #f8fafc; padding: 6px 9px; margin-bottom: 6px; page-break-inside: avoid">
        <strong style="color: #0f2f57">{{ $comment['teacher_name'] }}</strong>
        @if ($titulationAptSummary['applies'])
            <span class="muted small"> · Recomendación: {{ $comment['apto_titulacion'] === null ? 'sin respuesta' : ($comment['apto_titulacion'] ? 'Apto' : 'No apto') }}</span>
        @endif
        @if (filled($comment['general_comment']))<div style="margin-top: 2px">{{ $comment['general_comment'] }}</div>@endif
        @foreach ($comment['criterion_comments'] as $criterionComment)
            <div style="margin: 2px 0 0 10px"><span class="muted">{{ $criterionComment['criterion'] }}:</span> {{ $criterionComment['comment'] }}</div>
        @endforeach
    </div>
@empty
    <p class="muted">No hay comentarios escritos.</p>
@endforelse
@if ($silent->isNotEmpty())
    <p class="muted small">Sin comentarios escritos: {{ $silent->map(fn ($comment) => $comment['teacher_name'].($titulationAptSummary['applies'] ? ' ('.($comment['apto_titulacion'] === null ? 'sin respuesta' : ($comment['apto_titulacion'] ? 'apto' : 'no apto')).')' : ''))->join(', ') }}.</p>
@endif
