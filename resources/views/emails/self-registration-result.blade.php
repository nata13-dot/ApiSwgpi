<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de cuenta SGPI</title>
</head>
<body style="margin:0; padding:0; background:#eef2f7; font-family:Arial, Helvetica, sans-serif; color:#172033;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #d9e2ef;">
                    <tr>
                        <td style="background:#1B396A; padding:26px 30px;">
                            <div style="font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:#c9d8ef;">Sistema SGPI</div>
                            <h1 style="margin:8px 0 0; color:#ffffff; font-size:24px; line-height:1.25;">{{ $approved ? 'Tu cuenta fue aprobada' : 'Tu solicitud fue rechazada' }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 14px; font-size:16px;">Hola {{ $name }},</p>
                            @if ($approved)
                                <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#42526b;">
                                    La jefatura de {{ $career }} aprobó tu solicitud. Ya puedes iniciar sesión con tu número de control <strong>{{ $controlNumber }}</strong> y la contraseña que elegiste.
                                </p>
                            @else
                                <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#42526b;">
                                    La jefatura de {{ $career }} no aprobó tu solicitud de cuenta.
                                </p>
                                <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#42526b;"><strong>Motivo:</strong> {{ $reason }}</p>
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#42526b;">
                                    Si el registro sigue abierto, puedes corregir tus datos y enviar una solicitud nueva.
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc; padding:18px 30px; border-top:1px solid #e5edf6;">
                            <p style="margin:0; font-size:12px; line-height:1.5; color:#68758a;">
                                Correo automatico enviado por Sistema SGPI. No respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
