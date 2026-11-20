<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Explica en palabras cada operación auditada: qué se hizo, a quién o a qué (con nombre, número de control o título)
 * y los detalles útiles (campos modificados, decisión, motivo, cantidad). El objetivo se lee ANTES de ejecutar la
 * acción para que las eliminaciones conserven el nombre. Nunca guarda contraseñas, códigos ni archivos.
 */
final class AuditDescriber
{
    /** Controlador@método => [frase en pasado, tipo de objetivo, parámetro de ruta con su id]. */
    private const ACTIONS = [
        'SelfRegistrationController@approve' => ['Aprobó la solicitud de cuenta de', 'registration', 'id'],
        'SelfRegistrationController@reject' => ['Rechazó la solicitud de cuenta de', 'registration', 'id'],
        'SelfRegistrationController@updateRule' => ['Cambió la regla de autoregistro de la carrera', 'career', 'career'],
        'UserController@store' => ['Dio de alta al usuario', 'user', null],
        'UserController@update' => ['Editó los datos de', 'user', 'id'],
        'UserController@destroy' => ['Eliminó definitivamente al usuario', 'user', 'id'],
        'UserController@toggleActive' => ['Cambió el estado (activo/inactivo) de', 'user', 'id'],
        'UserController@bulk' => ['Aplicó una acción masiva a usuarios', null, null],
        'UserController@importExcel' => ['Importó usuarios desde un archivo', null, null],
        'UserController@sendCredentialEmails' => ['Envió credenciales por correo', null, null],
        'UserController@setGeneralAdmin' => ['Cambió el permiso de administración general de', 'user', 'id'],
        'ProfileController@update' => ['Actualizó su perfil', null, null],
        'ProfileController@completeInitial' => ['Completó su perfil inicial', null, null],
        'ProjectController@store' => ['Registró el proyecto', 'project', null],
        'ProjectController@update' => ['Editó el proyecto', 'project', 'id'],
        'ProjectController@destroy' => ['Eliminó el proyecto', 'project', 'id'],
        'ProjectController@bulkDestroy' => ['Eliminó varios proyectos', null, null],
        'ProjectController@importExcel' => ['Importó proyectos desde un archivo', null, null],
        'ProjectController@addAdvisor' => ['Agregó un asesor al proyecto', 'project', 'id'],
        'ProjectController@syncAdvisors' => ['Cambió los asesores del proyecto', 'project', 'id'],
        'ProjectController@removeAdvisor' => ['Quitó un asesor del proyecto', 'project', 'projectId'],
        'ProjectController@syncAsignaturas' => ['Cambió las materias del proyecto', 'project', 'id'],
        'ProjectController@setResidency' => ['Cambió la marca de residencia del proyecto', 'project', 'id'],
        'ProposalWorkflowController@register' => ['Registró una propuesta de proyecto', null, null],
        'ProposalWorkflowController@updateMine' => ['Editó su propuesta de proyecto', null, null],
        'ProposalWorkflowController@review' => ['Revisó la propuesta', 'project', 'id'],
        'ProposalWorkflowController@requestJoin' => ['Pidió unirse a la propuesta', 'project', 'id'],
        'ProposalWorkflowController@answerJoin' => ['Respondió una solicitud para unirse a su propuesta', null, null],
        'ProposalWorkflowController@cancelJoin' => ['Canceló su solicitud para unirse a una propuesta', null, null],
        'ProposalWorkflowController@storeAssignment' => ['Asignó un docente a una materia y grupo', null, null],
        'ProposalWorkflowController@destroyAssignment' => ['Quitó a un docente de una materia y grupo', null, null],
        'ProposalWorkflowController@storeWindow' => ['Abrió una ventana de registro de propuestas', null, null],
        'ProposalWorkflowController@updateWindow' => ['Cambió una ventana de registro de propuestas', null, null],
        'ProposalWorkflowController@destroyWindow' => ['Cerró una ventana de registro de propuestas', null, null],
        'ProposalWorkflowController@updateNewStudentGroups' => ['Cambió los grupos para alumnos de nuevo ingreso', null, null],
        'ProposalWorkflowController@updateProposalWindow' => ['Cambió el periodo de registro de propuestas', null, null],
        'ProposalWorkflowController@updateSelfRegistration' => ['Cambió la ventana de autoregistro de alumnos', null, null],
        'ProposalWorkflowController@updateValidators' => ['Cambió los docentes que validan propuestas', null, null],
        'SubjectGroupController@store' => ['Creó el grupo', 'group', null],
        'SubjectGroupController@update' => ['Editó el grupo', 'group', 'id'],
        'SubjectGroupController@destroy' => ['Eliminó el grupo', 'group', 'id'],
        'SubjectGroupController@syncStudents' => ['Agregó alumnos al grupo', 'group', 'id'],
        'SubjectGroupController@removeStudent' => ['Quitó a un alumno del grupo', 'group', 'id'],
        'AsignaturaController@store' => ['Creó la materia', 'subject', null],
        'AsignaturaController@update' => ['Editó la materia', 'subject', 'id'],
        'AsignaturaController@destroy' => ['Eliminó la materia', 'subject', 'id'],
        'CompetenciaController@store' => ['Creó la materia', 'subject', null],
        'CompetenciaController@update' => ['Editó la materia', 'subject', 'id'],
        'CompetenciaController@destroy' => ['Eliminó la materia', 'subject', 'id'],
        'DeliverableController@store' => ['Creó el entregable', 'deliverable', null],
        'DeliverableController@update' => ['Editó el entregable', 'deliverable', 'id'],
        'DeliverableController@destroy' => ['Quitó el entregable', 'deliverable', 'id'],
        'DeliverableController@upload' => ['Subió un archivo al entregable', 'deliverable', 'id'],
        'DeliverableController@calificar' => ['Calificó el entregable', 'deliverable', 'id'],
        'EvaluationController@store' => ['Creó una evaluación', null, null],
        'EvaluationController@update' => ['Editó la evaluación de', 'evaluation', 'id'],
        'EvaluationController@destroy' => ['Eliminó la evaluación de', 'evaluation', 'id'],
        'EvaluationController@score' => ['Capturó la evaluación de', 'evaluation', 'id'],
        'EvaluationController@feedback' => ['Dejó retroalimentación en la evaluación de', 'evaluation', 'id'],
        'EvaluationController@markCompleted' => ['Marcó como concluida la evaluación de', 'evaluation', 'id'],
        'EvaluationController@confirmViability' => ['Confirmó el dictamen de viabilidad de', 'evaluation', 'id'],
        'EvaluationController@archive' => ['Archivó la evaluación de', 'evaluation', 'id'],
        'EvaluationController@unarchive' => ['Restauró la evaluación de', 'evaluation', 'id'],
        'EvaluationController@archiveSelected' => ['Archivó varias evaluaciones', null, null],
        'EvaluationController@unarchiveSelected' => ['Restauró varias evaluaciones', null, null],
        'EvaluationController@storeRoom' => ['Creó la sala de evaluación', 'room', null],
        'EvaluationController@updateRoom' => ['Editó la sala de evaluación', 'room', 'id'],
        'EvaluationController@updateRoomSchedule' => ['Cambió el horario de la sala', 'room', 'id'],
        'EvaluationController@destroyRoom' => ['Eliminó la sala de evaluación', 'room', 'id'],
        'EvaluationController@archiveRoom' => ['Archivó la sala de evaluación', 'room', 'id'],
        'EvaluationController@unarchiveRoom' => ['Restauró la sala de evaluación', 'room', 'id'],
        'EvaluationController@advanceRoom' => ['Pasó al siguiente turno en la sala', 'room', 'id'],
        'EvaluationController@controlRoomTimer' => ['Controló el temporizador de la sala', 'room', 'id'],
        'EvaluationController@lockRoomSequence' => ['Fijó el orden de presentación de la sala', 'room', 'id'],
        'EvaluationController@storeCriterion' => ['Agregó un criterio a la rúbrica', null, null],
        'EvaluationController@updateCriterion' => ['Editó un criterio de la rúbrica', null, null],
        'EvaluationController@destroyCriterion' => ['Eliminó un criterio de la rúbrica', null, null],
        'EvaluationController@initializeRubrics' => ['Preparó las rúbricas de la carrera', null, null],
        'EvaluationController@updateManagers' => ['Cambió los responsables de evaluación', null, null],
        'EvaluationController@updateRubricScoreModes' => ['Cambió el modo de puntaje de las rúbricas', null, null],
        'RepositoryController@store' => ['Subió un documento al repositorio', null, null],
        'RepositoryController@update' => ['Editó el documento del repositorio', 'document', 'id'],
        'RepositoryController@destroy' => ['Eliminó el documento del repositorio', 'document', 'id'],
        'RepositoryController@publish' => ['Cambió la publicación del documento', 'document', 'id'],
        'ArchiveController@archive' => ['Envió registros al archivo histórico', null, null],
        'ArchiveController@restore' => ['Restauró registros del archivo histórico', null, null],
        'CourseEnrollmentController@enroll' => ['Se inscribió a la materia', 'course', 'course'],
        'CourseEnrollmentController@leave' => ['Registró una baja de la materia', 'course', 'course'],
        'CourseEnrollmentController@updateAccess' => ['Cambió el autoregistro de la materia', 'course', 'course'],
        'AcademicOperationsController@assign' => ['Asignó la matrícula de un alumno a un grupo', null, null],
        'AcademicOperationsController@membership' => ['Cambió el perfil de una persona en la carrera', null, null],
        'AcademicOperationsController@applyAutomation' => ['Aplicó la preparación automática del periodo', null, null],
        'AcademicOperationsController@projectPeriod' => ['Pasó un proyecto al periodo abierto', null, null],
        'AcademicOperationsController@classroom' => ['Registró un salón', null, null],
        'SemesterManagementController@storePeriod' => ['Creó un periodo', null, null],
        'SemesterManagementController@updatePeriod' => ['Editó el periodo', 'period', 'period'],
        'SemesterManagementController@transition' => ['Cambió el estado del periodo', 'period', 'period'],
        'SemesterManagementController@activatePeriod' => ['Activó el periodo', 'period', 'period'],
        'SemesterManagementController@applyPromotion' => ['Aplicó la promoción de semestre del periodo', 'period', 'period'],
        'SemesterManagementController@generateGroups' => ['Generó los grupos del periodo', 'period', 'period'],
        'SemesterManagementController@storeException' => ['Solicitó una autorización académica', null, null],
        'SemesterManagementController@decideException' => ['Resolvió una autorización académica', null, null],
        'SemesterManagementController@destroyException' => ['Retiró una autorización académica', null, null],
        'SemesterManagementController@answerIrregularCheck' => ['Respondió la verificación de alumno irregular', null, null],
        'ResidencyController@store' => ['Abrió un expediente de residencia', null, null],
        'ResidencyController@advisor' => ['Cambió el asesor de la residencia', null, null],
        'ResidencyController@close' => ['Cerró el seguimiento de la residencia', null, null],
        'ResidencyController@requirement' => ['Agregó un requisito a la residencia', null, null],
        'ResidencyController@upload' => ['Subió una versión de un requisito de residencia', null, null],
        'ResidencyController@review' => ['Revisó una versión de un requisito de residencia', null, null],
        'ProjectTrackingController@storeSubject' => ['Agregó una materia que pide avances', null, null],
        'ProjectTrackingController@updateSubject' => ['Cambió una materia que pide avances', null, null],
        'ProjectTrackingController@updateTemplate' => ['Cambió la plantilla de avances de una materia', null, null],
        'ProjectTrackingController@updateCompetencies' => ['Cambió las competencias de una materia', null, null],
        'ProjectTrackingController@applyTemplate' => ['Agregó los avances de la plantilla a la materia', 'course', 'course'],
        'ProjectTrackingController@link' => ['Agregó un proyecto a la materia', 'course', 'course'],
        'ProjectTrackingController@unlink' => ['Quitó un proyecto de la materia', 'course', 'course'],
        'ProjectTrackingController@extend' => ['Dio una prórroga en el avance', 'deliverable', 'deliverable'],
        'ProjectTrackingController@removeExtension' => ['Retiró una prórroga del avance', 'deliverable', 'deliverable'],
        'ProjectTrackingController@adjust' => ['Ajustó la calificación individual de un integrante', null, null],
        'ProjectTrackingController@removeAdjustment' => ['Quitó el ajuste de calificación de un integrante', null, null],
        'ProjectTrackingController@storePackage' => ['Creó un paquete de materias', null, null],
        'ProjectTrackingController@packageFromCurrent' => ['Guardó las materias actuales como paquete', null, null],
        'ProjectTrackingController@updatePackage' => ['Editó un paquete de materias', null, null],
        'ProjectTrackingController@destroyPackage' => ['Eliminó un paquete de materias', null, null],
        'ProjectTrackingController@applyPackage' => ['Aplicó un paquete de materias', null, null],
        'ThesisTrackingController@store' => ['Creó un curso de tesis', null, null],
        'ThesisTrackingController@update' => ['Editó el curso de tesis', 'thesis', 'id'],
        'ThesisTrackingController@updateReviewers' => ['Cambió los docentes revisores de la tesis', 'thesis', 'id'],
        'ThesisTrackingController@submit' => ['Entregó una versión de la tesis', 'thesis', 'id'],
        'ThesisTrackingController@review' => ['Revisó un capítulo de tesis', null, null],
        'ThesisTrackingController@annotate' => ['Agregó una nota a una entrega de tesis', null, null],
        'ThesisTrackingController@removeAnnotation' => ['Quitó una nota de una entrega de tesis', null, null],
        'ThesisTrackingController@release' => ['Liberó la tesis', 'thesis', 'id'],
        'ThesisTrackingController@reopen' => ['Reabrió el curso de tesis', 'thesis', 'id'],
        'CareerManagementController@update' => ['Editó la carrera', 'career', 'career'],
        'CareerManagementController@storeMembership' => ['Dio acceso a una persona en una carrera', null, null],
        'CareerManagementController@updateMembership' => ['Cambió el acceso de una persona a una carrera', null, null],
        'CareerManagementController@destroyMembership' => ['Quitó el acceso de una persona a una carrera', null, null],
        'CareerModuleController@updateModule' => ['Activó o desactivó un módulo de la carrera', null, null],
        'CareerSetupController@importCatalog' => ['Cargó el plan de estudios de la carrera', null, null],
        'SystemSettingController@update' => ['Cambió los ajustes del sistema', null, null],
        'SystemSettingController@updateNotices' => ['Cambió los avisos del sistema', null, null],
        'DocumentTagController@store' => ['Creó una etiqueta de documentos', null, null],
        'DocumentTagController@update' => ['Editó una etiqueta de documentos', null, null],
        'DocumentTagController@destroy' => ['Eliminó una etiqueta de documentos', null, null],
        'CompanyController@review' => ['Revisó la empresa', null, null],
        'DatabaseBackupController@store' => ['Generó un respaldo de la base de datos', null, null],
        'DatabaseBackupController@verify' => ['Verificó un respaldo de la base de datos', null, null],
        'DatabaseBackupController@cleanup' => ['Limpió respaldos antiguos', null, null],
        'CareerAuditController@prune' => ['Depuró la auditoría (con copia en los respaldos)', null, null],
        'CareerIntegrityController@run' => ['Ejecutó la verificación de integridad', null, null],
        'ContinuityPolicyController@update' => ['Cambió la política de continuidad', null, null],
        'ContinuityReportController@store' => ['Guardó un reporte de continuidad', null, null],
        'OperationalAlertController@scan' => ['Revisó las alertas operativas', null, null],
        'OperationalAlertController@acknowledge' => ['Atendió una alerta operativa', null, null],
        'AppReleaseController@store' => ['Publicó una versión de la app', null, null],
        'AppReleaseController@update' => ['Cambió una versión de la app', 'release', 'id'],
        'AppReleaseController@destroy' => ['Eliminó una versión de la app', 'release', 'id'],
        'ViewAsController@start' => ['Empezó a ver el sistema como otro nivel', null, null],
        'NotificationPreferenceController@update' => ['Cambió sus preferencias de avisos', null, null],
        'AppearancePreferenceController@update' => ['Cambió su apariencia', null, null],
        'ActivityNotificationController@markRead' => ['Marcó un aviso como leído', null, null],
        'ActivityNotificationController@markAllRead' => ['Marcó todos sus avisos como leídos', null, null],
    ];

    /**
     * Importancia para el seguimiento y la retención: las importantes se conservan más tiempo; la rutina, unos días.
     * Lo que no está en ninguna lista es «edición» (altas y cambios comunes).
     */
    private const IMPORTANT = [
        'SelfRegistrationController@approve', 'SelfRegistrationController@reject', 'SelfRegistrationController@updateRule',
        'UserController@destroy', 'UserController@toggleActive', 'UserController@bulk', 'UserController@setGeneralAdmin', 'UserController@store',
        'UserController@importExcel', 'UserController@sendCredentialEmails',
        'ProjectController@destroy', 'ProjectController@bulkDestroy', 'ProposalWorkflowController@review', 'ProposalWorkflowController@updateValidators',
        'SubjectGroupController@destroy', 'SubjectGroupController@removeStudent', 'SubjectGroupController@syncStudents',
        'AsignaturaController@destroy', 'CompetenciaController@destroy', 'DeliverableController@destroy', 'DeliverableController@calificar',
        'EvaluationController@destroy', 'EvaluationController@score', 'EvaluationController@confirmViability', 'EvaluationController@destroyRoom',
        'EvaluationController@archive', 'EvaluationController@archiveSelected', 'EvaluationController@updateManagers',
        'RepositoryController@destroy', 'RepositoryController@publish', 'ArchiveController@archive', 'ArchiveController@restore',
        'AcademicOperationsController@membership', 'AcademicOperationsController@assign', 'AcademicOperationsController@applyAutomation',
        'SemesterManagementController@transition', 'SemesterManagementController@activatePeriod', 'SemesterManagementController@applyPromotion',
        'SemesterManagementController@decideException', 'CourseEnrollmentController@leave',
        'CareerManagementController@storeMembership', 'CareerManagementController@updateMembership', 'CareerManagementController@destroyMembership',
        'CareerManagementController@update', 'CareerModuleController@updateModule', 'SystemSettingController@update',
        'ThesisTrackingController@release', 'ThesisTrackingController@reopen', 'ThesisTrackingController@review', 'ThesisTrackingController@updateReviewers',
        'ProjectTrackingController@adjust', 'ProjectTrackingController@removeAdjustment', 'ProjectTrackingController@extend', 'ProjectTrackingController@unlink',
        'ProjectTrackingController@applyPackage', 'ResidencyController@review', 'ResidencyController@close',
        'DatabaseBackupController@cleanup', 'CareerAuditController@prune', 'ContinuityPolicyController@update',
        'AppReleaseController@store', 'AppReleaseController@update', 'AppReleaseController@destroy', 'ViewAsController@start',
    ];

    private const ROUTINE = [
        'ActivityNotificationController@markRead', 'ActivityNotificationController@markAllRead', 'AppearancePreferenceController@update',
        'NotificationPreferenceController@update', 'EvaluationController@controlRoomTimer', 'EvaluationController@advanceRoom',
        'OperationalAlertController@scan', 'CareerIntegrityController@run', 'AuthController@heartbeat', 'AuthController@logout',
    ];

    public const LEVELS = ['importante' => 'Importante', 'edicion' => 'Edición', 'rutina' => 'Rutina'];

    public static function level(?string $action): string
    {
        $short = preg_replace('/^.*\\\\/', '', (string) $action);
        return in_array($short, self::IMPORTANT, true) ? 'importante' : (in_array($short, self::ROUTINE, true) ? 'rutina' : 'edicion');
    }

    /** Nombres completos de acción (como se guardan en auditoria_carreras.accion) de un nivel. */
    public static function actionsOf(string $level): array
    {
        $list = $level === 'importante' ? self::IMPORTANT : ($level === 'rutina' ? self::ROUTINE : []);
        return array_map(fn ($a) => 'App\\Http\\Controllers\\API\\'.$a, $list);
    }

    /** Personas afectadas (ids de usuario): el objetivo, alumnos o usuarios de acciones masivas y campos de persona. */
    public static function affected(Request $request, array $before, ?array $response): array
    {
        $ids = [];
        if (($before['tipo'] ?? null) === 'user' && ($before['id'] ?? null) !== null) $ids[] = $before['id'];
        if (($before['tipo'] ?? null) === 'registration' && !empty($before['matricula'])) $ids[] = $before['matricula'];
        if (($before['tipo'] ?? null) === 'user' && empty($before['id'])) $ids[] = $response['user']['id'] ?? $response['id'] ?? null;
        foreach (['ids', 'student_ids', 'user_ids'] as $key) {
            if (($before['accion'] ?? '') === 'ProjectController@bulkDestroy') break;
            foreach ((array) $request->input($key, []) as $value) if (is_scalar($value)) $ids[] = $value;
        }
        foreach (['student', 'userId'] as $param) if ($request->route($param) !== null) $ids[] = $request->route($param);
        foreach (['usuario_id', 'user_id', 'student_id', 'estudiante_id', 'docente_id', 'teacher_id'] as $key) {
            if (is_scalar($request->input($key))) $ids[] = $request->input($key);
        }

        return collect($ids)->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (string) $id)->unique()->take(300)->values()->all();
    }

    /** Campos que jamás se registran (sólo se indica que cambiaron, si acaso). */
    private const SECRET = '/pass|contrasena|token|codigo|secret|clave_autoregistro|admin_password/i';

    private const FIELD_LABELS = [
        'nombres' => 'nombre', 'apa' => 'apellido paterno', 'ama' => 'apellido materno', 'apellido_paterno' => 'apellido paterno',
        'apellido_materno' => 'apellido materno', 'email' => 'correo', 'correo' => 'correo', 'telefonos' => 'teléfono', 'telefono' => 'teléfono',
        'perfil_id' => 'perfil', 'semestre' => 'semestre', 'grupo' => 'grupo', 'activo' => 'estado activo', 'especialidad' => 'especialidad',
        'title' => 'título', 'titulo' => 'título', 'description' => 'descripción', 'descripcion' => 'descripción', 'modalidad' => 'modalidad',
        'tipo' => 'tipo', 'estado' => 'estado', 'fecha_limite' => 'fecha límite', 'ponderacion' => 'ponderación', 'calificacion' => 'calificación',
        'comentarios' => 'comentario', 'nombre' => 'nombre', 'grupo_id' => 'grupo', 'subject_group_id' => 'grupo', 'empresa_id' => 'empresa',
        'objetivo_general' => 'objetivo general', 'asignatura_ids' => 'materias', 'student_ids' => 'alumnos', 'advisor_ids' => 'asesores',
        'photo' => 'foto', 'archivo' => 'archivo', 'notas' => 'notas', 'obligatoria' => 'obligatoria', 'modalidades' => 'modalidades',
        'capitulos' => 'capítulos', 'revisores' => 'revisores', 'plan' => 'entregas planeadas', 'items' => 'elementos', 'reason' => 'motivo', 'motivo' => 'motivo',
    ];

    /** Lo que se sabe antes de ejecutar: frase y objetivo (con su nombre, para que sobreviva a una eliminación). */
    public static function before(Request $request): array
    {
        $action = self::actionName($request);
        [$phrase, $type, $param] = self::ACTIONS[$action] ?? [null, null, null];
        $id = $param ? $request->route($param) : null;
        if (is_object($id) && method_exists($id, 'getKey')) $id = $id->getKey();

        $matricula = $type === 'registration' && $id !== null ? DB::table('autoregistros_pendientes')->where('id', $id)->value('matricula') : null;

        return ['accion' => $action, 'frase' => $phrase, 'tipo' => $type, 'id' => is_scalar($id) ? (string) $id : null, 'matricula' => $matricula,
            'objetivo' => $type && $id !== null ? self::label($type, $id) : null];
    }

    /** Descripción final, con el resultado y los detalles de la petición. */
    public static function describe(Request $request, array $before, int $status, ?array $response, ?string $error = null): array
    {
        $phrase = $before['frase'] ?? self::generic($request);
        $target = $before['objetivo'];
        // Altas: el objetivo es lo recién creado (su id viene en la respuesta).
        if (!$target && $before['tipo'] && $status < 400 && $response) {
            $id = $response['id'] ?? $response['user']['id'] ?? $response['data']['id'] ?? $response['project']['id'] ?? $response['deliverable']['id'] ?? null;
            if ($id !== null) $target = self::label($before['tipo'], $id);
        }
        $details = self::details($request, $before['accion'] ?? '', $response);
        $sentence = trim($phrase.($target ? " {$target}" : ''));
        if ($status >= 400) {
            $sentence .= ' — no se completó';
            // Por qué no se completó: el mensaje de error (en validaciones, cada campo con su causa).
            $validation = is_array($response['errors'] ?? null) ? collect($response['errors'])->flatten()->take(3)->implode(' ') : null;
            $reason = $error ?: ($validation ?: ($response['message'] ?? $response['error'] ?? null));
            if ($reason && !collect($details)->contains(fn ($d) => str_contains($d, (string) $reason))) $details[] = 'Motivo: '.mb_substr((string) $reason, 0, 300);
        }

        return ['descripcion' => $sentence, 'objetivo' => $target, 'detalles' => $details, 'nivel' => self::level($before['accion'] ?? null),
            'afectados' => self::affected($request, $before, $response)];
    }

    /** Para registros anteriores a esta mejora: lo que se puede deducir de la acción y los parámetros guardados. */
    public static function fromStored(?string $action, string $method, string $route, array $params, int $status): string
    {
        $short = $action ? preg_replace('/^.*\\\\/', '', $action) : '';
        [$phrase, $type, $param] = self::ACTIONS[$short] ?? [null, null, null];
        $phrase ??= self::verb($method).' '.self::resource($route);
        $id = $param ? ($params[$param] ?? null) : null;
        $target = $type && $id !== null ? self::label($type, $id) : null;
        $sentence = trim($phrase.($target ? " {$target}" : ''));

        return $status >= 400 ? "{$sentence} — no se completó" : $sentence;
    }

    private static function actionName(Request $request): string
    {
        return preg_replace('/^.*\\\\/', '', (string) $request->route()?->getActionName());
    }

    private static function generic(Request $request): string
    {
        return self::verb($request->method()).' '.self::resource('/'.$request->path());
    }

    private static function verb(string $method): string
    {
        return ['POST' => 'Registró', 'PUT' => 'Modificó', 'PATCH' => 'Modificó', 'DELETE' => 'Eliminó'][$method] ?? 'Realizó';
    }

    private static function resource(string $route): string
    {
        $segment = collect(explode('/', trim(preg_replace('#^/?api/#', '', $route), '/')))->reject(fn ($s) => $s === '' || ctype_digit($s))->first() ?? 'registro';

        return 'en '.str_replace('-', ' ', $segment);
    }

    private static function details(Request $request, string $action, ?array $response): array
    {
        $input = $request->except(['_method']);
        $out = [];
        switch ($action) {
            case 'SelfRegistrationController@reject':
                if ($request->filled('reason')) $out[] = 'Motivo: '.mb_substr((string) $request->input('reason'), 0, 300);
                if (!empty($response['message'])) $out[] = 'Respuesta: '.mb_substr((string) $response['message'], 0, 200);
                return $out;
            case 'SelfRegistrationController@approve':
                $out[] = 'Se creó su cuenta de alumno';
                break;
            case 'UserController@bulk':
                $labels = ['deactivate' => 'desactivar', 'activate' => 'reactivar', 'archive' => 'enviar al archivo', 'delete' => 'eliminar'];
                $ids = (array) $request->input('ids', []);
                $out[] = 'Acción: '.($labels[$request->input('action')] ?? $request->input('action')).' · '.count($ids).' usuario(s)';
                if ($ids) $out[] = 'Usuarios: '.self::userList($ids);
                if ($request->filled('motivo')) $out[] = 'Motivo: '.mb_substr((string) $request->input('motivo'), 0, 300);
                return $out;
            case 'ProjectController@bulkDestroy':
                $ids = (array) $request->input('ids', $request->input('project_ids', []));
                $out[] = count($ids).' proyecto(s): '.self::projectList($ids);
                return $out;
            case 'ProposalWorkflowController@review':
                $out[] = 'Decisión: '.str_replace('_', ' ', (string) $request->input('proposal_status'));
                if ($request->filled('proposal_review_comment')) $out[] = 'Comentario: '.mb_substr((string) $request->input('proposal_review_comment'), 0, 300);
                return $out;
            case 'ProposalWorkflowController@answerJoin':
                $out[] = 'Respuesta: '.(string) $request->input('decision');
                return $out;
            case 'DeliverableController@calificar':
                $project = $request->input('project_id') ? self::label('project', $request->input('project_id')) : null;
                $out[] = 'Calificación: '.$request->input('calificacion').($project ? " · equipo {$project}" : '');
                return $out;
            case 'SubjectGroupController@syncStudents':
                $out[] = 'Alumnos: '.self::userList((array) $request->input('student_ids', []));
                return $out;
            case 'SubjectGroupController@removeStudent':
                $out[] = 'Alumno: '.self::label('user', $request->route('student'));
                return $out;
            case 'UserController@toggleActive':
                $active = DB::table('usuarios')->where('id', $request->route('id'))->value('activo');
                if ($active !== null) $out[] = 'Quedó '.($active ? 'activo' : 'inactivo');
                break;
        }
        $fields = collect(array_keys($input))->reject(fn ($key) => preg_match(self::SECRET, $key) || str_starts_with($key, '_'))
            ->map(fn ($key) => self::FIELD_LABELS[$key] ?? str_replace('_', ' ', $key))->unique()->values();
        if ($fields->isNotEmpty() && in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $out[] = 'Datos enviados: '.$fields->take(12)->implode(', ').($fields->count() > 12 ? '…' : '');
        }
        if (collect(array_keys($input))->contains(fn ($key) => preg_match('/^(password|contrasena)$/i', $key))) $out[] = 'Incluyó cambio de contraseña';
        if (!empty($response['message']) && is_string($response['message'])) $out[] = 'Respuesta: '.mb_substr($response['message'], 0, 200);

        return $out;
    }

    private static function userList(array $ids): string
    {
        $ids = array_slice(array_values(array_filter($ids, 'is_scalar')), 0, 15);
        $names = DB::table('usuarios')->whereIn('id', $ids)->get(['id', 'nombres', 'apellido_paterno'])->keyBy(fn ($u) => (string) $u->id);
        $list = collect($ids)->map(fn ($id) => ($u = $names->get((string) $id)) ? trim("{$u->nombres} {$u->apellido_paterno}")." ({$id})" : (string) $id);

        return $list->implode(', ');
    }

    private static function projectList(array $ids): string
    {
        return DB::table('proyectos')->whereIn('id', array_slice($ids, 0, 10))->pluck('titulo')->map(fn ($t) => "«{$t}»")->implode(', ');
    }

    /** Nombre legible del objetivo. Si no se encuentra, el tipo y su id. */
    public static function label(string $type, mixed $id): ?string
    {
        if ($id === null || $id === '') return null;
        try {
            $value = match ($type) {
                'user' => ($u = DB::table('usuarios')->where('id', $id)->first(['nombres', 'apellido_paterno', 'apellido_materno']))
                    ? trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}")." ({$id})" : null,
                'registration' => ($r = DB::table('autoregistros_pendientes')->where('id', $id)->first(['matricula', 'datos']))
                    ? (($d = json_decode((string) $r->datos, true) ?: []) ? trim(($d['nombres'] ?? '').' '.($d['apa'] ?? '').' '.($d['ama'] ?? '')) : '')." ({$r->matricula})" : null,
                'project' => ($t = DB::table('proyectos')->where('id', $id)->value('titulo')) ? "«{$t}»" : null,
                'group' => ($g = DB::table('grupos_academicos')->where('id', $id)->first(['semestre', 'clave_grupo', 'nombre'])) ? "{$g->semestre}° {$g->clave_grupo} ({$g->nombre})" : null,
                'subject' => ($a = DB::table('asignaturas')->where('id', $id)->first(['nombre', 'clave'])) ? "«{$a->nombre}» ({$a->clave})" : null,
                'deliverable' => ($n = DB::table('entregables')->where('id', $id)->value('nombre')) ? "«{$n}»" : null,
                'evaluation' => ($t = DB::table('evaluaciones as e')->join('proyectos as p', 'p.id', '=', 'e.proyecto_id')->where('e.id', $id)->value('p.titulo')) ? "«{$t}»" : null,
                'room' => ($n = DB::table('salas_evaluacion')->where('id', $id)->value('nombre')) ? "«{$n}»" : null,
                'document' => ($t = DB::table('documentos')->where('id', $id)->value('titulo')) ? "«{$t}»" : null,
                'course' => ($c = DB::table('cursos as c')->join('asignaturas as a', 'a.id', '=', 'c.asignatura_id')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
                    ->where('c.id', $id)->first(['a.nombre', 'g.semestre', 'g.clave_grupo'])) ? "«{$c->nombre}» de {$c->semestre}° {$c->clave_grupo}" : null,
                'thesis' => ($t = DB::table('tesis_cursos')->where('id', $id)->value('titulo')) ? "«{$t}»" : null,
                'period' => ($n = DB::table('periodos_academicos')->where('id', $id)->value('nombre')) ? $n : null,
                'career' => ($n = DB::table('carreras')->where('id', $id)->value('nombre_corto')) ? $n : null,
                'release' => ($n = DB::table('app_versiones')->where('id', $id)->value('version_name')) ? $n : null,
                default => null,
            };
        } catch (\Throwable) {
            $value = null;
        }

        return $value ?: "#{$id}";
    }
}
