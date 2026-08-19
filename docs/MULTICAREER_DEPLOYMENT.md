# Despliegue multicarrera SGPI

## Alcance

Esta versión soporta ISC, Ingeniería Industrial, Ingeniería Electromecánica y Contador Público. Los datos operativos se aíslan mediante `carrera_id`, membresías activas y contexto JWT. El perfil 4 corresponde al administrador general; los administradores de carrera conservan el perfil 1 dentro de sus membresías.

## Requisitos

- PHP 8.4 o superior con extensiones requeridas por Composer.
- MySQL 8.
- Composer 2.
- `mysql`, `mysqldump` y `gzip` disponibles para el usuario del servicio.
- Dos procesos web: API Laravel y frontend PHP.
- Un cron o proceso persistente que ejecute `php artisan schedule:run` cada minuto.

## Variables obligatorias

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.ejemplo.mx
FRONTEND_URLS=https://sistema.ejemplo.mx
APP_KEY=base64:...
JWT_SECRET=...

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=sgpi
DB_USERNAME=...
DB_PASSWORD=...

CONTINUITY_TARGET_READINESS=100
CONTINUITY_CRITICAL_READINESS=75
CONTINUITY_MAX_BACKUP_AGE_HOURS=26
```

No se deben copiar `.env`, respaldos SQL, logs ni claves al directorio público.

## Orden de actualización

1. Crear un respaldo externo antes del despliegue.
2. Activar modo mantenimiento: `php artisan down --retry=60`.
3. Instalar dependencias: `composer install --no-dev --prefer-dist --optimize-autoloader`.
4. Aplicar, en este orden, los scripts que aún no existan en la base:

```text
2026_07_28_create_multicareer_foundation.sql
2026_07_28_create_career_modules_and_indicators.sql
2026_07_28_seed_career_specific_settings.sql
2026_07_28_harden_multicareer_operational_isolation.sql
2026_07_28_create_multicareer_audit_log.sql
2026_07_28_scope_rubrics_by_career.sql
2026_07_28_create_integrity_run_history.sql
2026_07_28_create_database_backup_history.sql
2026_07_28_create_backup_restore_verifications.sql
2026_07_28_add_backup_retention_fields.sql
2026_07_28_create_operational_alerts.sql
2026_07_29_create_continuity_measurements.sql
2026_07_29_create_continuity_policy.sql
```

5. Ejecutar `php artisan optimize`.
6. Ejecutar `php artisan sgpi:release-check`.
7. Reactivar: `php artisan up`.
8. Validar login, cambio de carrera, repositorio y Centro de operaciones.

Cada entorno debe registrar qué scripts fueron aplicados. No se deben ejecutar de nuevo los scripts `ALTER TABLE` que no sean idempotentes.

## Programador

Cron recomendado:

```cron
* * * * * cd /ruta/ApiSwgpi_v2 && php artisan schedule:run >> /dev/null 2>&1
```

Las tareas internas sincronizan periodo, verifican aislamiento, generan respaldos, revisan almacenamiento, prueban restauración, analizan alertas y guardan mediciones de continuidad.

## Reversión

1. Mantener la aplicación en mantenimiento.
2. Conservar el respaldo fallido y los logs.
3. Restaurar el respaldo previo en una base temporal.
4. Validarlo con `php artisan sgpi:verify-backup <id>` o con el procedimiento del manual operativo.
5. Cambiar la conexión únicamente después de comprobar tablas esenciales.
6. Restaurar la versión anterior del código y ejecutar `php artisan optimize:clear`.

Nunca se debe restaurar directamente sobre producción sin una copia previa y una ventana de mantenimiento.
