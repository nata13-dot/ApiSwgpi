Hola {{ $name }},

@if ($denied)
{{ $team ? 'La presentación de tu equipo' : 'Tu presentación' }} del proyecto {{ $project }} fue negada en este periodo.

Motivo: {{ $reason }}

Si tienes dudas, acude con la coordinación o la jefatura de tu carrera.
@else
{{ $team ? 'La presentación de tu equipo' : 'Tu presentación' }} del proyecto {{ $project }} vuelve a estar permitida. Revisa tus evidencias en SGPI.
@endif

Correo automatico enviado por Sistema SGPI. No respondas a este mensaje.
