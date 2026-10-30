# Catálogo completo de rutas

Laravel registró **229 rutas**, de las cuales **223** llevan el prefijo `/api`. Se obtuvo con `php artisan route:list --json`. Un registro `GET|HEAD` cuenta como una ruta, no dos. `GET|POST|HEAD` representa una sola declaración con varios verbos.

Los middleware aquí son los declarados en el registro de rutas; las capas globales de `bootstrap/app.php` y los controles de los métodos se explican en el informe. Una ruta sin `auth:api` puede comprobar autenticación opcional en su controlador. Que exista un endpoint no significa que habilite la operación: varios conservan compatibilidad y devuelven 409 o 422.

**Precedencia comprobada:** `GET /api/users/credential-email-template` coincide en la colección no compilada con `UserController@show`, debido al registro previo de `/users/{id}`. La tabla muestra la acción declarada; este caso no debe confundirse con la acción que realmente se alcanza.

## AcademicOperationsController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 2 | GET\|HEAD | `/api/academic-operations/authorizations` | [AcademicOperationsController@authorizations](controladores/AcademicOperationsController.md#authorizations) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 4 | GET\|HEAD | `/api/academic-operations/catalog` | [AcademicOperationsController@catalog](controladores/AcademicOperationsController.md#catalog) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 5 | POST | `/api/academic-operations/classrooms` | [AcademicOperationsController@classroom](controladores/AcademicOperationsController.md#classroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 6 | GET\|HEAD | `/api/academic-operations/enrollments` | [AcademicOperationsController@enrollments](controladores/AcademicOperationsController.md#enrollments) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 7 | POST | `/api/academic-operations/enrollments` | [AcademicOperationsController@assign](controladores/AcademicOperationsController.md#assign) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 8 | PUT | `/api/academic-operations/memberships/{membership}` | [AcademicOperationsController@membership](controladores/AcademicOperationsController.md#membership) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 9 | GET\|HEAD | `/api/academic-operations/memberships/{membership}/commitments` | [AcademicOperationsController@commitments](controladores/AcademicOperationsController.md#commitments) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 10 | GET\|POST\|HEAD | `/api/academic-operations/periods/{period}/reconciliation` | [AcademicOperationsController@reconciliation](controladores/AcademicOperationsController.md#reconciliation) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 11 | POST | `/api/academic-operations/project-period` | [AcademicOperationsController@projectPeriod](controladores/AcademicOperationsController.md#projectperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## ActivityNotificationController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 12 | GET\|HEAD | `/api/activity-notifications` | [ActivityNotificationController@index](controladores/ActivityNotificationController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 13 | PUT | `/api/activity-notifications/read-all` | [ActivityNotificationController@markAllRead](controladores/ActivityNotificationController.md#markallread) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 14 | PUT | `/api/activity-notifications/{notification}/read` | [ActivityNotificationController@markRead](controladores/ActivityNotificationController.md#markread) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## AsignaturaController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 42 | GET\|HEAD | `/api/asignaturas` | [AsignaturaController@index](controladores/AsignaturaController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 43 | POST | `/api/asignaturas` | [AsignaturaController@store](controladores/AsignaturaController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 44 | GET\|HEAD | `/api/asignaturas/{id}` | [AsignaturaController@show](controladores/AsignaturaController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 45 | PUT | `/api/asignaturas/{id}` | [AsignaturaController@update](controladores/AsignaturaController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 46 | DELETE | `/api/asignaturas/{id}` | [AsignaturaController@destroy](controladores/AsignaturaController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
## AuthController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 47 | GET\|HEAD | `/api/auth/careers` | [AuthController@careers](controladores/AuthController.md#careers) | api, Authenticate:api, EnsureUserIsActive |
| 48 | POST | `/api/auth/heartbeat` | [AuthController@heartbeat](controladores/AuthController.md#heartbeat) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 49 | POST | `/api/auth/login` | [AuthController@login](controladores/AuthController.md#login) | api, ThrottleRequests:login |
| 50 | POST | `/api/auth/logout` | [AuthController@logout](controladores/AuthController.md#logout) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 51 | GET\|HEAD | `/api/auth/me` | [AuthController@me](controladores/AuthController.md#me) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 52 | POST | `/api/auth/password/request-token` | [AuthController@requestPasswordReset](controladores/AuthController.md#requestpasswordreset) | api, ThrottleRequests:password-recovery |
| 53 | POST | `/api/auth/password/reset` | [AuthController@resetPasswordWithToken](controladores/AuthController.md#resetpasswordwithtoken) | api, ThrottleRequests:password-recovery |
| 54 | POST | `/api/auth/password/verify-token` | [AuthController@verifyPasswordResetToken](controladores/AuthController.md#verifypasswordresettoken) | api, ThrottleRequests:password-recovery |
| 55 | POST | `/api/auth/refresh` | [AuthController@refresh](controladores/AuthController.md#refresh) | api |
| 56 | POST | `/api/auth/switch-career` | [AuthController@switchCareer](controladores/AuthController.md#switchcareer) | api, Authenticate:api, EnsureUserIsActive |
## CareerAuditController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 15 | GET\|HEAD | `/api/admin/audit` | [CareerAuditController@index](controladores/CareerAuditController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## CareerExportController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 21 | GET\|HEAD | `/api/admin/careers/export` | [CareerExportController@institutionalSummary](controladores/CareerExportController.md#institutionalsummary) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 57 | GET\|HEAD | `/api/career/export` | [CareerExportController@activeCareer](controladores/CareerExportController.md#activecareer) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
## CareerIntegrityController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 35 | GET\|HEAD | `/api/admin/integrity` | [CareerIntegrityController@index](controladores/CareerIntegrityController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 36 | GET\|HEAD | `/api/admin/integrity/history` | [CareerIntegrityController@history](controladores/CareerIntegrityController.md#history) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 37 | POST | `/api/admin/integrity/run` | [CareerIntegrityController@run](controladores/CareerIntegrityController.md#run) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## CareerManagementController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 16 | GET\|HEAD | `/api/admin/career-memberships` | [CareerManagementController@memberships](controladores/CareerManagementController.md#memberships) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 17 | POST | `/api/admin/career-memberships` | [CareerManagementController@storeMembership](controladores/CareerManagementController.md#storemembership) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 18 | PUT | `/api/admin/career-memberships/{membership}` | [CareerManagementController@updateMembership](controladores/CareerManagementController.md#updatemembership) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 19 | DELETE | `/api/admin/career-memberships/{membership}` | [CareerManagementController@destroyMembership](controladores/CareerManagementController.md#destroymembership) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 20 | GET\|HEAD | `/api/admin/careers` | [CareerManagementController@index](controladores/CareerManagementController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 22 | PUT | `/api/admin/careers/{career}` | [CareerManagementController@update](controladores/CareerManagementController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## CareerModuleController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 58 | POST | `/api/career/indicators` | [CareerModuleController@storeIndicator](controladores/CareerModuleController.md#storeindicator) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 59 | PUT | `/api/career/indicators/{indicator}` | [CareerModuleController@updateIndicator](controladores/CareerModuleController.md#updateindicator) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 60 | DELETE | `/api/career/indicators/{indicator}` | [CareerModuleController@destroyIndicator](controladores/CareerModuleController.md#destroyindicator) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 61 | GET\|HEAD | `/api/career/module-records` | [CareerModuleController@records](controladores/CareerModuleController.md#records) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 62 | POST | `/api/career/module-records` | [CareerModuleController@storeRecord](controladores/CareerModuleController.md#storerecord) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 63 | PUT | `/api/career/module-records/{record}` | [CareerModuleController@updateRecord](controladores/CareerModuleController.md#updaterecord) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 64 | DELETE | `/api/career/module-records/{record}` | [CareerModuleController@destroyRecord](controladores/CareerModuleController.md#destroyrecord) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 65 | GET\|HEAD | `/api/career/modules` | [CareerModuleController@index](controladores/CareerModuleController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 66 | PUT | `/api/career/modules/{module}` | [CareerModuleController@updateModule](controladores/CareerModuleController.md#updatemodule) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
## CareerSetupController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 67 | POST | `/api/career/setup/catalog` | [CareerSetupController@importCatalog](controladores/CareerSetupController.md#importcatalog) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
## Closure

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 1 | GET\|HEAD | `//` | `Closure` | web |
| 227 | GET\|HEAD | `/storage/{path}` | `Closure` | Sin middleware de ruta |
| 228 | PUT | `/storage/{path}` | `Closure` | Sin middleware de ruta |
| 229 | GET\|HEAD | `/up` | `Closure` | Sin middleware de ruta |
## CompanyController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 68 | GET\|HEAD | `/api/companies` | [CompanyController@index](controladores/CompanyController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 69 | PUT | `/api/companies/{company}/review` | [CompanyController@review](controladores/CompanyController.md#review) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |
## CompetenciaController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 70 | GET\|HEAD | `/api/competencias` | [CompetenciaController@index](controladores/CompetenciaController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 71 | POST | `/api/competencias` | [CompetenciaController@store](controladores/CompetenciaController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 72 | GET\|HEAD | `/api/competencias/{id}` | [CompetenciaController@show](controladores/CompetenciaController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 73 | PUT | `/api/competencias/{id}` | [CompetenciaController@update](controladores/CompetenciaController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 74 | DELETE | `/api/competencias/{id}` | [CompetenciaController@destroy](controladores/CompetenciaController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
## ContinuityPolicyController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 25 | GET\|HEAD | `/api/admin/continuity-policy` | [ContinuityPolicyController@show](controladores/ContinuityPolicyController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 26 | PUT | `/api/admin/continuity-policy` | [ContinuityPolicyController@update](controladores/ContinuityPolicyController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## ContinuityReportController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 23 | GET\|HEAD | `/api/admin/continuity-history` | [ContinuityReportController@history](controladores/ContinuityReportController.md#history) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 24 | POST | `/api/admin/continuity-history` | [ContinuityReportController@store](controladores/ContinuityReportController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 27 | GET\|HEAD | `/api/admin/continuity-report` | [ContinuityReportController@show](controladores/ContinuityReportController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 28 | GET\|HEAD | `/api/admin/continuity-report.pdf` | [ContinuityReportController@pdf](controladores/ContinuityReportController.md#pdf) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## CourseEnrollmentController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 75 | GET\|HEAD | `/api/course-enrollments` | [CourseEnrollmentController@index](controladores/CourseEnrollmentController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 76 | DELETE | `/api/course-enrollments/{course}` | [CourseEnrollmentController@leave](controladores/CourseEnrollmentController.md#leave) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 77 | POST | `/api/course-enrollments/{course}` | [CourseEnrollmentController@enroll](controladores/CourseEnrollmentController.md#enroll) | api, Authenticate:api, EnsureUserIsActive, ThrottleRequests:10,1, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:student |
| 78 | PUT | `/api/courses/{course}/self-enrollment` | [CourseEnrollmentController@updateAccess](controladores/CourseEnrollmentController.md#updateaccess) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:teacher |
| 79 | GET\|HEAD | `/api/courses/{course}/students` | [CourseEnrollmentController@students](controladores/CourseEnrollmentController.md#students) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## CsrfCookieController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 226 | GET\|HEAD | `/sanctum/csrf-cookie` | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` | web |
## DashboardController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 80 | GET\|HEAD | `/api/dashboard/stats` | [DashboardController@stats](controladores/DashboardController.md#stats) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 81 | GET\|HEAD | `/api/dashboard/student` | [DashboardController@student](controladores/DashboardController.md#student) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 82 | GET\|HEAD | `/api/dashboard/teacher` | [DashboardController@teacher](controladores/DashboardController.md#teacher) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## DatabaseBackupController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 29 | GET\|HEAD | `/api/admin/database-backups` | [DatabaseBackupController@index](controladores/DatabaseBackupController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 30 | POST | `/api/admin/database-backups` | [DatabaseBackupController@store](controladores/DatabaseBackupController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 31 | POST | `/api/admin/database-backups-cleanup` | [DatabaseBackupController@cleanup](controladores/DatabaseBackupController.md#cleanup) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 32 | GET\|HEAD | `/api/admin/database-backups-health` | [DatabaseBackupController@health](controladores/DatabaseBackupController.md#health) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 33 | GET\|HEAD | `/api/admin/database-backups/{backup}/download` | [DatabaseBackupController@download](controladores/DatabaseBackupController.md#download) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 34 | POST | `/api/admin/database-backups/{backup}/verify` | [DatabaseBackupController@verify](controladores/DatabaseBackupController.md#verify) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## DeliverableController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 83 | GET\|HEAD | `/api/deliverables` | [DeliverableController@index](controladores/DeliverableController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 84 | POST | `/api/deliverables` | [DeliverableController@store](controladores/DeliverableController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 85 | GET\|HEAD | `/api/deliverables/{id}` | [DeliverableController@show](controladores/DeliverableController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 86 | PUT | `/api/deliverables/{id}` | [DeliverableController@update](controladores/DeliverableController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 87 | DELETE | `/api/deliverables/{id}` | [DeliverableController@destroy](controladores/DeliverableController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 88 | POST | `/api/deliverables/{id}/calificar` | [DeliverableController@calificar](controladores/DeliverableController.md#calificar) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 89 | GET\|HEAD | `/api/deliverables/{id}/download` | [DeliverableController@download](controladores/DeliverableController.md#download) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 90 | POST | `/api/deliverables/{id}/upload` | [DeliverableController@upload](controladores/DeliverableController.md#upload) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 96 | GET\|HEAD | `/api/evaluation-documents` | [DeliverableController@evaluationDocuments](controladores/DeliverableController.md#evaluationdocuments) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 132 | GET\|HEAD | `/api/my-deliverables` | [DeliverableController@myDeliverables](controladores/DeliverableController.md#mydeliverables) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 212 | GET\|HEAD | `/api/teacher/deliverables-matrix` | [DeliverableController@teacherMatrix](controladores/DeliverableController.md#teachermatrix) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## DocumentTagController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 91 | GET\|HEAD | `/api/document-tags` | [DocumentTagController@index](controladores/DocumentTagController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 92 | POST | `/api/document-tags` | [DocumentTagController@store](controladores/DocumentTagController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 93 | GET\|HEAD | `/api/document-tags/{id}` | [DocumentTagController@show](controladores/DocumentTagController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 94 | PUT | `/api/document-tags/{id}` | [DocumentTagController@update](controladores/DocumentTagController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 95 | DELETE | `/api/document-tags/{id}` | [DocumentTagController@destroy](controladores/DocumentTagController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
## EvaluationController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 97 | GET\|HEAD | `/api/evaluation-managers` | [EvaluationController@managers](controladores/EvaluationController.md#managers) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 98 | PUT | `/api/evaluation-managers` | [EvaluationController@updateManagers](controladores/EvaluationController.md#updatemanagers) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 99 | GET\|HEAD | `/api/evaluations` | [EvaluationController@index](controladores/EvaluationController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 100 | POST | `/api/evaluations` | [EvaluationController@store](controladores/EvaluationController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 101 | POST | `/api/evaluations/archive-selected` | [EvaluationController@archiveSelected](controladores/EvaluationController.md#archiveselected) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 102 | GET\|HEAD | `/api/evaluations/criteria` | [EvaluationController@criteria](controladores/EvaluationController.md#criteria) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 103 | GET\|HEAD | `/api/evaluations/managers` | [EvaluationController@managers](controladores/EvaluationController.md#managers) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 104 | PUT | `/api/evaluations/managers` | [EvaluationController@updateManagers](controladores/EvaluationController.md#updatemanagers) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 105 | GET\|HEAD | `/api/evaluations/projects` | [EvaluationController@projects](controladores/EvaluationController.md#projects) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 106 | GET\|HEAD | `/api/evaluations/rooms` | [EvaluationController@rooms](controladores/EvaluationController.md#rooms) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 107 | POST | `/api/evaluations/rooms` | [EvaluationController@storeRoom](controladores/EvaluationController.md#storeroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 108 | PUT | `/api/evaluations/rooms/{id}` | [EvaluationController@updateRoom](controladores/EvaluationController.md#updateroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 109 | DELETE | `/api/evaluations/rooms/{id}` | [EvaluationController@destroyRoom](controladores/EvaluationController.md#destroyroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 110 | POST | `/api/evaluations/rooms/{id}/advance` | [EvaluationController@advanceRoom](controladores/EvaluationController.md#advanceroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 111 | POST | `/api/evaluations/rooms/{id}/archive` | [EvaluationController@archiveRoom](controladores/EvaluationController.md#archiveroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 112 | POST | `/api/evaluations/rooms/{id}/lock-sequence` | [EvaluationController@lockRoomSequence](controladores/EvaluationController.md#lockroomsequence) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 113 | GET\|HEAD | `/api/evaluations/rooms/{id}/report.pdf` | [EvaluationController@exportRoomPdf](controladores/EvaluationController.md#exportroompdf) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 114 | PUT | `/api/evaluations/rooms/{id}/schedule` | [EvaluationController@updateRoomSchedule](controladores/EvaluationController.md#updateroomschedule) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 115 | POST | `/api/evaluations/rooms/{id}/timer` | [EvaluationController@controlRoomTimer](controladores/EvaluationController.md#controlroomtimer) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 116 | POST | `/api/evaluations/rooms/{id}/unarchive` | [EvaluationController@unarchiveRoom](controladores/EvaluationController.md#unarchiveroom) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 117 | POST | `/api/evaluations/rubric-criteria` | [EvaluationController@storeCriterion](controladores/EvaluationController.md#storecriterion) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 118 | PUT | `/api/evaluations/rubric-criteria/{id}` | [EvaluationController@updateCriterion](controladores/EvaluationController.md#updatecriterion) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 119 | DELETE | `/api/evaluations/rubric-criteria/{id}` | [EvaluationController@destroyCriterion](controladores/EvaluationController.md#destroycriterion) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 120 | PUT | `/api/evaluations/rubric-score-modes` | [EvaluationController@updateRubricScoreModes](controladores/EvaluationController.md#updaterubricscoremodes) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 121 | POST | `/api/evaluations/rubrics/initialize` | [EvaluationController@initializeRubrics](controladores/EvaluationController.md#initializerubrics) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 122 | POST | `/api/evaluations/unarchive-selected` | [EvaluationController@unarchiveSelected](controladores/EvaluationController.md#unarchiveselected) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 123 | GET\|HEAD | `/api/evaluations/{id}` | [EvaluationController@show](controladores/EvaluationController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 124 | PUT | `/api/evaluations/{id}` | [EvaluationController@update](controladores/EvaluationController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 125 | DELETE | `/api/evaluations/{id}` | [EvaluationController@destroy](controladores/EvaluationController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 126 | POST | `/api/evaluations/{id}/archive` | [EvaluationController@archive](controladores/EvaluationController.md#archive) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 127 | POST | `/api/evaluations/{id}/feedback` | [EvaluationController@feedback](controladores/EvaluationController.md#feedback) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 128 | POST | `/api/evaluations/{id}/mark-completed` | [EvaluationController@markCompleted](controladores/EvaluationController.md#markcompleted) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 129 | GET\|HEAD | `/api/evaluations/{id}/report.pdf` | [EvaluationController@exportEvaluationPdf](controladores/EvaluationController.md#exportevaluationpdf) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 130 | POST | `/api/evaluations/{id}/score` | [EvaluationController@score](controladores/EvaluationController.md#score) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 131 | POST | `/api/evaluations/{id}/unarchive` | [EvaluationController@unarchive](controladores/EvaluationController.md#unarchive) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 204 | GET\|HEAD | `/api/student/evaluation-schedule` | [EvaluationController@studentSchedule](controladores/EvaluationController.md#studentschedule) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## OperationalAlertController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 38 | GET\|HEAD | `/api/admin/operational-alerts` | [OperationalAlertController@index](controladores/OperationalAlertController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 39 | POST | `/api/admin/operational-alerts/scan` | [OperationalAlertController@scan](controladores/OperationalAlertController.md#scan) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 40 | GET\|HEAD | `/api/admin/operational-alerts/summary` | [OperationalAlertController@summary](controladores/OperationalAlertController.md#summary) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| 41 | PUT | `/api/admin/operational-alerts/{alert}/acknowledge` | [OperationalAlertController@acknowledge](controladores/OperationalAlertController.md#acknowledge) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
## ProfileController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 136 | GET\|HEAD | `/api/profile` | [ProfileController@show](controladores/ProfileController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 137 | POST | `/api/profile` | [ProfileController@update](controladores/ProfileController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 138 | POST | `/api/profile/complete-initial` | [ProfileController@completeInitial](controladores/ProfileController.md#completeinitial) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## ProjectController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 133 | GET\|HEAD | `/api/my-projects` | [ProjectController@myProjects](controladores/ProjectController.md#myprojects) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 139 | GET\|HEAD | `/api/projects` | [ProjectController@index](controladores/ProjectController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 140 | POST | `/api/projects` | [ProjectController@store](controladores/ProjectController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:student,project_manager |
| 141 | GET\|HEAD | `/api/projects-template.xls` | [ProjectController@projectsExcelTemplate](controladores/ProjectController.md#projectsexceltemplate) | api |
| 142 | POST | `/api/projects/import-excel` | [ProjectController@importExcel](controladores/ProjectController.md#importexcel) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 143 | GET\|HEAD | `/api/projects/{id}` | [ProjectController@show](controladores/ProjectController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 144 | PUT | `/api/projects/{id}` | [ProjectController@update](controladores/ProjectController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:student,project_manager |
| 145 | DELETE | `/api/projects/{id}` | [ProjectController@destroy](controladores/ProjectController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |
| 146 | POST | `/api/projects/{id}/advisors` | [ProjectController@addAdvisor](controladores/ProjectController.md#addadvisor) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |
| 147 | PUT | `/api/projects/{id}/advisors` | [ProjectController@syncAdvisors](controladores/ProjectController.md#syncadvisors) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |
| 148 | POST | `/api/projects/{id}/asignaturas` | [ProjectController@syncAsignaturas](controladores/ProjectController.md#syncasignaturas) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |
| 149 | DELETE | `/api/projects/{projectId}/advisors/{userId}` | [ProjectController@removeAdvisor](controladores/ProjectController.md#removeadvisor) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |
## ProposalWorkflowController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 150 | POST | `/api/proposal/assignments` | [ProposalWorkflowController@storeAssignment](controladores/ProposalWorkflowController.md#storeassignment) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 151 | DELETE | `/api/proposal/assignments/{id}` | [ProposalWorkflowController@destroyAssignment](controladores/ProposalWorkflowController.md#destroyassignment) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 152 | GET\|HEAD | `/api/proposal/config` | [ProposalWorkflowController@configIndex](controladores/ProposalWorkflowController.md#configindex) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 153 | POST | `/api/proposal/exceptions` | [ProposalWorkflowController@storeException](controladores/ProposalWorkflowController.md#storeexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 154 | DELETE | `/api/proposal/exceptions/{id}` | [ProposalWorkflowController@destroyException](controladores/ProposalWorkflowController.md#destroyexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 155 | POST | `/api/proposal/projects/{id}/review` | [ProposalWorkflowController@review](controladores/ProposalWorkflowController.md#review) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 156 | GET\|HEAD | `/api/proposal/student-status` | [ProposalWorkflowController@studentStatus](controladores/ProposalWorkflowController.md#studentstatus) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 157 | GET\|HEAD | `/api/proposal/students/search` | [ProposalWorkflowController@searchStudents](controladores/ProposalWorkflowController.md#searchstudents) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 158 | GET\|HEAD | `/api/proposal/teacher-projects` | [ProposalWorkflowController@teacherProjects](controladores/ProposalWorkflowController.md#teacherprojects) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 159 | GET\|HEAD | `/api/proposal/window-groups` | [ProposalWorkflowController@windowGroups](controladores/ProposalWorkflowController.md#windowgroups) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 160 | POST | `/api/proposal/windows` | [ProposalWorkflowController@storeWindow](controladores/ProposalWorkflowController.md#storewindow) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 161 | PUT | `/api/proposal/windows/{id}` | [ProposalWorkflowController@updateWindow](controladores/ProposalWorkflowController.md#updatewindow) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
| 162 | DELETE | `/api/proposal/windows/{id}` | [ProposalWorkflowController@destroyWindow](controladores/ProposalWorkflowController.md#destroywindow) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,teacher,project_manager |
## RepositoryController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 163 | GET\|HEAD | `/api/repositorio` | [RepositoryController@index](controladores/RepositoryController.md#index) | api |
| 164 | POST | `/api/repositorio` | [RepositoryController@store](controladores/RepositoryController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 165 | GET\|HEAD | `/api/repositorio/admin/list` | [RepositoryController@adminIndex](controladores/RepositoryController.md#adminindex) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 166 | GET\|HEAD | `/api/repositorio/buscar` | [RepositoryController@search](controladores/RepositoryController.md#search) | api |
| 167 | GET\|HEAD | `/api/repositorio/etiqueta/{tagId}` | [RepositoryController@byTag](controladores/RepositoryController.md#bytag) | api |
| 168 | GET\|HEAD | `/api/repositorio/evaluation-documents` | [RepositoryController@evaluationDocuments](controladores/RepositoryController.md#evaluationdocuments) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureAcademicPeriodOpen |
| 169 | POST | `/api/repositorio/evaluation-documents` | [RepositoryController@storeEvaluationDocument](controladores/RepositoryController.md#storeevaluationdocument) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureAcademicPeriodOpen |
| 170 | PUT | `/api/repositorio/evaluation-documents/{id}/release-status` | [RepositoryController@reviewEvaluationRelease](controladores/RepositoryController.md#reviewevaluationrelease) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureAcademicPeriodOpen |
| 171 | GET\|HEAD | `/api/repositorio/proyecto/{projectId}` | [RepositoryController@byProject](controladores/RepositoryController.md#byproject) | api |
| 172 | GET\|HEAD | `/api/repositorio/student/list` | [RepositoryController@studentIndex](controladores/RepositoryController.md#studentindex) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 173 | GET\|HEAD | `/api/repositorio/thesis-documents` | [RepositoryController@thesisDocuments](controladores/RepositoryController.md#thesisdocuments) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureAcademicPeriodOpen |
| 174 | POST | `/api/repositorio/thesis-documents` | [RepositoryController@storeThesisDocument](controladores/RepositoryController.md#storethesisdocument) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureAcademicPeriodOpen |
| 175 | GET\|HEAD | `/api/repositorio/{id}` | [RepositoryController@show](controladores/RepositoryController.md#show) | api |
| 176 | POST | `/api/repositorio/{id}` | [RepositoryController@update](controladores/RepositoryController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 177 | DELETE | `/api/repositorio/{id}` | [RepositoryController@destroy](controladores/RepositoryController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 178 | GET\|HEAD | `/api/repositorio/{id}/download` | [RepositoryController@download](controladores/RepositoryController.md#download) | api |
| 179 | POST | `/api/repositorio/{id}/publish` | [RepositoryController@publish](controladores/RepositoryController.md#publish) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 180 | GET\|HEAD | `/api/repositorio/{id}/view` | [RepositoryController@view](controladores/RepositoryController.md#view) | api |
## ResidencyController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 181 | GET\|HEAD | `/api/residencies` | [ResidencyController@index](controladores/ResidencyController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 182 | POST | `/api/residencies` | [ResidencyController@store](controladores/ResidencyController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 183 | PUT | `/api/residencies/{residency}/advisor` | [ResidencyController@advisor](controladores/ResidencyController.md#advisor) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 184 | POST | `/api/residencies/{residency}/close` | [ResidencyController@close](controladores/ResidencyController.md#close) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 185 | POST | `/api/residencies/{residency}/requirements` | [ResidencyController@requirement](controladores/ResidencyController.md#requirement) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 186 | POST | `/api/residencies/{residency}/requirements/{requirement}/versions` | [ResidencyController@upload](controladores/ResidencyController.md#upload) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 187 | GET\|HEAD | `/api/residencies/{residency}/versions/{version}/download` | [ResidencyController@download](controladores/ResidencyController.md#download) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 188 | PUT | `/api/residencies/{residency}/versions/{version}/review` | [ResidencyController@review](controladores/ResidencyController.md#review) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
## SemesterManagementController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 3 | POST | `/api/academic-operations/authorizations` | [SemesterManagementController@storeException](controladores/SemesterManagementController.md#storeexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:teacher |
| 189 | GET\|HEAD | `/api/semester-management` | [SemesterManagementController@summary](controladores/SemesterManagementController.md#summary) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 190 | POST | `/api/semester-management/exceptions` | [SemesterManagementController@storeException](controladores/SemesterManagementController.md#storeexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 191 | DELETE | `/api/semester-management/exceptions/{exception}` | [SemesterManagementController@destroyException](controladores/SemesterManagementController.md#destroyexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 192 | PUT | `/api/semester-management/exceptions/{exception}/decision` | [SemesterManagementController@decideException](controladores/SemesterManagementController.md#decideexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 193 | POST | `/api/semester-management/periods` | [SemesterManagementController@storePeriod](controladores/SemesterManagementController.md#storeperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 194 | PUT | `/api/semester-management/periods/{period}` | [SemesterManagementController@updatePeriod](controladores/SemesterManagementController.md#updateperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 195 | POST | `/api/semester-management/periods/{period}/activate` | [SemesterManagementController@activatePeriod](controladores/SemesterManagementController.md#activateperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 196 | POST | `/api/semester-management/periods/{period}/promote` | [SemesterManagementController@applyPromotion](controladores/SemesterManagementController.md#applypromotion) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 197 | GET\|HEAD | `/api/semester-management/periods/{period}/promotion-preview` | [SemesterManagementController@promotionPreview](controladores/SemesterManagementController.md#promotionpreview) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 198 | POST | `/api/semester-management/periods/{period}/transition` | [SemesterManagementController@transition](controladores/SemesterManagementController.md#transition) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:academic_manager |
| 199 | GET\|HEAD | `/api/semester-management/search` | [SemesterManagementController@search](controladores/SemesterManagementController.md#search) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
## SubjectGroupController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 205 | GET\|HEAD | `/api/subject-groups` | [SubjectGroupController@index](controladores/SubjectGroupController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 206 | POST | `/api/subject-groups` | [SubjectGroupController@store](controladores/SubjectGroupController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 207 | GET\|HEAD | `/api/subject-groups/{id}` | [SubjectGroupController@show](controladores/SubjectGroupController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 208 | PUT | `/api/subject-groups/{id}` | [SubjectGroupController@update](controladores/SubjectGroupController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 209 | DELETE | `/api/subject-groups/{id}` | [SubjectGroupController@destroy](controladores/SubjectGroupController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 210 | GET\|HEAD | `/api/subject-groups/{id}/students` | [SubjectGroupController@students](controladores/SubjectGroupController.md#students) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| 211 | POST | `/api/subject-groups/{id}/students` | [SubjectGroupController@syncStudents](controladores/SubjectGroupController.md#syncstudents) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
## SystemSettingController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 134 | GET\|HEAD | `/api/notices` | [SystemSettingController@notices](controladores/SystemSettingController.md#notices) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 135 | PUT | `/api/notices` | [SystemSettingController@updateNotices](controladores/SystemSettingController.md#updatenotices) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 200 | GET\|HEAD | `/api/settings` | [SystemSettingController@index](controladores/SystemSettingController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 201 | PUT | `/api/settings` | [SystemSettingController@update](controladores/SystemSettingController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| 202 | GET\|HEAD | `/api/settings/current` | [SystemSettingController@current](controladores/SystemSettingController.md#current) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| 203 | GET\|HEAD | `/api/settings/public` | [SystemSettingController@public](controladores/SystemSettingController.md#public) | api |
## UserController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 213 | GET\|HEAD | `/api/users` | [UserController@index](controladores/UserController.md#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,project_manager |
| 214 | POST | `/api/users` | [UserController@store](controladores/UserController.md#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 215 | GET\|HEAD | `/api/users-inactive` | [UserController@getInactive](controladores/UserController.md#getinactive) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 216 | GET\|HEAD | `/api/users-template.csv` | [UserController@blankCsvTemplate](controladores/UserController.md#blankcsvtemplate) | api |
| 217 | GET\|HEAD | `/api/users-template.xls` | [UserController@usersExcelTemplate](controladores/UserController.md#usersexceltemplate) | api |
| 218 | GET\|HEAD | `/api/users/credential-email-template` | [UserController@credentialEmailTemplate](controladores/UserController.md#credentialemailtemplate) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 219 | POST | `/api/users/import-excel` | [UserController@importExcel](controladores/UserController.md#importexcel) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 220 | POST | `/api/users/send-credentials` | [UserController@sendCredentialEmails](controladores/UserController.md#sendcredentialemails) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 221 | GET\|HEAD | `/api/users/{id}` | [UserController@show](controladores/UserController.md#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 222 | PUT | `/api/users/{id}` | [UserController@update](controladores/UserController.md#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 223 | DELETE | `/api/users/{id}` | [UserController@destroy](controladores/UserController.md#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
| 224 | POST | `/api/users/{id}/toggle-active` | [UserController@toggleActive](controladores/UserController.md#toggleactive) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:user_governance |
## WebhookController

| # | Verbo | URI completa | Acción declarada | Middleware |
|---:|---|---|---|---|
| 225 | POST | `/resend/webhook` | `Resend\Laravel\Http\Controllers\WebhookController@handleWebhook` | Sin middleware de ruta |
