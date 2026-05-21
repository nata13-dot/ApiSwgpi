# SGPI API

API Laravel para el Sistema de Gestion de Proyectos Integradores.

## Recuperacion de contrasenas

El flujo publico de recuperacion no pide JWT porque el usuario todavia no ha iniciado sesion:

- `POST /api/auth/password/request-token`: valida No. de control/empleado y correo, genera un token de 6 digitos y lo envia por correo.
- `POST /api/auth/password/verify-token`: confirma que el token siga vigente.
- `POST /api/auth/password/reset`: cambia la contrasena y devuelve un JWT nuevo para iniciar sesion automaticamente.

JWT se usa para autenticar al usuario despues del cambio de contrasena y para todas las rutas protegidas. En Railway debes configurar `JWT_SECRET`; si falta, el login y el cierre del flujo de recuperacion pueden fallar al generar el token.

## Variables en Railway

Configura estas variables en el servicio de la API:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-api.up.railway.app
FRONTEND_URLS=https://tu-frontend.up.railway.app

JWT_SECRET=pega_aqui_el_valor_generado
JWT_TTL=60
JWT_REFRESH_TTL=20160

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_correo@gmail.com
MAIL_PASSWORD=tu_app_password_de_gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_correo@gmail.com
MAIL_FROM_NAME="Sistema SGPI"
```

Para generar `JWT_SECRET` localmente:

```bash
php artisan jwt:secret --show
```

En Gmail, `MAIL_PASSWORD` debe ser una contrasena de aplicacion, no la contrasena normal de la cuenta. Tambien conviene que `MAIL_FROM_ADDRESS` sea el mismo correo de `MAIL_USERNAME`.

## Verificacion rapida

```bash
php artisan route:list --path=auth
php artisan config:clear
php artisan test
```
