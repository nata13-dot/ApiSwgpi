<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\DeliverableController;
use App\Http\Controllers\API\RepositoryController;
use App\Http\Controllers\API\AsignaturaController;
use App\Http\Controllers\API\CompetenciaController;
use App\Http\Controllers\API\DocumentTagController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\EvaluationController;
use App\Http\Controllers\API\SubjectGroupController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ProposalWorkflowController;
use App\Http\Controllers\API\SystemSettingController;
use App\Http\Controllers\API\ActivityNotificationController;
use App\Http\Controllers\API\SemesterManagementController;
use App\Http\Controllers\API\CareerManagementController;
use App\Http\Controllers\API\CareerModuleController;
use App\Http\Controllers\API\CareerSetupController;
use App\Http\Controllers\API\CareerAuditController;
use App\Http\Controllers\API\CareerExportController;
use App\Http\Controllers\API\CareerIntegrityController;
use App\Http\Controllers\API\DatabaseBackupController;
use App\Http\Controllers\API\OperationalAlertController;
use App\Http\Controllers\API\ContinuityReportController;
use App\Http\Controllers\API\ContinuityPolicyController;

// ========================
// AUTENTICACIÓN (sin protección)
// ========================
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/password/request-token', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:password-recovery');
Route::post('/auth/password/verify-token', [AuthController::class, 'verifyPasswordResetToken'])->middleware('throttle:password-recovery');
Route::post('/auth/password/reset', [AuthController::class, 'resetPasswordWithToken'])->middleware('throttle:password-recovery');
Route::get('/settings/public', [SystemSettingController::class, 'public']);
Route::get('/users-template.csv', [UserController::class, 'blankCsvTemplate']);
Route::get('/users-template.xls', [UserController::class, 'usersExcelTemplate']);
Route::get('/projects-template.xls', [ProjectController::class, 'projectsExcelTemplate']);

// ========================
// RUTAS PÚBLICAS (sin JWT)
// ========================
Route::prefix('repositorio')->group(function () {
    Route::get('/', [RepositoryController::class, 'index']);
    Route::get('/buscar', [RepositoryController::class, 'search']);
    Route::get('/proyecto/{projectId}', [RepositoryController::class, 'byProject']);
    Route::get('/etiqueta/{tagId}', [RepositoryController::class, 'byTag']);
    Route::middleware(['auth:api', 'active', 'career'])->group(function () {
        Route::get('/evaluation-documents', [RepositoryController::class, 'evaluationDocuments']);
        Route::post('/evaluation-documents', [RepositoryController::class, 'storeEvaluationDocument']);
        Route::put('/evaluation-documents/{id}/release-status', [RepositoryController::class, 'reviewEvaluationRelease']);
        Route::get('/thesis-documents', [RepositoryController::class, 'thesisDocuments']);
        Route::post('/thesis-documents', [RepositoryController::class, 'storeThesisDocument']);
    });
    Route::get('/{id}/download', [RepositoryController::class, 'download']);
    Route::get('/{id}/view', [RepositoryController::class, 'view']);
    Route::get('/{id}', [RepositoryController::class, 'show']);
});

// Estas rutas no exigen un contexto vigente para permitir recuperarse si la
// membresía de la carrera que estaba activa fue revocada.
Route::middleware(['auth:api', 'active'])->group(function () {
    Route::get('/auth/careers', [AuthController::class, 'careers']);
    Route::post('/auth/switch-career', [AuthController::class, 'switchCareer']);
});

// ========================
// RUTAS PROTEGIDAS (con JWT)
// ========================
Route::middleware(['auth:api', 'active', 'career', 'career.module', 'audit'])->group(function () {
    Route::get('/career/modules', [CareerModuleController::class, 'index']);
    Route::get('/career/module-records', [CareerModuleController::class, 'records']);
    Route::middleware('role:admin')->group(function () {
        Route::get('/career/export', [CareerExportController::class, 'activeCareer']);
        Route::post('/career/setup/catalog', [CareerSetupController::class, 'importCatalog']);
        Route::put('/career/modules/{module}', [CareerModuleController::class, 'updateModule']);
        Route::post('/career/module-records', [CareerModuleController::class, 'storeRecord']);
        Route::put('/career/module-records/{record}', [CareerModuleController::class, 'updateRecord']);
        Route::delete('/career/module-records/{record}', [CareerModuleController::class, 'destroyRecord']);
        Route::post('/career/indicators', [CareerModuleController::class, 'storeIndicator']);
        Route::put('/career/indicators/{indicator}', [CareerModuleController::class, 'updateIndicator']);
        Route::delete('/career/indicators/{indicator}', [CareerModuleController::class, 'destroyIndicator']);
    });
    Route::middleware('role:general_admin')->prefix('admin')->group(function () {
        Route::get('/operational-alerts', [OperationalAlertController::class, 'index']);
        Route::get('/operational-alerts/summary', [OperationalAlertController::class, 'summary']);
        Route::post('/operational-alerts/scan', [OperationalAlertController::class, 'scan']);
        Route::put('/operational-alerts/{alert}/acknowledge', [OperationalAlertController::class, 'acknowledge']);
        Route::get('/continuity-report', [ContinuityReportController::class, 'show']);
        Route::get('/continuity-report.pdf', [ContinuityReportController::class, 'pdf']);
        Route::get('/continuity-history', [ContinuityReportController::class, 'history']);
        Route::post('/continuity-history', [ContinuityReportController::class, 'store']);
        Route::get('/continuity-policy', [ContinuityPolicyController::class, 'show']);
        Route::put('/continuity-policy', [ContinuityPolicyController::class, 'update']);
        Route::get('/integrity', [CareerIntegrityController::class, 'index']);
        Route::post('/integrity/run', [CareerIntegrityController::class, 'run']);
        Route::get('/integrity/history', [CareerIntegrityController::class, 'history']);
        Route::get('/database-backups', [DatabaseBackupController::class, 'index']);
        Route::get('/database-backups-health', [DatabaseBackupController::class, 'health']);
        Route::post('/database-backups', [DatabaseBackupController::class, 'store']);
        Route::post('/database-backups-cleanup', [DatabaseBackupController::class, 'cleanup']);
        Route::post('/database-backups/{backup}/verify', [DatabaseBackupController::class, 'verify']);
        Route::get('/database-backups/{backup}/download', [DatabaseBackupController::class, 'download']);
        Route::get('/careers/export', [CareerExportController::class, 'institutionalSummary']);
        Route::get('/audit', [CareerAuditController::class, 'index']);
        Route::get('/careers', [CareerManagementController::class, 'index']);
        Route::put('/careers/{career}', [CareerManagementController::class, 'update']);
        Route::get('/career-memberships', [CareerManagementController::class, 'memberships']);
        Route::post('/career-memberships', [CareerManagementController::class, 'storeMembership']);
        Route::put('/career-memberships/{membership}', [CareerManagementController::class, 'updateMembership']);
        Route::delete('/career-memberships/{membership}', [CareerManagementController::class, 'destroyMembership']);
    });
    
    // Evaluaciones (solo docentes y administradores)
    Route::middleware('role:admin,teacher,project_manager')->group(function () {
        Route::get('/evaluation-managers', [EvaluationController::class, 'managers']);
        Route::put('/evaluation-managers', [EvaluationController::class, 'updateManagers']);
        Route::get('/evaluations/managers', [EvaluationController::class, 'managers']);
        Route::put('/evaluations/managers', [EvaluationController::class, 'updateManagers']);
        Route::get('/evaluations/criteria', [EvaluationController::class, 'criteria']);
        Route::post('/evaluations/rubrics/initialize', [EvaluationController::class, 'initializeRubrics']);
        Route::put('/evaluations/rubric-score-modes', [EvaluationController::class, 'updateRubricScoreModes']);
        Route::post('/evaluations/rubric-criteria', [EvaluationController::class, 'storeCriterion']);
        Route::put('/evaluations/rubric-criteria/{id}', [EvaluationController::class, 'updateCriterion']);
        Route::delete('/evaluations/rubric-criteria/{id}', [EvaluationController::class, 'destroyCriterion']);
        Route::get('/evaluations/projects', [EvaluationController::class, 'projects']);
        Route::get('/evaluations/rooms', [EvaluationController::class, 'rooms']);
        Route::post('/evaluations/rooms', [EvaluationController::class, 'storeRoom']);
        Route::put('/evaluations/rooms/{id}', [EvaluationController::class, 'updateRoom']);
        Route::delete('/evaluations/rooms/{id}', [EvaluationController::class, 'destroyRoom']);
        Route::post('/evaluations/rooms/{id}/archive', [EvaluationController::class, 'archiveRoom']);
        Route::post('/evaluations/rooms/{id}/unarchive', [EvaluationController::class, 'unarchiveRoom']);
        Route::post('/evaluations/rooms/{id}/lock-sequence', [EvaluationController::class, 'lockRoomSequence']);
        Route::post('/evaluations/rooms/{id}/advance', [EvaluationController::class, 'advanceRoom']);
        Route::get('/evaluations/rooms/{id}/report.pdf', [EvaluationController::class, 'exportRoomPdf']);
        Route::get('/evaluations', [EvaluationController::class, 'index']);
        Route::post('/evaluations', [EvaluationController::class, 'store']);
        Route::post('/evaluations/archive-selected', [EvaluationController::class, 'archiveSelected']);
        Route::post('/evaluations/unarchive-selected', [EvaluationController::class, 'unarchiveSelected']);
        Route::get('/evaluations/{id}', [EvaluationController::class, 'show']);
        Route::get('/evaluations/{id}/report.pdf', [EvaluationController::class, 'exportEvaluationPdf']);
        Route::put('/evaluations/{id}', [EvaluationController::class, 'update']);
        Route::delete('/evaluations/{id}', [EvaluationController::class, 'destroy']);
        Route::post('/evaluations/{id}/archive', [EvaluationController::class, 'archive']);
        Route::post('/evaluations/{id}/unarchive', [EvaluationController::class, 'unarchive']);
        Route::post('/evaluations/{id}/score', [EvaluationController::class, 'score']);
        Route::post('/evaluations/{id}/mark-completed', [EvaluationController::class, 'markCompleted']);
        Route::post('/evaluations/{id}/feedback', [EvaluationController::class, 'feedback']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/teacher', [DashboardController::class, 'teacher']);
    Route::get('/dashboard/student', [DashboardController::class, 'student']);
    Route::get('/settings/current', [SystemSettingController::class, 'current']);

    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/heartbeat', [AuthController::class, 'heartbeat']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Notificaciones personales de actividad
    Route::get('/activity-notifications', [ActivityNotificationController::class, 'index']);
    Route::put('/activity-notifications/read-all', [ActivityNotificationController::class, 'markAllRead']);
    Route::put('/activity-notifications/{notification}/read', [ActivityNotificationController::class, 'markRead']);

    // Perfil propio
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/complete-initial', [ProfileController::class, 'completeInitial']);

    // Flujo de propuestas
    Route::get('/proposal/student-status', [ProposalWorkflowController::class, 'studentStatus']);
    Route::get('/student/evaluation-schedule', [EvaluationController::class, 'studentSchedule']);
    Route::get('/proposal/students/search', [ProposalWorkflowController::class, 'searchStudents']);
    Route::get('/proposal/teacher-projects', [ProposalWorkflowController::class, 'teacherProjects']);
    Route::post('/proposal/projects/{id}/review', [ProposalWorkflowController::class, 'review']);
    Route::middleware('role:admin,teacher,project_manager')->group(function () {
        Route::get('/proposal/window-groups', [ProposalWorkflowController::class, 'windowGroups']);
        Route::post('/proposal/windows', [ProposalWorkflowController::class, 'storeWindow']);
        Route::put('/proposal/windows/{id}', [ProposalWorkflowController::class, 'updateWindow']);
        Route::delete('/proposal/windows/{id}', [ProposalWorkflowController::class, 'destroyWindow']);
    });
    Route::get('/repositorio/student/list', [RepositoryController::class, 'studentIndex']);
    Route::post('/repositorio', [RepositoryController::class, 'store']);

    // Coordinación de proyectos: administrador, jefatura, asistencia y coordinación.
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::get('/proposal/config', [ProposalWorkflowController::class, 'configIndex']);
        Route::post('/proposal/assignments', [ProposalWorkflowController::class, 'storeAssignment']);
        Route::delete('/proposal/assignments/{id}', [ProposalWorkflowController::class, 'destroyAssignment']);
        Route::post('/proposal/exceptions', [ProposalWorkflowController::class, 'storeException']);
        Route::delete('/proposal/exceptions/{id}', [ProposalWorkflowController::class, 'destroyException']);
        Route::get('/users', [UserController::class, 'index']);
    });

    // Operación académica: administrador, jefatura y asistencia de jefatura.
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::get('/semester-management', [SemesterManagementController::class, 'summary']);
        Route::get('/semester-management/search', [SemesterManagementController::class, 'search']);
        Route::post('/semester-management/periods', [SemesterManagementController::class, 'storePeriod']);
        Route::put('/semester-management/periods/{period}', [SemesterManagementController::class, 'updatePeriod']);
        Route::post('/semester-management/periods/{period}/activate', [SemesterManagementController::class, 'activatePeriod']);
        Route::get('/semester-management/periods/{period}/promotion-preview', [SemesterManagementController::class, 'promotionPreview']);
        Route::post('/semester-management/periods/{period}/promote', [SemesterManagementController::class, 'applyPromotion']);
        Route::post('/semester-management/exceptions', [SemesterManagementController::class, 'storeException']);
        Route::delete('/semester-management/exceptions/{exception}', [SemesterManagementController::class, 'destroyException']);
    });

    // Controles administrativos críticos.
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SystemSettingController::class, 'index']);
        Route::put('/settings', [SystemSettingController::class, 'update']);
        Route::get('/notices', [SystemSettingController::class, 'notices']);
        Route::put('/notices', [SystemSettingController::class, 'updateNotices']);
        Route::get('/repositorio/admin/list', [RepositoryController::class, 'adminIndex']);
        Route::post('/repositorio/{id}', [RepositoryController::class, 'update']);
        Route::post('/repositorio/{id}/publish', [RepositoryController::class, 'publish']);
        Route::delete('/repositorio/{id}', [RepositoryController::class, 'destroy']);
    });

    // Gobierno de identidades: exclusivamente Administrador General.
    Route::middleware('role:user_governance')->group(function () {
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::get('/users/credential-email-template', [UserController::class, 'credentialEmailTemplate']);
        Route::post('/users/send-credentials', [UserController::class, 'sendCredentialEmails']);
        Route::post('/users/import-excel', [UserController::class, 'importExcel']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::get('/users-inactive', [UserController::class, 'getInactive']);
    });

    // Cargas de asignaturas por semestre/grupo
    Route::get('/subject-groups', [SubjectGroupController::class, 'index']);
    Route::get('/subject-groups/{id}', [SubjectGroupController::class, 'show']);
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::post('/subject-groups', [SubjectGroupController::class, 'store']);
        Route::put('/subject-groups/{id}', [SubjectGroupController::class, 'update']);
        Route::delete('/subject-groups/{id}', [SubjectGroupController::class, 'destroy']);
        Route::get('/subject-groups/{id}/students', [SubjectGroupController::class, 'students']);
        Route::post('/subject-groups/{id}/students', [SubjectGroupController::class, 'syncStudents']);
    });

    // Projects (CRUD)
    Route::get('/my-projects', [ProjectController::class, 'myProjects']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::post('/projects/import-excel', [ProjectController::class, 'importExcel']);
    });
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/advisors', [ProjectController::class, 'addAdvisor']);
    Route::put('/projects/{id}/advisors', [ProjectController::class, 'syncAdvisors']);
    Route::post('/projects/{id}/asignaturas', [ProjectController::class, 'syncAsignaturas']);
    Route::delete('/projects/{projectId}/advisors/{userId}', [ProjectController::class, 'removeAdvisor']);

    // Deliverables (CRUD + Calificación + Upload + Descarga)
    Route::get('/teacher/deliverables-matrix', [DeliverableController::class, 'teacherMatrix']);
    Route::get('/evaluation-documents', [DeliverableController::class, 'evaluationDocuments']);
    Route::get('/my-deliverables', [DeliverableController::class, 'myDeliverables']);
    Route::get('/deliverables', [DeliverableController::class, 'index']);
    Route::post('/deliverables', [DeliverableController::class, 'store']);
    Route::get('/deliverables/{id}', [DeliverableController::class, 'show']);
    Route::put('/deliverables/{id}', [DeliverableController::class, 'update']);
    Route::delete('/deliverables/{id}', [DeliverableController::class, 'destroy']);
    
    // Endpoints adicionales de entregas
    Route::post('/deliverables/{id}/calificar', [DeliverableController::class, 'calificar']);
    Route::post('/deliverables/{id}/upload', [DeliverableController::class, 'upload']);
    Route::get('/deliverables/{id}/download', [DeliverableController::class, 'download']);

    // Asignaturas (solo Admin puede crear/editar)
    Route::get('/asignaturas', [AsignaturaController::class, 'index']);
    Route::get('/asignaturas/{id}', [AsignaturaController::class, 'show']);
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::post('/asignaturas', [AsignaturaController::class, 'store']);
        Route::put('/asignaturas/{id}', [AsignaturaController::class, 'update']);
        Route::delete('/asignaturas/{id}', [AsignaturaController::class, 'destroy']);
    });
    
    // Competencias (solo Admin puede crear/editar)
    Route::get('/competencias', [CompetenciaController::class, 'index']);
    Route::get('/competencias/{id}', [CompetenciaController::class, 'show']);
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::post('/competencias', [CompetenciaController::class, 'store']);
        Route::put('/competencias/{id}', [CompetenciaController::class, 'update']);
        Route::delete('/competencias/{id}', [CompetenciaController::class, 'destroy']);
    });
    
    // Document Tags (solo Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/document-tags', [DocumentTagController::class, 'index']);
        Route::post('/document-tags', [DocumentTagController::class, 'store']);
        Route::get('/document-tags/{id}', [DocumentTagController::class, 'show']);
        Route::put('/document-tags/{id}', [DocumentTagController::class, 'update']);
        Route::delete('/document-tags/{id}', [DocumentTagController::class, 'destroy']);
    });
});
