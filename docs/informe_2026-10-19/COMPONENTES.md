# Responsabilidades de todos los controladores y servicios

Este mapa complementa la narrativa del [informe](README.md). Los enlaces abren las fichas con métodos completos y sus reglas. Un método público de un controlador sólo es endpoint si está registrado en [RUTAS.md](RUTAS.md).

## Los 30 controladores API

| Controlador | Responsabilidad |
|---|---|
| [AcademicOperationsController](controladores/AcademicOperationsController.md) | Catálogo para operación académica; matrícula, continuidad por periodo, delegación de perfiles, compromisos de membresía, salones institucionales, consulta de autorizaciones y conciliación. |
| [ActivityNotificationController](controladores/ActivityNotificationController.md) | Lista personal y conteo de no leídas; lectura individual/masiva con propiedad del recurso. |
| [AsignaturaController](controladores/AsignaturaController.md) | CRUD del catálogo de asignaturas por carrera y representación compatible de competencias. |
| [AuthController](controladores/AuthController.md) | Login JWT, perfil de sesión, carreras, cambio de carrera, refresh/logout, heartbeat y recuperación por correo. |
| [CareerAuditController](controladores/CareerAuditController.md) | Auditoría institucional paginada, filtros de carrera/actor/método/estado/fechas. |
| [CareerExportController](controladores/CareerExportController.md) | ZIP de siete CSV de carrera y resumen CSV institucional; sin archivos privados ni secretos. |
| [CareerIntegrityController](controladores/CareerIntegrityController.md) | Estado de integridad multicarrera, ejecución explícita e historial de resultados. |
| [CareerManagementController](controladores/CareerManagementController.md) | Identidad visual, estado y preparación de carreras; módulos y gobierno de membresías. |
| [CareerModuleController](controladores/CareerModuleController.md) | Módulos configurables, registros genéricos e indicadores, con validaciones por carrera. |
| [CareerSetupController](controladores/CareerSetupController.md) | Importación JSON transaccional de asignaturas y grupos; normalización y coherencia de claves. |
| [CompanyController](controladores/CompanyController.md) | Catálogo/búsqueda de empresas y revisión de aprobación/rechazo. |
| [CompetenciaController](controladores/CompetenciaController.md) | Compatibilidad de lectura basada en asignaturas; las mutaciones responden 422. |
| [ContinuityPolicyController](controladores/ContinuityPolicyController.md) | Consulta y actualización de objetivo, umbral, antigüedad de copias y retención. |
| [ContinuityReportController](controladores/ContinuityReportController.md) | Reporte JSON/PDF, medición histórica y consultas de continuidad. |
| [CourseEnrollmentController](controladores/CourseEnrollmentController.md) | Materias accesibles, inscripción con clave, cierre/renovación de acceso, lista de inscritos y baja. |
| [DashboardController](controladores/DashboardController.md) | Indicadores generales, docente y estudiante; agregados y proyectos recientes. |
| [DatabaseBackupController](controladores/DatabaseBackupController.md) | Lista de hasta 50 respaldos, creación, descarga, verificación, salud y depuración con frase de confirmación. |
| [DeliverableController](controladores/DeliverableController.md) | Actividades por curso, matriz docente, entregables propios, calificación, carga/descarga y respuestas adaptadas. |
| [DocumentTagController](controladores/DocumentTagController.md) | CRUD administrativo de etiquetas; filtros/paginación, estado activo editable y borrado físico con delete(). La relación inexistente que consulta se documenta en el informe. |
| [EvaluationController](controladores/EvaluationController.md) | Rúbricas, criterios, gestores, salas, agenda, turnos, cronómetro, puntajes, votos, reportes y archivo. |
| [OperationalAlertController](controladores/OperationalAlertController.md) | Listado/resumen, escaneo de condiciones y reconocimiento de alertas. |
| [ProfileController](controladores/ProfileController.md) | Datos personales propios, foto, contraseña e inicialización del perfil de alumno. |
| [ProjectController](controladores/ProjectController.md) | Catálogo y CRUD de proyectos/propuestas, equipos, empresas, asesores, materias e importación tabular. |
| [ProposalWorkflowController](controladores/ProposalWorkflowController.md) | Estado del estudiante, búsqueda de integrantes, proyectos del docente, revisión, ventanas y asignaciones de curso. |
| [RepositoryController](controladores/RepositoryController.md) | Repositorio público/privado, publicación, categorías, archivos, liberaciones, presentaciones y tesis. |
| [ResidencyController](controladores/ResidencyController.md) | Expediente individual, requisitos, versiones, revisión, asesor, descarga y cierre de residencia. |
| [SemesterManagementController](controladores/SemesterManagementController.md) | Calendario, resumen/búsqueda, apertura/transición, promoción y puente hacia autorizaciones académicas. |
| [SubjectGroupController](controladores/SubjectGroupController.md) | Grupos y carga de materias, cursos asociados, consulta y asignación de alumnos con historial protegido. |
| [SystemSettingController](controladores/SystemSettingController.md) | Ajustes públicos/actuales/administrativos, avisos y exposición del calendario. |
| [UserController](controladores/UserController.md) | Identidades, filtros, alta/edición/baja/activación, plantillas/importación y envío controlado de credenciales. |

El [Controller base](controladores/Controller.md) no concentra la autorización de negocio: cada componente aplica sus propias reglas junto con middleware y servicios.

## Los 23 servicios

| Servicio | Responsabilidad |
|---|---|
| [AcademicAuthorizationService](servicios/AcademicAuthorizationService.md) | Solicitar, consultar y decidir autorizaciones con carrera, periodo, alcance, vigencia, versión y auditoría. |
| [AcademicDiagnosticsService](servicios/AcademicDiagnosticsService.md) | Diagnóstico de lectura de requisitos base/consolidación, conteos, salas por conciliar y última ejecución. |
| [AcademicMaintenanceService](servicios/AcademicMaintenanceService.md) | Preparar periodos de carreras activas, avisar cierre cercano y vencer autorizaciones; no promover/activar. |
| [AcademicMembershipService](servicios/AcademicMembershipService.md) | Enumerar cursos, salas, asesorías y residencias pendientes; bloquear cambios hasta liberarlos. |
| [AcademicPeriodLifecycleService](servicios/AcademicPeriodLifecycleService.md) | Máquina de estados por carrera, fechas, exclusividad de periodo y bloqueo de cierre con pendientes. |
| [AcademicPromotionService](servicios/AcademicPromotionService.md) | Vista previa y confirmación atómica de avance/permanencia/salida, UUID, snapshot y unicidad. |
| [AcademicReconciliationService](servicios/AcademicReconciliationService.md) | Comparar fuentes históricas y consolidar matrícula, salas y continuidad mediante decisiones explícitas. |
| [AcademicWorkflowService](servicios/AcademicWorkflowService.md) | Utilidades centrales: carrera, preparación del esquema, gestor autorizado, bitácora, periodo, matrícula, docente y semestre de proyecto. |
| [ActivityNotificationService](servicios/ActivityNotificationService.md) | Persistir notificaciones deduplicadas a destinatarios distintos del actor; fallos registrados en log. |
| [BusinessValidationService](servicios/BusinessValidationService.md) | Validadores de asesores, fechas, calificaciones y acceso heredado; su existencia no significa que todas las acciones los invoquen. |
| [ContinuityPolicyService](servicios/ContinuityPolicyService.md) | Lectura/defaults y escritura de política institucional de continuidad, registro id 1. |
| [ContinuityReportService](servicios/ContinuityReportService.md) | Construcción del índice de cuatro controles, historial, instantáneas y tendencia. |
| [CourseAdmissionService](servicios/CourseAdmissionService.md) | Política compartida de admisión, gestión docente y consulta de inscripción activa. |
| [DatabaseBackupService](servicios/DatabaseBackupService.md) | Exportación SQL comprimida, checksum, restauración de prueba, inventario físico y retención. |
| [DeliverySubmissionService](servicios/DeliverySubmissionService.md) | Autorizar y persistir carga de entregable con entrega/documento/versiones/participantes y limpieza compensatoria. |
| [EvaluationAgendaService](servicios/EvaluationAgendaService.md) | Periodo, duración, recursos, choques, membresías de jurado, etapa, sala activa y autorización de intento. |
| [MulticareerIntegrityService](servicios/MulticareerIntegrityService.md) | Once verificaciones SQL de consistencia y almacenamiento de resultados. |
| [OperationalAlertService](servicios/OperationalAlertService.md) | Mantener alertas de integridad/copia/continuidad, reconocerlas y presentar resúmenes. |
| [ProtectedDownloadService](servicios/ProtectedDownloadService.md) | Resolver disco/ruta segura, fallback legado, headers de descarga y X-Accel opcional; autorización previa corresponde al llamador. |
| [ReleaseReadinessService](servicios/ReleaseReadinessService.md) | Checklist operativo parcial de despliegue: configuración, esquema, integridad, recuperación, espacio, binarios y dependencias. |
| [RepositoryFileService](servicios/RepositoryFileService.md) | Persistir documento mediante callback y nueva versión inmutable, con bloqueo/reintento y eliminación compensatoria. |
| [SemesterManagementService](servicios/SemesterManagementService.md) | Fachada del periodo activo, activación explícita y resolución de semestre de presentación con autorización. |
| [StoredFileService](servicios/StoredFileService.md) | Nombre/extensión/MIME/tamaño/estructura, checksum, temporal, promoción de archivo y limpieza. |

## Modelos y compatibilidad que no equivalen a módulos HTTP

`Avance`, `Feedback` y `Grafo` conservan tablas/relaciones auxiliares, pero no hay controladores ni rutas dedicadas de CRUD para avances o grafos en el registro revisado. La retroalimentación efectiva de evaluaciones y entregas utiliza sus propios métodos; no debe asumirse que todo pase por el modelo `Feedback`.

`ProjectRegistrationWindow`, `ProposalReviewException`, `TeacherGroupAssignment` y `SemesterPresentationException` mantienen adaptaciones de estructuras anteriores. El flujo actual puede operar mediante columnas de grupo, tablas de cursos o `autorizaciones_academicas`; consultar sus nombres aislados puede conducir a interpretar incorrectamente dónde se escribe hoy.

Los pivots `ProjectMemberPivot` y `EvaluationRoomProjectPivot` adaptan atributos de relaciones, por ejemplo roles de asesor y datos de presentación. No son endpoints independientes. Los cuatro concerns implementan ámbitos por carrera y traducciones de propiedades/consultas.

`CareerSetting` consulta `configuraciones_carrera` mediante query builder y no hereda de Eloquent; por eso el diccionario lo muestra sin `$table` propio. Las tablas modernas de residencias, matrícula, movimientos y bitácoras también pueden usarse directamente desde servicios/controladores sin una clase Model dedicada.

`AppServiceProvider` registra el contexto y rate limiters. Los dos Mailables seleccionan vistas HTML/texto y asunto; usar el trait `Queueable` no convierte automáticamente un envío `send()` en asíncrono.

## Archivos fuera de app

Los 62 archivos de migración documentan evolución, no exclusivamente el esquema final. Los 22 SQL incluyen cambios complementarios y capa BI: deben revisarse respecto a la base que se actualiza. Los seeders contienen datos de demostración y uno reinicia catálogos/relaciones; no se ejecutaron como parte del informe.

Las vistas PDF definen presentación de reportes; las de correo definen contenido HTML/texto. Los recursos JS/CSS/Vite construyen la página/recursos Laravel, no sustituyen el frontend React o PHP de los directorios hermanos. Los workflows de issues, pull requests y changelog son automatizaciones del repositorio, no reglas del dominio académico. Su contenido completo está en [COMPLEMENTOS.md](COMPLEMENTOS.md).
