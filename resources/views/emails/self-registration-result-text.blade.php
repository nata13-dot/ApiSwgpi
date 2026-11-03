Hola {{ $name }},

@if ($approved)
La jefatura de {{ $career }} aprobó tu solicitud. Ya puedes iniciar sesión con tu número de control {{ $controlNumber }} y la contraseña que elegiste.
@else
La jefatura de {{ $career }} no aprobó tu solicitud de cuenta.

Motivo: {{ $reason }}

Si el registro sigue abierto, puedes corregir tus datos y enviar una solicitud nueva.
@endif

Correo automatico enviado por Sistema SGPI. No respondas a este mensaje.
