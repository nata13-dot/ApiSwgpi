# Consolidación académica del SGPI

## Estado de la entrega

Cambios implementados en API Laravel y clientes PHP/React. El cierre operativo en producción requiere contrastar el esquema real, ensayar el respaldo, conciliar datos y realizar el piloto. No se ejecutaron migraciones, promociones ni cambios sobre la base productiva; tampoco se modificó `.env` ni se publicaron paquetes Android.

La migración nueva parte del esquema normalizado en español, con las ampliaciones de septiembre de 2026. Las pruebas MySQL utilizan una instancia temporal sin red, un esquema sin datos productivos y datos sintéticos. Esto verifica ese esquema representativo; no certifica que el servidor desplegado tenga exactamente la misma estructura.

## Reglas implementadas

- Calendario institucional y estados por carrera: preparación, abierto, en cierre y cerrado. Las consultas GET no activan ni promueven. Apertura exige conciliación y fechas vigentes.
- Matrícula principal por alumno/carrera/periodo; inscripciones a materias independientes. El perfil personal no cambia semestre/grupo. Los grupos y proyectos con historial conservan su grupo original; la continuidad se registra por periodo.
- Promoción revisada con grupo destino, motivo, huella de la vista previa, UUID de operación, transacción y movimiento único por alumno/origen/destino. Salida no significa egreso; noveno permanece en seguimiento.
- Los perfiles operativos sólo se delegan a membresías existentes de la carrera. Asignaciones docentes y jurados usan membresías activas. Los compromisos pendientes bloquean cambios de perfil o desactivación. Se conservan identidad y resultados históricos.
- Autorregistro y listado comparten admisión por carrera, matrícula, periodo, semestre, grupo, ventana y clave. Renovación, cierre, inscritos y baja están disponibles. Las bajas conservan registros; participación en entregas impide la baja libre.
- Entregables por inscripción individual. Las entregas de equipo registran participantes inscritos. La inscripción no asigna sala ni concede una etapa extraordinaria.
- Autorizaciones explícitas por proyecto/periodo: presentación de otra etapa, reprogramación, reintento e inscripción de otro semestre. Solicitar no aprueba. Las decisiones requieren versión vigente; las aprobaciones de proyecto requieren alcance explícito. Vencimientos, revocaciones y usos quedan registrados.
- Salones institucionales y bloqueos de recursos para impedir choques de docente/salón, incluso entre carreras. Se valida duración suficiente. Las reservas consecutivas están permitidas.
- Los reintentos crean evaluaciones nuevas, enlazan su autorización y conservan resultados previos. Las evaluaciones nuevas conservan copia de criterios y escala. Las rúbricas son seguimiento de proyectos; no certifican aprobación escolar ni egreso.
- Residencias individuales de noveno, con empresa, proyecto nuevo o integrador previo, asesor asignado, requisitos, archivos privados versionados y revisión por versión. Cierre exige documentación obligatoria validada; sólo jefatura/administración lo confirma.
- Preparación automática, vencimiento de autorizaciones, avisos previos al cierre y registro de ejecuciones. La tarea reintenta al siguiente ciclo horario y no sustituye las decisiones humanas.

## Trazabilidad de la auditoría

Los nombres de pruebas indicados pertenecen a `tests/Integration/AcademicWorkflowsMySqlTest.php`, salvo indicación distinta. Esta tabla registra evidencia automatizada, no una aceptación productiva.

| Hallazgo | Corrección principal | Evidencia |
|---|---|---|
| F01 | Ámbito obligatorio de carrera en solicitudes y decisiones | `cross_career_authorization…`, `expired_and_cross_career_decisions…` |
| F02 | Estados por carrera y consultas sin promoción | `summary_is_read_only…`, `preparation_job_never_activates_or_promotes`, `maintenance_expires…` |
| F03 | Lotes atómicos, UUID, huella y unicidad de movimientos | `summary_is_read_only…`, `stale_promotion…`, `exit_decision…`, `failed_second_movement…` |
| F04 | Matrícula única, conciliación explícita y continuidad | `reconciliation_requires…`, `group_change_cannot_rewrite…`, `ninth_semester…` |
| F05 | Perfil personal separado de matrícula | `profile_phone_update…` y pruebas existentes de perfil |
| F06 | Actividades ligadas a inscripción individual | `individual_enrollment_controls…`, `mixed_course_team_records_only_enrolled_participants…` |
| F07 | Política de admisión compartida y control de periodo/ventana | `admission_is_idempotent…`, `real_role_and_period_middleware…` |
| F08 | Membresía activa para asignaciones, historial y compromisos | `cross_career_authorization…`, `teaching_assignments…`, `membership_cannot_be_demoted…`, `old_results_keep_teacher_identity…` |
| F09 | Catálogo y bloqueo transaccional de recursos | `agenda_rejects_shared_room…`, `simultaneous_reservations…` (salón y docente) |
| F10 | Validación de etapa al guardar y autorización vinculada | `room_wrong_stage…`, `stage_approval_is_bound…` |
| F11 | Autorizaciones versionadas, reintentos nuevos y resultados inmutables | `authorization_has_explicit_scope…`, `retry_keeps_prior_results…`, `real_scoring_persists…` |
| F12 | Programador configurable, diagnóstico y registro de ejecución | `preparation_job_never_activates_or_promotes`, `maintenance_expires…`; falta verificar instancia única desplegada |

PHP y React comparten el módulo `academicConsole.js` / `academic-console.js`. Vitest comprueba identidad del módulo, navegación de los siete perfiles, inscripción, alcance de autorización y límites de asistencia. La equivalencia completa de los clientes fuera de este módulo requiere los recorridos del piloto; las pruebas DOM no sustituyen pruebas de navegador contra la instalación real.

## Verificación local

Verificación del 14 de octubre de 2026: 48 pruebas de la suite existente (245 aserciones), 34 pruebas de integración MySQL (174 aserciones) y 16 pruebas de React aprobadas. Incluye concurrencia real por salón/docente, evaluación con respuestas normalizadas, equipos con materias diferentes y diagnóstico de esquema sin escrituras. La descarga comprueba inscripción activa o participación histórica explícita; un compañero de proyecto sin vínculo con la materia recibe 403.

La página PHP se renderizó para los siete perfiles mediante `php tests/academic-page-smoke.php <perfil>` desde `Frontend_Swgpi`. Esta comprobación usa sesión sintética y no sustituye los recorridos funcionales de navegador. También se verifican sintaxis PHP, ESLint, compilación React y diferencias de los archivos de implementación sin errores de espacios. Se excluyen de esta última comprobación los archivos de configuración del IDE y Android preexistentes del usuario.

Desde `ApiSwgpi_v2`:

```sh
vendor/bin/phpunit
./scripts/test-academic-mysql.sh
```

El segundo comando exige `mysqld`, `mysql`, `mysqladmin`, PHP con PDO MySQL y `pcntl` para concurrencia. Crea únicamente `sgpi_test_academic` dentro de un directorio temporal y destruye esa instancia al terminar. No utiliza las credenciales de `.env`.

Desde `React_Swgpi`:

```sh
npm test -- --run
npm run lint
npm run build
```

## Despliegue y recuperación

1. **Diagnóstico de sólo lectura.** En la conexión destinada al despliegue, ejecutar `php artisan sgpi:diagnose-academic --baseline` y revisar `php artisan migrate:status`. Guardar conteos iniciales. No ejecutar todas las migraciones históricas sobre una importación normalizada: algunas describen tablas anteriores y no constituyen una reconstrucción segura del esquema actual.
2. **Respaldo y ensayo aislado.** Obtener respaldo de base y archivos privados, comprobar su restauración en otro servidor/base y aplicar allí la migración nueva. Las herramientas existentes `sgpi:backup-database` y `sgpi:verify-backup` requieren revisar antes su configuración de almacenamiento y servidor temporal. Repetir diagnóstico y pruebas sobre la copia; comparar conteos de usuarios, documentos, versiones, entregas y evaluaciones.
3. **Aplicación controlada.** Con requisitos de septiembre verificados y copia recuperable, aplicar únicamente la migración revisada:

   ```sh
   php artisan migrate --path=database/migrations/2026_10_13_000001_consolidate_academic_workflows.php --force
   php artisan sgpi:diagnose-academic
   ```

   El comando se proporciona para el despliegue autorizado; no se ejecutó contra producción. La migración añade tablas/columnas y claves restrictivas, sin borrar el historial. `down()` rechaza una reversión destructiva. Si MySQL interrumpe DDL, inspeccionar los objetos realmente creados antes de reintentar; no usar `migrate:fresh` ni borrar tablas para “arreglarlo”.
4. **Preparación y conciliación del piloto.** Ejecutar `sgpi:prepare-academic-periods`. Administración general registra salones. Jefatura de la carrera piloto revisa matrícula, discrepancias entre grupos/cursos/columnas antiguas, exclusiones con motivo, salas heredadas y excepciones históricas. Cada sala debe vincularse explícitamente a periodo y salón. Las excepciones antiguas no se convierten silenciosamente en aprobaciones de todo el equipo. Las inscripciones existentes a cursos se conservan; no se inventan inscripciones a partir de pertenecer a un proyecto.
5. **Abrir y recorrer.** Abrir sólo la carrera conciliada. Verificar cada perfil en PHP y React: matrícula, cambio personal, inscripción/baja, entrega, propuesta, sala ordinaria, excepción, reintento, residencia y cierre. Revisar el historial de cada modificación y acceso cruzado. Una carrera distinta debe conservar su estado.
6. **Programador único.** En Docker, `SGPI_SCHEDULER_ENABLED=false` por defecto. Activar `true` sólo en una instancia designada, después de instalar el esquema. Las réplicas web ya no ejecutan migraciones al iniciar. La tarea académica se programa cada hora al minuto 10 en `America/Mexico_City`; mantiene bloqueo en base y registro de éxito/fallo. Revisar `schedule:list`, logs de Supervisor y `ejecuciones_academicas`; confirmar avance de la marca de ejecución. No configurar simultáneamente otro cron para el mismo servicio.
7. **Expansión.** Repetir conciliación y recorridos para cada carrera. Habilitar módulos sólo después de pruebas satisfactorias.

Ante un fallo, deshabilitar el módulo afectado por carrera y detener su programador si corresponde. Mantener las evidencias y filas nuevas. Una corrección hacia adelante o una restauración ensayada debe considerar las escrituras posteriores al respaldo; volver a una versión de código que ignore los controles nuevos no es un mecanismo seguro de recuperación.

## Pendientes de aceptación operativa

- Contraste del esquema y datos de la instalación real, restauración de su respaldo y conciliación de discrepancias con responsables académicos.
- Piloto con cuentas reales de prueba en ambos clientes, incluidos archivos y revisión de proyectos ya existentes.
- Verificar procesos/replicas reales y alertar externamente si deja de avanzar la última ejecución.
- Confirmar las ventanas, requisitos documentales obligatorios y salones institucionales. Los tres requisitos iniciales de residencia son “Plan de trabajo”, “Avance documental” e “Informe final”; pueden añadirse requisitos desde el expediente.

No se declara cierre productivo de los doce hallazgos hasta completar estos controles.
