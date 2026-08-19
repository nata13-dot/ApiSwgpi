# Manual operativo SGPI

## Revisión diaria

1. Abrir `/pages/admin/operations.php`.
2. Confirmar cero alertas críticas.
3. Revisar que el índice de continuidad sea 100%.
4. Confirmar un respaldo reciente y al menos uno marcado como restaurable.

Comandos equivalentes:

```bash
php artisan sgpi:check-multicareer
php artisan sgpi:backup-health
php artisan sgpi:scan-operations
php artisan sgpi:measure-continuity
```

## Respaldo y recuperación

- Crear copia: `php artisan sgpi:backup-database`.
- Verificar la última copia: `php artisan sgpi:verify-backup`.
- Verificar una copia concreta: `php artisan sgpi:verify-backup ID`.
- Los archivos viven en `storage/app/private/database-backups` con permisos 600.
- El simulacro restaura en una base temporal y la elimina al terminar.

La restauración productiva requiere mantenimiento, respaldo preventivo, validación temporal y autorización del responsable institucional.

## Atención de alertas

- **Integridad multicarrera:** detener modificaciones relacionadas, exportar auditoría y localizar la relación cruzada antes de corregirla.
- **Respaldo faltante o alterado:** no utilizar el archivo, conservar evidencia y generar una copia nueva.
- **Respaldo antiguo:** revisar el programador y ejecutar una copia manual.
- **Sin restauración verificada:** ejecutar inmediatamente el simulacro.
- **Caída del índice:** comparar las dos últimas mediciones y atender el control que cambió.

Marcar una alerta como atendida no la resuelve. El siguiente análisis la resuelve cuando la condición desaparece.

## Alta de una carrera

1. Crear la carrera desde **Carreras y accesos**.
2. Asignar colores, clave, nombre corto e identidad visual.
3. Habilitar módulos compartidos y específicos.
4. Importar asignaturas y grupos desde **Carga inicial**.
5. Crear al menos un administrador de carrera.
6. Configurar periodos, avisos, rúbricas e indicadores propios.
7. Ejecutar Integridad multicarrera.
8. Cambiar entre carreras como administrador general y validar que los conteos no se mezclen.

## Incidente de aislamiento

1. No borrar registros inmediatamente.
2. Descargar auditoría y reporte de continuidad.
3. Identificar carrera origen y destino.
4. Corregir las claves foráneas dentro de una transacción.
5. Ejecutar `php artisan sgpi:check-multicareer --store`.
6. Documentar responsable, causa y registros afectados.

## Seguridad

- Rotar `APP_KEY` o `JWT_SECRET` solo con un plan de invalidación de sesiones.
- Mantener `APP_DEBUG=false` en producción.
- Limitar `FRONTEND_URLS` a dominios HTTPS exactos.
- Ejecutar `composer audit --locked` antes de cada liberación.
- No exponer `test-api.php`; está restringido al administrador general.
