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

// ========================
// AUTENTICACIÓN (sin protección)
// ========================
Route::post('/auth/login', [AuthController::class, 'login']);

// ========================
// RUTAS PÚBLICAS (sin JWT)
// ========================
Route::prefix('repositorio')->group(function () {
    Route::get('/', [RepositoryController::class, 'index']);
    Route::get('/buscar', [RepositoryController::class, 'search']);
    Route::get('/proyecto/{projectId}', [RepositoryController::class, 'byProject']);
    Route::get('/etiqueta/{tagId}', [RepositoryController::class, 'byTag']);
    Route::get('/{id}', [RepositoryController::class, 'show']);
});

// ========================
// RUTAS PROTEGIDAS (con JWT)
// ========================
Route::middleware('auth:api')->group(function () {
    
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Users (solo Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::get('/users-inactive', [UserController::class, 'getInactive']);
    });

    // Projects (CRUD)
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/advisors', [ProjectController::class, 'addAdvisor']);
    Route::delete('/projects/{projectId}/advisors/{userId}', [ProjectController::class, 'removeAdvisor']);

    // Deliverables (CRUD)
    Route::get('/deliverables', [DeliverableController::class, 'index']);
    Route::post('/deliverables', [DeliverableController::class, 'store']);
    Route::get('/deliverables/{id}', [DeliverableController::class, 'show']);
    Route::put('/deliverables/{id}', [DeliverableController::class, 'update']);
    Route::delete('/deliverables/{id}', [DeliverableController::class, 'destroy']);

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
