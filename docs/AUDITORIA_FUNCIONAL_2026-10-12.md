# Auditoría funcional del SGPI — 12 de octubre de 2026

El sistema tiene los módulos necesarios, pero sus reglas todavía no forman un flujo académico consistente. Los bloqueos principales son el aislamiento incompleto de excepciones, la promoción global/parcial de alumnos, la coexistencia de dos fuentes de inscripción académica y la desconexión del autorregistro respecto de entregables.

## Alcance y evidencia

Revisión estática de la API Laravel, rutas, middleware, modelos, servicios, configuración de ejecución, pruebas existentes y pantallas React relacionadas. Se siguieron las relaciones con proyectos, propuestas, grupos, entregables y evaluaciones. El cliente PHP histórico y los artefactos APK no fueron auditados exhaustivamente; no se certifica equivalencia entre clientes.

No se consultó la base productiva, no se leyó el contenido de `.env`, no se ejecutaron migraciones ni tareas de promoción. Los escenarios siguientes se derivan del código; no se presentan como incidentes ya observados en producción. La estructura efectiva de la base desplegada queda pendiente de contrastar.

Validación ejecutada con SQLite en memoria: PHPUnit **49 pruebas, 247 aserciones, correctas**. React: **4 pruebas correctas**, ESLint sin errores. Las pruebas existentes no cubren de extremo a extremo autorregistro, promoción multicarrera, excepciones y agenda de salas.

Prioridad P1: corregir antes de confiar en la operación autónoma. P2: brecha funcional o de trazabilidad que debe resolverse en la misma evolución.

## Hallazgos

### F01 — P1 — Las excepciones permiten operar sobre otra carrera

Evidencia: `app/Http/Controllers/API/SemesterManagementController.php:28`, `:148`, `:202`; `app/Models/SemesterPresentationException.php:9`.

El modelo de excepciones no tiene ámbito por carrera. El listado consulta todas las autorizaciones activas de presentación. La creación valida existencia global de proyecto/alumno y la búsqueda del proyecto del alumno usa SQL sin carrera. La baja sólo comprueba el tipo de autorización.

Escenario: una asistencia de la carrera A, autorizada para gestionar semestres, envía el ID de un proyecto o excepción de B. No hay comprobación de pertenencia en estas operaciones. Aunque relaciones cargadas puedan quedar vacías por sus propios ámbitos, la autorización base sigue siendo global.

Impacto: modificación o revocación de autorizaciones académicas ajenas y exposición de sus metadatos.

Corrección: resolver el objetivo dentro de la carrera activa antes de validar/guardar; aplicar autorización también a lectura y baja; almacenar carrera e integridad referencial de la autorización.

Aceptación: A no puede listar, crear ni revocar excepciones de B; una petición con ID ajeno devuelve 403/404 sin mutaciones.

### F02 — P1 — Activación global con promoción parcial y efectos desde una consulta GET

Evidencia: `app/Services/SemesterManagementService.php:30`, `:46`, `:64`; `app/Http/Controllers/API/SemesterManagementController.php:25`; `app/Models/AcademicPeriod.php:8`; `app/Models/Concerns/ScopedToCareerMembership.php:74`; `routes/console.php:114` (buscar `sync-current-academic-period`).

Los periodos y `active_academic_period` son globales. La activación desactiva todos los demás periodos y registra una única marca `promocion_aplicada_en`. En HTTP, `User::students()` se limita a la carrera activa; en consola, sin contexto, usa el perfil global del usuario. Además, GET `/semester-management` llama a sincronización y puede activar/promover.

Escenario: A abre el resumen al iniciar un periodo automático; se promueven alumnos de A y se marca el periodo global como procesado. B puede quedar sin promoción automática. Si corre primero el programador, la selección se hace con perfiles globales, con resultados distintos del flujo HTTP.

Corrección: separar calendario institucional de operación por carrera; registrar ejecución por carrera/periodo/alumno; usar membresías también en tareas de consola; GET debe ser sólo lectura. Definir si la activación institucional corresponde exclusivamente a administración general.

Aceptación: el orden de apertura de pantallas y ejecución de tareas no cambia los alumnos promovidos; cada carrera mantiene su avance verificable.

### F03 — P1 — Promoción sin idempotencia manual ni transición académica completa

Evidencia: `app/Http/Controllers/API/SemesterManagementController.php:111`; `app/Services/SemesterManagementService.php:64`.

`applyPromotion()` no rechaza un periodo ya procesado ni exige que sea el destino vigente. Incrementa `usuarios.semestre`; no crea matrícula en grupo/curso destino, no cierra la anterior ni traslada explícitamente el seguimiento del proyecto. El nombre del periodo decide la paridad: cualquier nombre sin sufijo `-1`/`-2` permite todos los semestres.

Escenario: con un periodo llamado `Agosto-Diciembre`, dos solicitudes sucesivas pueden subir un alumno de 5 a 6 y luego a 7. Con nombres convencionales la paridad limita esa repetición inmediata, pero no resuelve la falta de identidad de la transición. Tampoco hay bloqueo para activaciones concurrentes del mismo periodo.

Corrección: transición transaccional con origen/destino, clave única de ejecución por alumno, previsualización real, tratamiento de rezago y egreso y asignación explícita a grupos/cursos. La política académica no debe inferirse del texto del nombre.

Aceptación: repetir una operación produce cero movimientos adicionales; una falla no deja un conjunto parcialmente promovido; alumnos de noveno tienen un destino explícito.

### F04 — P1 — Dos fuentes incompatibles para semestre y grupo

Evidencia: `app/Models/User.php:169`; `app/Http/Controllers/API/ProfileController.php:135`; `app/Http/Controllers/API/SubjectGroupController.php:130`; `app/Services/SemesterManagementService.php:64`.

Perfil elimina semestre/grupo del payload de usuario y escribe `grupo_estudiantes`. Gestión de grupos y promoción escriben `usuarios.semestre/grupo`. El accesor del usuario prioriza las columnas si existen, incluso si contienen null, y sólo consulta matrículas cuando no están seleccionadas. Los listados y promoción filtran esas columnas directamente.

Impacto: el mismo alumno puede aparecer en semestres diferentes según endpoint/selección; cambiar su perfil puede no reflejarse en los listados. Si el esquema desplegado no contiene las columnas históricas, las consultas directas pueden fallar; esto requiere verificación del esquema real.

Corrección: una fuente académica única por alumno/carrera/periodo, usada por perfil, grupos, propuestas y promoción; eliminar escrituras heredadas después de migrar y contrastar datos.

### F05 — P1 — Actualizar sólo datos personales puede desactivar la matrícula

Evidencia: `app/Http/Controllers/API/ProfileController.php:59`, `:135`, `:150`.

`update()` añade `semestre` y `grupo` al request como null cuando están ausentes. `pullAcademicAssignment()` considera que se solicitó cambio porque las claves existen. `syncAcademicAssignment()` desactiva todas las matrículas activas y, si ambos valores son null, no crea reemplazo.

Escenario: un alumno envía POST `/profile` únicamente con teléfonos o una contraseña nueva. El código puede tratarlo como una solicitud para vaciar su asignación académica.

Corrección: distinguir campo omitido de petición explícita de borrar; no modificar matrícula en cambios personales. Validar además periodo del grupo destino: actualmente se busca el primer grupo activo que coincide en semestre y letra, sin periodo.

Aceptación: actualizar teléfono, foto o contraseña conserva exactamente las matrículas anteriores.

### F06 — P1 — Autorregistro desconectado del seguimiento académico

Evidencia: `app/Http/Controllers/API/CourseEnrollmentController.php:36`; `app/Http/Controllers/API/DeliverableController.php:223`, `:943`; `app/Models/Course.php:35`.

Inscribirse sólo escribe `curso_estudiantes`. “Mis entregables” busca proyectos del alumno y después todos los entregables del grupo del proyecto; no consulta esa matrícula individual. En el código de aplicación revisado, `curso_estudiantes` sólo aparece en el controlador de inscripción y la relación del modelo Course.

Escenario: un alumno inscrito a una materia de otro grupo no recibe por ello sus actividades; un integrante de proyecto puede recibir materias del grupo sin haberse inscrito individualmente.

Corrección: definir si la inscripción da acceso a actividades, propuestas y evaluación y centralizar esa regla. Mantener explícita la diferencia entre entrega por equipo y matrícula individual.

Aceptación: inscripción/baja cambia el acceso esperado, respeta el historial y no concede acceso a materias ajenas.

### F07 — P1 — El endpoint de inscripción permite cursos fuera del periodo o de grupos inactivos

Evidencia: `app/Http/Controllers/API/CourseEnrollmentController.php:17`, `:36`; `React_Swgpi/src/pages/CourseEnrollment.jsx:15`.

El listado excluye grupos inactivos, pero el POST sólo verifica curso activo, indicador de seguimiento y contraseña. No revalida grupo activo ni periodo/ventana de inscripción. El listado tampoco filtra periodo.

Escenario: un alumno conserva un ID y clave de un curso cuyo grupo fue desactivado; la llamada directa puede volver a inscribirlo. Un curso histórico aún activo permanece disponible.

Corrección: política de admisión común a listado y escritura, con periodo y apertura/cierre explícitos. Añadir baja y consulta de inscritos; la pantalla actual sólo habilita/cambia clave y no ofrece deshabilitar, aunque la API sí permite `enabled=false`.

### F08 — P1 — Asignaciones usan perfil global en lugar del perfil por carrera

Evidencia: `app/Http/Controllers/API/EvaluationController.php:2199`; `app/Http/Controllers/API/ProposalWorkflowController.php:103`; `app/Http/Controllers/API/SemesterManagementController.php:160`.

Las validaciones de evaluadores usan `usuarios.perfil_id IN (1,2)`; propuestas exige perfil global 2; excepciones de alumnos exige global 3. Esas reglas no representan `usuario_carrera.perfil_id`. En salas tampoco se comprueba que cada evaluador tenga membresía activa en la carrera antes de sincronizar IDs.

Escenarios: un usuario globalmente coordinador pero docente en B es rechazado al asignarlo; uno globalmente docente, pero sin pertenecer a B, puede superar la validación de una sala de B.

Corrección: resolver elegibilidad por membresía activa y capacidad requerida. Si se permiten jurados externos a una carrera, modelar esa autorización de manera explícita.

### F09 — P1 — Las salas no verifican choques de docente ni salón

Evidencia: `app/Http/Controllers/API/EvaluationController.php:2199`, `:2315`, `:2351`.

Se comprueba nombre repetido en horario coincidente y proyecto en otra sala activa. No se comprueba intersección horaria de evaluadores ni coincidencia física de `salon`. El ámbito por carrera tampoco permitiría detectar por sí solo a un docente ocupado en otra carrera.

Escenario: dos salas con nombres y proyectos distintos, mismo salón o mismo jurado, al mismo horario pasan estas validaciones.

Corrección: agenda de recursos compartidos, duración total factible y reservas atómicas. Validar antes de guardar y proteger contra creaciones concurrentes; `roomRules()` se ejecuta antes de la transacción de creación.

### F10 — P1 — La excepción no es una condición obligatoria para asignar un semestre distinto

Evidencia: `app/Http/Controllers/API/EvaluationController.php:813`, `:2199`; `app/Services/SemesterManagementService.php:134`.

El listado de proyectos calcula `presentation_semester`, pero las reglas de escritura de sala sólo comprueban carrera, existencia, duplicados y rúbrica. No comparan semestre real/autorizado del proyecto contra semestre de sala. Se puede enviar directamente un proyecto a otra rúbrica sin excepción.

Además, excepciones aceptan semestre 9 mientras salas sólo admiten 5–8: existe una autorización que ese flujo no puede materializar.

Corrección: aplicar en backend una política única de elegibilidad por periodo y semestre; distinguir sala ordinaria/extraordinaria y vincular autorización, motivo, vigencia y responsable. Definir explícitamente si noveno es evaluable.

### F11 — P2 — Se sobrescribe el historial de excepciones entre periodos

Evidencia: `app/Http/Controllers/API/SemesterManagementController.php:178`; `app/Services/SemesterManagementService.php:93`, `:134`.

`updateOrCreate` identifica la excepción por proyecto/usuario/tipo, sin periodo; una nueva autorización reemplaza el JSON de la anterior. La lectura sólo comprueba activa/periodo, no `vigente_hasta`. En la resolución por lotes, ordenar descendente y luego `keyBy(proyecto_id)` conserva el último valor encontrado: con varias excepciones individuales del equipo puede prevalecer la más antigua.

Corrección: periodo como dato relacional y parte de la identidad; conservar autorizaciones/revocaciones; definir precedencia explícita entre autorización del proyecto y las de integrantes y evaluar vencimiento.

### F12 — P2 — Autonomía operativa no demostrada en el despliegue incluido

Evidencia: `routes/console.php`; `docker/supervisord.conf`; `Dockerfile`; `railway.json`.

Existe la tarea diaria de sincronización, pero la configuración de Supervisor revisada ejecuta el trabajador de colas, sin servicio del programador. No se encontró cron/schedule en Dockerfile o railway.json. Un programador externo podría existir; no se inspeccionó infraestructura desplegada.

Corrección: verificar ejecución externa o incorporar scheduler, zona horaria, monitor de última ejecución y reintentos idempotentes. Una pantalla que dispara sincronización no sustituye la automatización.

## Decisiones de producto pendientes, distintas de errores

- Actualmente sólo administración general puede crear identidades y administrar membresías. Jefatura/asistencia gestionan operación académica, pero no asignan perfiles. Es una restricción explícita de rutas y pruebas; si se requiere delegación por carrera, debe diseñarse sin permitir elevar privilegios institucionales.
- Hay un perfil por membresía usuario/carrera. Si una persona debe ser simultáneamente coordinador y docente en la misma carrera, hacen falta capacidades compatibles o múltiples roles, no otra interpretación de `usuarios.perfil_id`.
- Aclarar si el autorregistro sólo comprende materias de seguimiento de proyecto o todas las materias; el código actual lo limita a las primeras.
- Definir si el calendario es institucional o independiente por carrera; aun compartiendo fechas, cierre y promoción necesitan seguimiento por carrera.
- Definir rezago, recursamiento, egreso, prórroga documental, reintento de evaluación y presentación extraordinaria como operaciones diferentes.

## Secuencia propuesta de corrección

1. Contener cambios cruzados de excepciones y validar membresías de evaluadores; corregir pérdida accidental de matrícula al editar perfil.
2. Unificar matrícula académica por carrera/periodo y reconciliar datos históricos antes de automatizar promociones.
3. Separar lectura, activación, promoción y cierre; registrar movimientos idempotentes con trazabilidad y política de rezago/egreso.
4. Conectar autorregistro con actividades y permisos; implementar ventana de inscripción, baja y lista de alumnos.
5. Formalizar autorizaciones extraordinarias y agenda de recursos; comprobar elegibilidad al escribir, no únicamente al filtrar pantallas.
6. Verificar scheduler y ejecutar pruebas de aceptación integrales en una base aislada representativa del esquema real.

## Pruebas de aceptación mínimas

| Flujo | Prueba requerida |
|---|---|
| Aislamiento | Cada perfil de A intenta leer/modificar excepciones y asignaciones de B. |
| Perfiles | Misma persona con perfil global distinto del perfil docente en la carrera activa. |
| Perfil personal | Cambiar sólo teléfono/contraseña conserva la matrícula. |
| Autorregistro | Curso vigente, curso de grupo inactivo, curso histórico, clave inválida, baja y reingreso. |
| Actividades | Alumno inscrito/no inscrito, con/sin proyecto y con materia de otro grupo. |
| Promoción | Dos carreras, ejecuciones HTTP/consola en distinto orden, doble envío y fallo intermedio. |
| Historial | Cambio de periodo conserva matrículas, autorizaciones, evidencias y resultados previos. |
| Excepciones | Vigente, vencida, revocada, siguiente periodo e integrantes con permisos contradictorios. |
| Salas | Mismo docente/salón en horas superpuestas, incluso entre carreras y solicitudes concurrentes. |
| Elegibilidad | Proyecto de sexto hacia sala de octavo con y sin autorización válida. |

No se modificó lógica funcional durante esta auditoría. Este documento entrega el diagnóstico y criterios verificables para una fase de corrección.
