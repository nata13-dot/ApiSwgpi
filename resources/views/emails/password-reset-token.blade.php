<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperacion de contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #1B396A;">Recuperacion de contraseña</h2>
    <p>Hola {{ $name }},</p>
    <p>Usa el siguiente token para continuar con el cambio de contraseña:</p>
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 6px; color: #1B396A;">{{ $token }}</p>
    <p>Este token vence en 15 minutos. Si no solicitaste este cambio, puedes ignorar este correo.</p>
</body>
</html>
