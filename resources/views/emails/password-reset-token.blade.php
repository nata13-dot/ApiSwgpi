<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token de recuperacion</title>
</head>
<body style="margin:0; padding:0; background:#eef2f7; font-family:Arial, Helvetica, sans-serif; color:#172033;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #d9e2ef;">
                    <tr>
                        <td style="background:#1B396A; padding:26px 30px;">
                            <div style="font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:#c9d8ef;">Sistema SGPI</div>
                            <h1 style="margin:8px 0 0; color:#ffffff; font-size:24px; line-height:1.25;">Recuperacion de contrasena</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 14px; font-size:16px;">Hola {{ $name }},</p>
                            <p style="margin:0 0 22px; font-size:15px; line-height:1.6; color:#42526b;">
                                Recibimos una solicitud para cambiar la contrasena de tu cuenta. Usa el siguiente token en el sistema para continuar:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background:#f8fafc; border:1px solid #dce6f2; border-radius:10px; padding:24px 12px;">
                                        <div style="font-size:34px; line-height:1; font-weight:700; letter-spacing:8px; color:#1B396A;">{{ $token }}</div>
                                        <div style="margin-top:12px; font-size:12px; color:#68758a;">Este token vence en 15 minutos.</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#42526b;">
                                Si no solicitaste este cambio, puedes ignorar este correo. Tu contrasena actual seguira funcionando.
                            </p>
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#68758a;">
                                Por seguridad, no compartas este token con nadie.
                            </p>
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
