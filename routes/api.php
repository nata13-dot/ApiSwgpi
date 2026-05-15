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

// ========================
// AUTENTICACIÓN (sin protección)
// ========================
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/settings/public', [SystemSettingController::class, 'public']);

// ========================
// RUTAS PÚBLICAS (sin JWT)
// ========================
Route::prefix('repositorio')->group(function () {
    Route::get('/', [RepositoryController::class, 'index']);
    Route::get('/buscar', [RepositoryController::class, 'search']);
    Route::get('/proyecto/{projectId}', [RepositoryController::class, 'byProject']);
    Route::get('/etiqueta/{tagId}', [RepositoryController::class, 'byTag']);
    Route::get('/{id}/download', [RepositoryController::class, 'download']);
    Route::get('/{id}/view', [RepositoryController::class, 'view']);
    Route::get('/{id}', [RepositoryController::class, 'show']);
});

// ========================
// RUTAS PROTEGIDAS (con JWT)
// ========================
Route::middleware(['auth:api', 'active'])->group(function () {
    
    // Evaluaciones (solo docentes y administradores)
    Route::middleware('role:admin,teacher')->group(function () {
        Route::get('/evaluations/criteria', [EvaluationController::class, 'criteria']);
        Route::post('/evaluations/rubric-criteria', [EvaluationController::class, 'storeCriterion']);
        Route::put('/evaluations/rubric-criteria/{id}', [EvaluationController::class, 'updateCriterion']);
        Route::delete('/evaluations/rubric-criteria/{id}', [EvaluationController::class, 'destroyCriterion']);
        Route::get('/evaluations/projects', [EvaluationController::class, 'projects']);
        Route::get('/evaluations/rooms', [EvaluationController::class, 'rooms']);
        Route::post('/evaluations/rooms', [EvaluationController::class, 'storeRoom']);
        Route::put('/evaluations/rooms/{id}', [EvaluationController::class, 'updateRoom']);
        Route::delete('/evaluations/rooms/{id}', [EvaluationController::class, 'destroyRoom']);
        Route::get('/evaluations', [EvaluationController::class, 'index']);
        Route::post('/evaluations', [EvaluationController::class, 'store']);
        Route::get('/evaluations/{id}', [EvaluationController::class, 'show']);
        Route::put('/evaluations/{id}', [EvaluationController::class, 'update']);
        Route::delete('/evaluations/{id}', [EvaluationController::class, 'destroy']);
        Route::post('/evaluations/{id}/score', [EvaluationController::class, 'score']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/teacher', [DashboardController::class, 'teacher']);
    Route::get('/dashboard/student', [DashboardController::class, 'student']);

    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

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

    // Users (solo Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/proposal/config', [ProposalWorkflowController::class, 'configIndex']);
        Route::post('/proposal/windows', [ProposalWorkflowController::class, 'storeWindow']);
        Route::put('/proposal/windows/{id}', [ProposalWorkflowController::class, 'updateWindow']);
        Route::delete('/proposal/windows/{id}', [ProposalWorkflowController::class, 'destroyWindow']);
        Route::post('/proposal/assignments', [ProposalWorkflowController::class, 'storeAssignment']);
        Route::delete('/proposal/assignments/{id}', [ProposalWorkflowController::class, 'destroyAssignment']);
        Route::get('/settings', [SystemSettingController::class, 'index']);
        Route::put('/settings', [SystemSettingController::class, 'update']);
        Route::get('/notices', [SystemSettingController::class, 'notices']);
        Route::put('/notices', [SystemSettingController::class, 'updateNotices']);
        Route::get('/settings/semester-preview', [SystemSettingController::class, 'semesterPreview']);
        Route::post('/settings/apply-semester-change', [SystemSettingController::class, 'applySemesterChange']);
        Route::post('/repositorio', [RepositoryController::class, 'store']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users-template.csv', [UserController::class, 'blankCsvTemplate']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::get('/users-inactive', [UserController::class, 'getInactive']);
    });

    // Cargas de asignaturas por semestre/grupo
    Route::get('/subject-groups', [SubjectGroupController::class, 'index']);
    Route::get('/subject-groups/{id}', [SubjectGroupController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/subject-groups', [SubjectGroupController::class, 'store']);
        Route::put('/subject-groups/{id}', [SubjectGroupController::class, 'update']);
        Route::delete('/subject-groups/{id}', [SubjectGroupController::class, 'destroy']);
        Route::get('/subject-groups/{id}/students', [SubjectGroupController::class, 'students']);
        Route::post('/subject-groups/{id}/students', [SubjectGroupController::class, 'syncStudents']);
    });

    // Projects (CRUD)
    Route::get('/my-projects', [ProjectController::class, 'myProjects']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/advisors', [ProjectController::class, 'addAdvisor']);
    Route::post('/projects/{id}/asignaturas', [ProjectController::class, 'syncAsignaturas']);
    Route::delete('/projects/{projectId}/advisors/{userId}', [ProjectController::class, 'removeAdvisor']);

    // Deliverables (CRUD + Calificación + Upload + Descarga)
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
    Route::middleware('role:admin')->group(function () {
        Route::post('/asignaturas', [AsignaturaController::class, 'store']);
        Route::put('/asignaturas/{id}', [AsignaturaController::class, 'update']);
        Route::delete('/asignaturas/{id}', [AsignaturaController::class, 'destroy']);
    });
    
    // Competencias (solo Admin puede crear/editar)
    Route::get('/competencias', [CompetenciaController::class, 'index']);
    Route::get('/competencias/{id}', [CompetenciaController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
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
