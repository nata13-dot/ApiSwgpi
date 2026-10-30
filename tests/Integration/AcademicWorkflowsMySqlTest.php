<?php
namespace Tests\Integration;

use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\Course;
use App\Models\EvaluationRoom;
use App\Models\User;
use App\Services\AcademicWorkflowService;
use App\Services\AcademicPromotionService;
use App\Services\AcademicAuthorizationService;
use App\Services\CourseAdmissionService;
use App\Services\EvaluationAgendaService;
use App\Support\CareerContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AcademicWorkflowsMySqlTest extends TestCase
{
    private static bool $installed = false;
    private bool $committedForConcurrency = false;

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('SGPI_TEST_MYSQL_SOCKET');
        if (!$socket) $this->markTestSkipped('Requires an isolated MySQL socket via SGPI_TEST_MYSQL_SOCKET.');
        config(['database.default' => 'academic_test', 'database.connections.academic_test' => [
            'driver' => 'mysql', 'unix_socket' => $socket, 'database' => 'sgpi_test_academic', 'username' => 'root',
            'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ]]);
        DB::purge('academic_test');
        if (!self::$installed) {
            // Deliberately restricted to a test-only database name and explicitly supplied socket.
            $this->assertSame('sgpi_test_academic', DB::connection()->getDatabaseName());
            DB::unprepared(file_get_contents(base_path('tests/Fixtures/academic-base.mysql.sql')));
            (require database_path('migrations/2026_09_02_000001_add_shared_academic_workflows.php'))->up();
            (require database_path('migrations/2026_09_10_000001_add_storage_metadata_to_documento_versiones.php'))->up();
            (require database_path('migrations/2026_10_13_000001_consolidate_academic_workflows.php'))->up();
            (require database_path('migrations/2026_10_25_000001_normalize_relational_integrity.php'))->up();
            (require database_path('migrations/2026_10_26_000001_remove_redundant_career_copies.php'))->up();
            (require database_path('migrations/2026_10_27_000001_simplify_user_registration.php'))->up();
            (require database_path('migrations/2026_10_28_000001_profile_levels.php'))->up();
            (require database_path('migrations/2026_10_29_000001_evaluation_deliverables.php'))->up();
            (require database_path('migrations/2026_10_29_000002_evaluation_deliverable_reviews.php'))->up();
            (require database_path('migrations/2026_10_30_000001_criterion_scales.php'))->up();
            self::$installed = true;
        }
        DB::beginTransaction();
        $this->seedAcademic();
        $this->acting('head', 1, 5);
        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\ResolveCareerContext::class,
            \App\Http\Middleware\CheckRole::class,
            \App\Http\Middleware\EnsureCareerModuleEnabled::class,
            \App\Http\Middleware\AuditCareerMutation::class,
            \App\Http\Middleware\EnsureAcademicPeriodOpen::class,
        ]); // Keep real route bindings and validation with an explicit authenticated context.
    }

    protected function tearDown(): void
    {
        if (getenv('SGPI_TEST_MYSQL_SOCKET')) {
            while (DB::transactionLevel()) DB::rollBack();
            if ($this->committedForConcurrency) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) DB::statement('TRUNCATE TABLE `'.array_values((array) $row)[0].'`');
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
        parent::tearDown();
    }

    public function test_diagnostics_recognizes_migrated_schema_without_mutating_data(): void
    {
        $before = DB::table('bitacora_academica')->count();
        $report = app(\App\Services\AcademicDiagnosticsService::class)->report();
        $this->assertSame([], $report['missing_baseline']);
        $this->assertSame([], $report['missing_consolidation']);
        $this->assertTrue($report['baseline_ready']);
        $this->assertSame($before, DB::table('bitacora_academica')->count());
    }

    public function test_mixed_course_team_records_only_enrolled_participants_and_protects_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('swgpi_private');
        \Illuminate\Support\Facades\Storage::fake('local');
        DB::table('usuarios')->insert(['id' => 'peer', 'nombres' => 'Peer', 'contrasena' => Hash::make('testing-password')]);
        DB::table('usuario_carrera')->insert(['usuario_id' => 'peer', 'carrera_id' => 1, 'perfil_id' => 3]);
        DB::table('matriculas_academicas')->insert(['estudiante_id' => 'peer', 'grupo_id' => 1]);
        DB::table('proyecto_integrantes')->insert(['proyecto_id' => 1, 'usuario_id' => 'peer', 'rol' => 'integrante']);
        DB::table('asignaturas')->insert(['id' => 2, 'carrera_id' => 1, 'clave' => 'B', 'nombre' => 'Otra materia']);
        DB::table('cursos')->insert(['id' => 2, 'grupo_id' => 1, 'asignatura_id' => 2]);
        DB::table('curso_estudiantes')->insert([['curso_id' => 1, 'estudiante_id' => 'student'], ['curso_id' => 2, 'estudiante_id' => 'peer']]);
        DB::table('entregables')->insert(['id' => 1, 'curso_id' => 1, 'nombre' => 'Avance', 'tipo_documento' => 'documento']);
        $path = tempnam(sys_get_temp_dir(), 'sgpi-test-pdf');
        file_put_contents($path, "%PDF-1.7\nAvance\n");
        try {
            $file = new \Illuminate\Http\UploadedFile($path, 'avance.pdf', 'application/pdf', UPLOAD_ERR_OK, true);
            $student = $this->acting('student', 1, 3);
            app(\App\Services\DeliverySubmissionService::class)->submit(
                \App\Models\Deliverable::findOrFail(1), \App\Models\Project::findOrFail(1), $student, $file, ['pdf'], 50);
            $this->assertSame(['student'], DB::table('entrega_participantes')->pluck('estudiante_id')->all());
            $this->deleteJson('/api/course-enrollments/1', ['reason' => 'Cambio'])->assertConflict();
            $this->get('/api/deliverables/1/download?project_id=1')->assertOk();
            $this->acting('peer', 1, 3);
            $this->getJson('/api/deliverables/1')->assertForbidden();
            $this->get('/api/deliverables/1/download?project_id=1')->assertForbidden();
            $this->post('/api/deliverables/1/upload', ['project_id' => 1, 'archivo' => $file], ['Accept' => 'application/json'])->assertForbidden();
        } finally { @unlink($path); }
    }

    private function seedAcademic(): void
    {
        // La migración de niveles ya creó los perfiles 2–7; el perfil 1 (Administrador) ya no existe.
        foreach (\App\Support\Profiles::LEVELS as $id => [$level]) DB::table('perfiles')->updateOrInsert(['id' => $id], ['nivel' => $level, 'nombre' => 'Perfil '.$id]);
        foreach ([1,2] as $id) DB::table('carreras')->insert(['id' => $id, 'clave' => 'C'.$id, 'slug' => 'c'.$id, 'nombre' => 'Carrera '.$id, 'nombre_corto' => 'C'.$id]);
        foreach (['head' => 5, 'student' => 3, 'teacher' => 7, 'foreign' => 2] as $id => $profile) {
            DB::table('usuarios')->insert(['id' => $id, 'nombres' => $id, 'contrasena' => Hash::make('testing-password')]);
            DB::table('usuario_carrera')->insert(['usuario_id' => $id, 'carrera_id' => $id === 'foreign' ? 2 : 1, 'perfil_id' => $id === 'teacher' ? 2 : $profile]);
        }
        foreach ([1,2] as $id) DB::table('periodos_academicos')->insert(['id' => $id, 'nombre' => $id === 1 ? 'Periodo actual' : 'Destino libre', 'fecha_inicio' => now()->startOfDay()->addMonths(($id-1)*6)->subMonth(), 'fecha_fin' => now()->addMonths($id*6)]);
        foreach ([[1,1,1,5],[2,1,2,6],[3,2,1,5],[4,1,1,6],[5,1,1,9]] as [$id,$career,$period,$semester]) DB::table('grupos_academicos')->insert(['id' => $id, 'carrera_id' => $career, 'periodo_id' => $period, 'nombre' => 'Grupo '.$id, 'semestre' => $semester, 'clave_grupo' => 'A']);
        foreach ([1,2] as $career) DB::table('carrera_periodos')->insert(['carrera_id' => $career, 'periodo_id' => 1, 'estado' => 'abierto', 'conciliado' => true]);
        DB::table('matriculas_academicas')->insert(['estudiante_id' => 'student', 'grupo_id' => 1]);
        DB::table('asignaturas')->insert(['id' => 1, 'carrera_id' => 1, 'clave' => 'A', 'nombre' => 'Materia']);
        DB::table('cursos')->insert(['id' => 1, 'grupo_id' => 1, 'asignatura_id' => 1, 'es_seguimiento_proyecto' => true, 'clave_autoregistro' => Hash::make('course-key'), 'inscripcion_desde' => now()->subDay(), 'inscripcion_hasta' => now()->addDay()]);
        DB::table('curso_docentes')->insert(['curso_id' => 1, 'docente_id' => 'teacher']);
        DB::table('empresas')->insert(['id' => 1, 'nombre' => 'Empresa de prueba', 'estado_validacion' => 'aprobada']);
        foreach ([[1,1,1],[2,2,3],[3,1,1]] as [$id,$career,$group]) DB::table('proyectos')->insert(['id' => $id, 'grupo_id' => $group, 'titulo' => 'Proyecto '.$id, 'empresa_id' => 1]);
        DB::table('proyecto_integrantes')->insert([['proyecto_id' => 1, 'usuario_id' => 'student', 'rol' => 'lider'], ['proyecto_id' => 1, 'usuario_id' => 'teacher', 'rol' => 'primario']]);
        foreach ([1,2] as $id) DB::table('rubricas')->insert(['id' => $id, 'carrera_id' => $id, 'nombre' => 'Rubrica', 'etapa' => 'propuesta', 'semestre' => 5]);
        DB::table('criterios_rubrica')->insert(['id' => 1, 'rubrica_id' => 1, 'clave' => 'problema', 'pregunta' => 'Pregunta original', 'orden' => 1]);
        DB::table('salones_institucionales')->insert(['id' => 1, 'nombre' => 'Aula 1', 'clave' => 'aula 1']);
    }

    private function acting(string $id, int $career, int $profile): User
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);
        app(CareerContext::class)->set($user, Career::findOrFail($career), $profile);
        auth('api')->setUser($user);
        return $user;
    }

    public function test_cross_career_authorization_is_rejected_and_global_profile_does_not_control_teacher_eligibility(): void
    {
        $this->assertSame('teacher', app(AcademicWorkflowService::class)->teacher('teacher')->id);
        $this->postJson('/api/academic-operations/authorizations', $this->authorization(['project_id' => 2]))->assertNotFound();
        $this->assertDatabaseCount('autorizaciones_academicas', 0);
    }

    public function test_profile_phone_update_preserves_enrollment_and_rejects_semester_change(): void
    {
        $this->acting('student', 1, 3);
        $this->postJson('/api/profile', ['telefonos' => '5555555555'])->assertOk();
        $this->assertDatabaseHas('matriculas_academicas', ['estudiante_id' => 'student', 'grupo_id' => 1, 'estado' => 'activa']);
        $this->postJson('/api/profile', ['semestre' => 9])->assertUnprocessable();
    }

    public function test_enrollment_is_idempotent_and_inactive_group_or_expired_window_rejected(): void
    {
        $this->acting('student', 1, 3);
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertOk();
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertOk();
        $this->assertDatabaseCount('curso_estudiantes', 1);
        DB::table('grupos_academicos')->where('id', 1)->update(['activo' => false]);
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertUnprocessable();
        DB::table('grupos_academicos')->where('id', 1)->update(['activo' => true]);
        DB::table('cursos')->where('id', 1)->update(['inscripcion_hasta' => now()->subHour()]);
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertUnprocessable();
    }

    public function test_summary_is_read_only_and_promotion_retries_do_not_duplicate_movements(): void
    {
        $this->getJson('/api/semester-management')->assertOk();
        $this->assertDatabaseCount('matriculas_academicas', 1);
        $service = app(AcademicPromotionService::class); $period = AcademicPeriod::findOrFail(2);
        $preview = $service->preview($period);
        $data = ['operation_id' => (string) Str::uuid(), 'snapshot' => $preview['snapshot'],
            'movements' => [['student_id' => 'student', 'group_id' => 2, 'action' => 'avance', 'reason' => 'Revisado por carrera']]];
        $first = $service->confirm($period, $data);
        $this->assertEquals($first, $service->confirm($period, $data));
        $this->assertDatabaseCount('matriculas_academicas', 2);
        $this->assertSame('abierto', DB::table('carrera_periodos')->where('carrera_id', 2)->value('estado'));
        $this->assertDatabaseHas('matriculas_academicas', ['grupo_id' => 1]);
    }

    public function test_stale_promotion_is_rejected_without_partial_writes(): void
    {
        $preview = app(AcademicPromotionService::class)->preview(AcademicPeriod::findOrFail(2));
        DB::table('matriculas_academicas')->increment('version');
        $this->postJson('/api/semester-management/periods/2/promote', ['operation_id' => (string) Str::uuid(), 'snapshot' => $preview['snapshot'],
            'movements' => [['student_id' => 'student', 'group_id' => 2, 'action' => 'avance', 'reason' => 'Confirmado']]])->assertConflict();
        $this->assertDatabaseCount('matriculas_academicas', 1);
    }

    public function test_authorization_has_explicit_scope_version_and_revocation(): void
    {
        $row = app(AcademicAuthorizationService::class)->request($this->authorization());
        $this->putJson('/api/semester-management/exceptions/'.$row->id.'/decision', ['version' => 1, 'status' => 'aprobada', 'reason' => 'Revisado'])->assertUnprocessable();
        $this->putJson('/api/semester-management/exceptions/'.$row->id.'/decision', ['version' => 1, 'status' => 'aprobada', 'reason' => 'Revisado', 'project_scope' => true])->assertOk();
        $this->putJson('/api/semester-management/exceptions/'.$row->id.'/decision', ['version' => 1, 'status' => 'revocada', 'reason' => 'Revocar'])->assertConflict();
        $this->putJson('/api/semester-management/exceptions/'.$row->id.'/decision', ['version' => 2, 'status' => 'revocada', 'reason' => 'Revocar'])->assertOk();
        $this->assertSame(0, app(AcademicAuthorizationService::class)->valid('presentacion_semestre', 1, 1)->count());
    }

    public function test_agenda_rejects_shared_room_across_careers_and_accepts_adjacent_slots(): void
    {
        $data = $this->roomData();
        DB::table('salas_evaluacion')->insert(['id' => 1, 'periodo_id' => 1, 'rubrica_id' => 2, 'nombre' => 'Otra carrera', 'salon_id' => 1, 'inicia_en' => $data['fecha_evaluacion'], 'termina_en' => $data['fecha_fin_evaluacion']]);
        try { app(EvaluationAgendaService::class)->validate($data); $this->fail('Conflicting classroom was accepted'); }
        catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $data['fecha_evaluacion'] = now()->addDay()->setTime(12,0)->toDateTimeString();
        $data['fecha_fin_evaluacion'] = now()->addDay()->setTime(14,0)->toDateTimeString();
        $this->assertSame(1, app(EvaluationAgendaService::class)->validate($data)['salon_id']);
    }

    public function test_room_wrong_stage_needs_authorization(): void
    {
        $data = $this->roomData(); $data['semestre'] = 8;
        try { app(EvaluationAgendaService::class)->validate($data); $this->fail('Unauthorized stage accepted'); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $authorization = app(AcademicAuthorizationService::class)->request($this->authorization());
        app(AcademicAuthorizationService::class)->decide($authorization->id, ['version' => 1, 'status' => 'aprobada', 'reason' => 'Presentación especial', 'project_scope' => true]);
        $this->assertSame(1, app(EvaluationAgendaService::class)->validate($data)['periodo_id']);
    }

    public function test_membership_cannot_be_demoted_with_pending_courses(): void
    {
        $id = DB::table('usuario_carrera')->where('usuario_id', 'teacher')->value('id');
        $this->putJson('/api/academic-operations/memberships/'.$id, ['profile_id' => 3])->assertConflict();
        $this->assertDatabaseHas('usuario_carrera', ['id' => $id, 'perfil_id' => 2]);
    }

    public function test_residency_cannot_close_without_reviewed_documentation_and_is_individual(): void
    {
        DB::table('matriculas_academicas')->where('estudiante_id', 'student')->update(['grupo_id' => 5]);
        $this->acting('student', 1, 3);
        $payload = ['student_id' => 'student', 'period_id' => 1, 'project_id' => 1, 'company_id' => 1, 'advisor_id' => 'teacher', 'origin' => 'continuacion', 'changes' => 'Cambios notorios al proyecto integrador previo'];
        $id = $this->postJson('/api/residencies', $payload)->assertCreated()->json('id');
        $this->postJson('/api/residencies', $payload)->assertConflict();
        $this->assertDatabaseCount('residencia_requisitos', 3);
        $this->acting('head', 1, 5);
        $this->postJson('/api/residencies/'.$id.'/close', ['version' => 1, 'reason' => 'Revisado'])->assertConflict();
    }

    public function test_closing_self_registration_preserves_course_and_enrolled_student_access(): void
    {
        DB::table('entregables')->insert(['id' => 1, 'curso_id' => 1, 'nombre' => 'Avance', 'tipo_documento' => 'documento']);
        $this->acting('student', 1, 3);
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertOk();
        $this->acting('teacher', 1, 2);
        $this->putJson('/api/courses/1/self-enrollment', ['enabled' => false])->assertOk();
        $this->assertDatabaseHas('cursos', ['id' => 1, 'es_seguimiento_proyecto' => true, 'clave_autoregistro' => null]);
        $this->acting('student', 1, 3);
        $this->getJson('/api/course-enrollments')->assertOk()->assertJsonPath('data.0.inscrito', true);
        $this->getJson('/api/my-deliverables')->assertOk()->assertJsonPath('data.0.project_id', 1);
        $this->getJson('/api/deliverables/1')->assertOk();
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertUnprocessable();
        $this->assertDatabaseHas('curso_estudiantes', ['curso_id' => 1, 'estudiante_id' => 'student', 'activo' => true]);
    }

    public function test_individual_enrollment_controls_deliverables_and_baja(): void
    {
        DB::table('entregables')->insert(['id' => 1, 'curso_id' => 1, 'nombre' => 'Avance', 'tipo_documento' => 'documento']);
        $this->acting('student', 1, 3);
        $this->getJson('/api/my-deliverables')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertOk();
        $this->getJson('/api/my-deliverables')->assertOk()->assertJsonPath('data.0.project_id', 1);
        $this->getJson('/api/deliverables/1')->assertOk();
        $this->deleteJson('/api/course-enrollments/1', ['reason' => 'Cambio de materia'])->assertOk();
        $this->getJson('/api/my-deliverables')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/deliverables/1')->assertForbidden();
    }

    public function test_residency_upload_review_close_and_private_download(): void
    {
        \Illuminate\Support\Facades\Storage::fake('swgpi_private');
        \Illuminate\Support\Facades\Storage::fake('local');
        DB::table('matriculas_academicas')->where('estudiante_id', 'student')->update(['grupo_id' => 5]);
        $this->acting('student', 1, 3);
        $id = $this->postJson('/api/residencies', ['student_id' => 'student', 'period_id' => 1, 'project_id' => 1, 'company_id' => 1, 'advisor_id' => 'teacher', 'origin' => 'continuacion', 'changes' => 'Nuevo proyecto empresarial con documentación individual'])->assertCreated()->json('id');
        foreach (DB::table('residencia_requisitos')->where('residencia_id', $id)->pluck('id') as $requirement) {
            $path = tempnam(sys_get_temp_dir(), 'sgpi-test-pdf'); file_put_contents($path, "%PDF-1.7\nResidencia\n");
            try {
                $file = new \Illuminate\Http\UploadedFile($path, 'residencia.pdf', 'application/pdf', UPLOAD_ERR_OK, true);
                $this->post('/api/residencies/'.$id.'/requirements/'.$requirement.'/versions', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();
            } finally { @unlink($path); }
            $version = DB::table('residencia_versiones')->where('requisito_id', $requirement)->value('id');
            $this->acting('teacher', 1, 2);
            $this->putJson('/api/residencies/'.$id.'/versions/'.$version.'/review', ['status' => 'validada', 'observations' => 'Documento validado'])->assertOk();
            $this->putJson('/api/residencies/'.$id.'/versions/'.$version.'/review', ['status' => 'correcciones', 'observations' => 'No sobrescribir'])->assertConflict();
            $this->acting('student', 1, 3);
            $this->get('/api/residencies/'.$id.'/versions/'.$version.'/download')->assertOk();
        }
        $this->acting('head', 1, 5);
        $version = DB::table('residencias')->where('id', $id)->value('version');
        $this->postJson('/api/residencies/'.$id.'/close', ['version' => $version, 'reason' => 'Documentación completa'])->assertOk();
        $this->assertDatabaseCount('residencia_versiones', 3);
        $this->assertDatabaseHas('residencias', ['id' => $id, 'estado' => 'cerrada']);
        $this->acting('foreign', 2, 2);
        $this->getJson('/api/residencies')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_expired_and_cross_career_decisions_preserve_authorization_history(): void
    {
        $first = app(AcademicAuthorizationService::class)->request($this->authorization());
        $second = app(AcademicAuthorizationService::class)->request($this->authorization(['presentation_semester' => 7]));
        $this->assertNotSame($first->id, $second->id);
        DB::table('autorizaciones_academicas')->where('id', $first->id)->update(['vigente_hasta' => now()->subMinute()]);
        $this->putJson('/api/semester-management/exceptions/'.$first->id.'/decision', ['version' => 1, 'status' => 'aprobada', 'reason' => 'Prueba', 'project_scope' => true])->assertUnprocessable();
        $this->acting('foreign', 2, 5);
        $this->putJson('/api/semester-management/exceptions/'.$second->id.'/decision', ['version' => 1, 'status' => 'aprobada', 'reason' => 'Prueba', 'project_scope' => true])->assertNotFound();
        $this->assertDatabaseCount('autorizaciones_academicas', 2);
    }

    public function test_closure_blocks_pending_room_without_affecting_other_career(): void
    {
        DB::table('salas_evaluacion')->insert(['id' => 1, 'periodo_id' => 1, 'rubrica_id' => 1, 'nombre' => 'Pendiente']);
        $this->postJson('/api/semester-management/periods/1/transition', ['state' => 'en_cierre'])->assertOk();
        $this->postJson('/api/semester-management/periods/1/transition', ['state' => 'cerrado'])->assertConflict();
        DB::table('salas_evaluacion')->where('id', 1)->update(['estado' => 'finalizada']);
        $this->postJson('/api/semester-management/periods/1/transition', ['state' => 'cerrado'])->assertOk();
        $this->assertDatabaseHas('carrera_periodos', ['carrera_id' => 2, 'estado' => 'abierto']);
        $this->acting('student', 1, 3);
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertConflict();
    }

    public function test_catalog_and_room_creation_work_on_normalized_schema(): void
    {
        $this->getJson('/api/academic-operations/catalog')->assertOk();
        $data = $this->roomData() + ['nombre' => 'Sala nueva', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]];
        $response = $this->postJson('/api/evaluations/rooms', $data)->assertCreated();
        $this->assertDatabaseHas('salas_evaluacion', ['id' => $response->json('room.id'), 'periodo_id' => 1, 'salon_id' => 1]);
        $this->assertDatabaseHas('evaluaciones', ['proyecto_id' => 1]);
    }

    public function test_preparation_job_never_activates_or_promotes(): void
    {
        $this->artisan('sgpi:prepare-academic-periods')->assertSuccessful();
        $this->assertDatabaseCount('matriculas_academicas', 1);
        $this->assertDatabaseHas('carrera_periodos', ['carrera_id' => 2, 'periodo_id' => 2, 'estado' => 'preparacion']);
        $this->assertDatabaseHas('ejecuciones_academicas', ['estado' => 'completada']);
    }

    public static function reservationResources(): array { return [['classroom'], ['teacher']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('reservationResources')]
    public function test_simultaneous_reservations_cannot_book_same_resource(string $resource): void
    {
        if (!function_exists('pcntl_fork')) $this->markTestSkipped('pcntl required.');
        DB::table('salones_institucionales')->insert(['id' => 2, 'nombre' => 'Aula 2', 'clave' => 'aula 2']);
        DB::commit(); $this->committedForConcurrency = true; DB::disconnect();
        $files = []; $pids = [];
        for ($index = 0; $index < 2; $index++) {
            $path = tempnam(sys_get_temp_dir(), 'sgpi-concurrency-'); $files[] = $path;
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge('academic_test');
                try {
                    DB::transaction(function () use ($index, $resource) {
                        $data = $this->roomData(); $data['project_ids'] = [$index === 0 ? 1 : 3];
                        $data['teacher_ids'] = [$resource === 'teacher' || $index === 0 ? 'teacher' : 'head'];
                        $data['salon_id'] = $resource === 'teacher' ? $index + 1 : 1;
                        app(EvaluationAgendaService::class)->validate($data);
                        usleep(150000);
                        $roomId = DB::table('salas_evaluacion')->insertGetId(['periodo_id' => 1, 'rubrica_id' => 1, 'nombre' => 'Concurrente '.$index, 'salon_id' => $data['salon_id'], 'inicia_en' => $data['fecha_evaluacion'], 'termina_en' => $data['fecha_fin_evaluacion']]);
                        DB::table('sala_docentes')->insert(['sala_id' => $roomId, 'docente_id' => $data['teacher_ids'][0]]);
                    }, 3);
                    file_put_contents($path, '201');
                } catch (HttpException $e) { file_put_contents($path, (string) $e->getStatusCode()); }
                catch (\Throwable $e) { file_put_contents($path, get_class($e).': '.$e->getMessage()); }
                exit(0);
            }
            $this->assertGreaterThan(0, $pid); $pids[] = $pid;
        }
        foreach ($pids as $pid) pcntl_waitpid($pid, $status);
        $results = array_map(fn ($path) => file_get_contents($path), $files);
        foreach ($files as $path) unlink($path);
        sort($results);
        $this->assertSame(['201', '409'], $results);
        $this->assertSame(1, DB::table('salas_evaluacion')->count());
    }

    public function test_reconciliation_requires_explicit_legacy_room_resolution_and_preserves_evaluations(): void
    {
        DB::table('carrera_periodos')->where('carrera_id', 1)->update(['estado' => 'preparacion', 'conciliado' => false]);
        DB::table('salas_evaluacion')->insert(['id' => 1, 'rubrica_id' => 1, 'nombre' => 'Heredada', 'salon' => 'Aula 1']);
        DB::table('evaluaciones')->insert(['id' => 1, 'sala_id' => 1, 'proyecto_id' => 1, 'orden_presentacion' => 1]);
        $report = $this->getJson('/api/academic-operations/periods/1/reconciliation')->assertOk()->json();
        $payload = ['snapshot' => $report['snapshot'], 'resolutions' => [['student_id' => 'student', 'group_id' => 1, 'reason' => 'Registro verificado']], 'acknowledge_legacy_exceptions' => true];
        $this->postJson('/api/academic-operations/periods/1/reconciliation', $payload)->assertUnprocessable();
        $payload['room_resolutions'] = [['room_id' => 1, 'period_id' => 1, 'classroom_id' => 1, 'reason' => 'Calendario institucional verificado']];
        $this->postJson('/api/academic-operations/periods/1/reconciliation', $payload)->assertOk();
        $this->assertDatabaseHas('salas_evaluacion', ['id' => 1, 'periodo_id' => 1, 'salon_id' => 1]);
        $this->assertDatabaseHas('evaluaciones', ['id' => 1, 'sala_id' => 1, 'proyecto_id' => 1]);
        $this->assertDatabaseHas('carrera_periodos', ['carrera_id' => 1, 'conciliado' => true]);
    }

    public function test_exit_decision_cannot_repeat_under_a_different_operation_id(): void
    {
        $service = app(AcademicPromotionService::class); $period = AcademicPeriod::findOrFail(2);
        $data = ['operation_id' => (string) Str::uuid(), 'snapshot' => $service->preview($period)['snapshot'],
            'movements' => [['student_id' => 'student', 'action' => 'salida', 'reason' => 'Salida voluntaria del seguimiento']]];
        $service->confirm($period, $data);
        $data['operation_id'] = (string) Str::uuid(); $data['snapshot'] = $service->preview($period)['snapshot'];
        $this->postJson('/api/semester-management/periods/2/promote', $data)->assertConflict();
        $this->assertDatabaseCount('movimientos_academicos', 1);
        $this->assertDatabaseCount('matriculas_academicas', 1);
    }

    public function test_failed_second_movement_rolls_back_entire_promotion_batch(): void
    {
        DB::table('usuarios')->insert(['id' => 'student2', 'nombres' => 'Otro alumno', 'contrasena' => Hash::make('testing-password')]);
        DB::table('usuario_carrera')->insert(['usuario_id' => 'student2', 'carrera_id' => 1, 'perfil_id' => 3]);
        DB::table('matriculas_academicas')->insert(['estudiante_id' => 'student2', 'grupo_id' => 1]);
        $preview = app(AcademicPromotionService::class)->preview(AcademicPeriod::findOrFail(2));
        $this->postJson('/api/semester-management/periods/2/promote', ['operation_id' => (string) Str::uuid(), 'snapshot' => $preview['snapshot'], 'movements' => [
            ['student_id' => 'student', 'group_id' => 2, 'action' => 'avance', 'reason' => 'Revisión completa'],
            ['student_id' => 'student2', 'group_id' => 3, 'action' => 'avance', 'reason' => 'Grupo de otra carrera'],
        ]])->assertNotFound();
        $this->assertDatabaseCount('matriculas_academicas', 2);
        $this->assertDatabaseCount('movimientos_academicos', 0);
        $this->assertDatabaseCount('operaciones_academicas', 0);
    }

    public function test_retry_keeps_prior_results_and_links_exact_approved_source(): void
    {
        $first = $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Primera sala', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertCreated()->json('room.id');
        $evaluation = DB::table('evaluaciones')->where('sala_id', $first)->first();
        DB::table('evaluaciones')->where('id', $evaluation->id)->update(['estado' => 'finalizada', 'apto_titulacion' => false]);
        DB::table('salas_evaluacion')->where('id', $first)->update(['estado' => 'finalizada']);
        $data = $this->roomData() + ['nombre' => 'Recuperación', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]];
        $this->postJson('/api/evaluations/rooms', $data)->assertUnprocessable();
        $approval = app(AcademicAuthorizationService::class)->request($this->authorization(['type' => 'reintento', 'evaluation_id' => $evaluation->id]));
        app(AcademicAuthorizationService::class)->decide($approval->id, ['version' => 1, 'status' => 'aprobada', 'reason' => 'Nuevo intento revisado', 'project_scope' => true]);
        $this->postJson('/api/evaluations/rooms', $data)->assertCreated();
        $this->assertDatabaseCount('evaluaciones', 2);
        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluation->id, 'estado' => 'finalizada', 'apto_titulacion' => false]);
        $consumed = DB::table('autorizaciones_academicas')->find($approval->id);
        $this->assertNotNull($consumed->consumida_en);
        $this->assertDatabaseHas('evaluaciones', ['id' => $consumed->consumida_por_evaluacion_id, 'estado' => 'programada']);
    }

    public function test_stage_approval_is_bound_to_created_evaluation_and_cannot_be_revoked_after_use(): void
    {
        DB::table('rubricas')->where('id', 1)->update(['semestre' => 8]);
        $approval = app(AcademicAuthorizationService::class)->request($this->authorization());
        app(AcademicAuthorizationService::class)->decide($approval->id, ['version' => 1, 'status' => 'aprobada', 'reason' => 'Etapa autorizada', 'project_scope' => true]);
        $data = array_replace($this->roomData(), ['semestre' => 8, 'nombre' => 'Sala especial', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]]);
        $id = $this->postJson('/api/evaluations/rooms', $data)->assertCreated()->json('room.id');
        $consumer = DB::table('autorizaciones_academicas')->where('id', $approval->id)->value('consumida_por_evaluacion_id');
        $this->assertDatabaseHas('evaluaciones', ['id' => $consumer, 'sala_id' => $id]);
        $this->putJson('/api/semester-management/exceptions/'.$approval->id.'/decision', ['version' => 2, 'status' => 'revocada', 'reason' => 'Ya utilizada'])->assertConflict();
        $data['nombre'] = 'Nombre actualizado';
        $this->putJson('/api/evaluations/rooms/'.$id, $data)->assertOk();
    }

    public function test_settings_and_document_queries_do_not_create_activities_or_infer_period_from_name(): void
    {
        $this->getJson('/api/settings/current')->assertOk()->assertJsonPath('active_academic_period', 'Periodo actual');
        $this->getJson('/api/evaluation-documents')->assertOk();
        $this->assertDatabaseCount('entregables', 0);
        $this->assertDatabaseCount('movimientos_academicos', 0);
    }

    public function test_real_role_and_period_middleware_prevent_student_governance_and_closed_writes(): void
    {
        $this->withMiddleware([\App\Http\Middleware\CheckRole::class, \App\Http\Middleware\ResolveCareerContext::class, \App\Http\Middleware\EnsureAcademicPeriodOpen::class]);
        $this->acting('student', 1, 3);
        $this->postJson('/api/semester-management/periods/1/transition', ['state' => 'en_cierre'])->assertForbidden();
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertOk();
        DB::table('carrera_periodos')->where('carrera_id', 1)->update(['estado' => 'cerrado']);
        $this->deleteJson('/api/course-enrollments/1', ['reason' => 'Petición directa'])->assertConflict();
        $this->getJson('/api/my-deliverables')->assertOk();
    }

    public function test_teaching_assignments_use_membership_and_normalized_courses_and_keep_audit_history(): void
    {
        DB::table('asignaturas')->where('id', 1)->update(['nombre' => 'Fundamentos de Ingeniería de Software']);
        $this->getJson('/api/proposal/config')->assertOk()->assertJsonCount(1, 'subject_groups.0.teacher_assignments');
        $this->postJson('/api/proposal/assignments', ['subject_group_id' => 1, 'asignatura_id' => 1, 'teacher_id' => 'foreign'])->assertUnprocessable();
        $response = $this->postJson('/api/proposal/assignments', ['subject_group_id' => 1, 'asignatura_id' => 1, 'teacher_id' => 'head'])->assertCreated();
        $this->assertDatabaseHas('curso_docentes', ['curso_id' => 1, 'docente_id' => 'head', 'activo' => true]);
        $this->deleteJson('/api/proposal/assignments/'.$response->json('assignment.id'))->assertOk();
        $this->assertDatabaseHas('curso_docentes', ['curso_id' => 1, 'docente_id' => 'head', 'activo' => false]);
        $this->assertDatabaseHas('bitacora_academica', ['accion' => 'curso.docente_retirado', 'actor_id' => 'head']);
    }

    public function test_proposal_status_uses_current_enrolled_course_and_does_not_depend_on_legacy_window_tables(): void
    {
        DB::table('asignaturas')->where('id', 1)->update(['nombre' => 'Fundamentos de Ingeniería de Software']);
        DB::table('grupos_academicos')->where('id', 1)->update(['registro_proyectos_desde' => now()->subDay(), 'registro_proyectos_hasta' => now()->addDay()]);
        $this->acting('student', 1, 3);
        $this->getJson('/api/proposal/student-status')->assertOk()->assertJsonPath('active_window', null);
        $this->postJson('/api/course-enrollments/1', ['password' => 'course-key'])->assertOk();
        $this->getJson('/api/proposal/student-status')->assertOk()->assertJsonPath('subject_group.id', 1)->assertJsonPath('active_window.id', 1);
    }

    public function test_group_change_cannot_rewrite_academic_history(): void
    {
        $this->putJson('/api/subject-groups/1', ['nombre' => 'Cambio', 'semestre' => 8, 'grupo' => 'A', 'periodo' => 'Periodo actual', 'asignatura_ids' => [1]])->assertConflict();
        $this->assertDatabaseHas('grupos_academicos', ['id' => 1, 'semestre' => 5]);
        $this->assertDatabaseHas('matriculas_academicas', ['grupo_id' => 1]);
    }

    public function test_old_results_keep_teacher_identity_after_membership_deactivation(): void
    {
        $id = $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Sala histórica', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertCreated()->json('room.id');
        DB::table('salas_evaluacion')->where('id', $id)->update(['estado' => 'finalizada']);
        // Simulates a completed, explicitly released transfer; historical relations must remain resolvable.
        DB::table('usuario_carrera')->where('usuario_id', 'teacher')->update(['activo' => false]);
        DB::table('usuarios')->where('id', 'teacher')->update(['activo' => false]);
        $this->assertSame('teacher', EvaluationRoom::findOrFail($id)->teachers->first()->id);
        $this->assertSame('teacher', EvaluationRoom::findOrFail($id)->responsibleTeacher->id);
        $this->assertNull(User::find('teacher'));
    }

    public function test_ninth_semester_stays_in_residency_and_new_company_project_can_keep_old_integrator_link(): void
    {
        DB::table('matriculas_academicas')->where('estudiante_id', 'student')->update(['grupo_id' => 5]);
        $payload = ['title' => 'Proyecto de empresa', 'description' => 'Trabajo de residencia profesional', 'subject_group_id' => 5,
            'semestre' => 9, 'year' => (int) now()->year, 'student_ids' => ['student'], 'modalidad' => 'caso_integrador',
            'company_id' => 1, 'company_name' => 'Empresa de prueba', 'company_giro' => 'Software', 'company_contact_name' => 'Contacto',
            'company_contact_position' => 'Gerencia', 'company_address' => 'Domicilio de prueba'];
        $id = $this->postJson('/api/projects', $payload)->assertCreated()->json('project.id');
        $this->postJson('/api/projects/'.$id.'/advisors', ['user_id' => 'teacher', 'rol_asesor' => 'primario'])->assertOk();
        $this->acting('student', 1, 3);
        $this->postJson('/api/residencies', ['student_id' => 'student', 'period_id' => 1, 'project_id' => $id, 'company_id' => 1,
            'advisor_id' => 'teacher', 'origin' => 'empresa', 'changes' => 'Nuevo proyecto de empresa para residencia individual'])->assertCreated();
        $this->assertDatabaseHas('proyecto_integrantes', ['proyecto_id' => 1, 'usuario_id' => 'student']);
        $this->acting('head', 1, 5);
        $preview = app(AcademicPromotionService::class)->preview(AcademicPeriod::findOrFail(2));
        $this->assertSame(9, $preview['movements'][0]['to']);
        $this->assertSame('permanencia', $preview['movements'][0]['action']);
        $this->assertStringContainsString('Residencia abierta', $preview['movements'][0]['pending']);
    }

    public function test_maintenance_expires_authorizations_and_notifies_once_without_promoting(): void
    {
        DB::table('periodos_academicos')->where('id', 1)->update(['fecha_fin' => now()->addDays(3)]);
        $row = app(AcademicAuthorizationService::class)->request($this->authorization());
        DB::table('autorizaciones_academicas')->where('id', $row->id)->update(['vigente_hasta' => now()->subHour()]);
        $this->artisan('sgpi:prepare-academic-periods')->assertSuccessful();
        $this->artisan('sgpi:prepare-academic-periods')->assertSuccessful();
        $this->assertDatabaseHas('autorizaciones_academicas', ['id' => $row->id, 'estado' => 'vencida']);
        $this->assertSame(1, DB::table('notificaciones')->where('tipo', 'periodo_por_cerrar')->count());
        // El periodo de prueba va en su quinta semana: la jefatura recibe un solo recordatorio de alumnos irregulares.
        $this->assertSame(1, DB::table('notificaciones')->where('tipo', 'revision_irregulares')->count());
        $this->assertDatabaseCount('matriculas_academicas', 1);
        $this->assertDatabaseCount('movimientos_academicos', 0);
        $this->assertDatabaseCount('ejecuciones_academicas', 2);
    }

    public function test_room_edit_cannot_relabel_existing_evaluation_stage_or_period(): void
    {
        $payload = $this->roomData() + ['nombre' => 'Sala original', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]];
        $room = $this->postJson('/api/evaluations/rooms', $payload)->assertCreated()->json('room.id');
        $snapshot = DB::table('evaluaciones')->where('sala_id', $room)->value('criterios_snapshot');
        DB::table('rubricas')->insert(['id' => 3, 'carrera_id' => 1, 'nombre' => 'Desarrollo', 'etapa' => 'avance', 'semestre' => 6]);
        $this->putJson('/api/evaluations/rooms/'.$room, array_replace($payload, ['semestre' => 6]))->assertConflict();
        $this->putJson('/api/evaluations/rooms/'.$room, array_replace($payload, ['periodo_id' => 2]))->assertConflict();
        $this->assertDatabaseHas('salas_evaluacion', ['id' => $room, 'rubrica_id' => 1, 'periodo_id' => 1]);
        $this->assertSame($snapshot, DB::table('evaluaciones')->where('sala_id', $room)->value('criterios_snapshot'));
    }

    public function test_room_without_active_criteria_is_rejected_atomically_and_can_be_retried(): void
    {
        DB::table('criterios_rubrica')->update(['activo' => false]);
        $payload = $this->roomData() + ['nombre' => 'Sala sin criterios', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]];
        $this->postJson('/api/evaluations/rooms', $payload)->assertUnprocessable()->assertJsonValidationErrors('project_ids');
        $this->assertDatabaseCount('salas_evaluacion', 0);
        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseCount('sala_docentes', 0);
        DB::table('criterios_rubrica')->update(['activo' => true]);
        $this->postJson('/api/evaluations/rooms', $payload)->assertCreated();
        $this->assertCount(1, json_decode(DB::table('evaluaciones')->value('criterios_snapshot'), true));
    }

    public function test_real_scoring_persists_normalized_response_and_preserves_criteria_and_results(): void
    {
        $room = $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Sala calificada', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertCreated()->json('room.id');
        $evaluation = DB::table('evaluaciones')->where('sala_id', $room)->first();
        $document = \App\Models\RepositoryDocument::create(['carrera_id' => 1, 'project_id' => 1, 'nombre' => 'Liberación', 'document_category' => 'evaluation_release_sheet', 'visibility' => 'private', 'uploaded_by' => 'student']);
        DB::table('liberaciones_documentos_evaluacion')->insert(['documento_repositorio_id' => $document->id, 'alumno_id' => 'student', 'liberado' => true, 'revisado_por' => 'head', 'revisado_en' => now()]);
        // En 5° (propuesta) también se exige la presentación.
        \App\Models\RepositoryDocument::create(['carrera_id' => 1, 'project_id' => 1, 'nombre' => 'Presentación', 'document_category' => 'evaluation_presentation', 'visibility' => 'private', 'uploaded_by' => 'student']);
        DB::table('revisiones_entregas_evaluacion')->insert(['proyecto_id' => 1, 'periodo_id' => 1, 'entrega' => 'presentation', 'estado' => 'aprobada', 'revisado_por' => 'head']);
        DB::table('criterios_rubrica')->where('id', 1)->update(['pregunta' => 'Pregunta cambiada después de programar']);
        \App\Models\CareerSetting::setValue('rubric_score_modes', ['5' => 'numeric'], 'array');
        DB::table('salas_evaluacion')->where('id', $room)->update(['secuencia_bloqueada' => true, 'orden_actual' => 1]);
        DB::table('evaluaciones')->where('id', $evaluation->id)->update(['estado' => 'en_evaluacion']);
        $this->travelTo(now()->addDay()->setTime(10, 30));
        $this->acting('teacher', 1, 2);
        $payload = ['scores' => [['criterio' => 'problema', 'nivel' => 'totalmente_de_acuerdo', 'comentario' => 'Justificación']], 'general_comment' => 'Revisado'];
        $response = $this->postJson('/api/evaluations/'.$evaluation->id.'/score', $payload)->assertOk();
        $response->assertJsonPath('evaluation.criteria.0.label', 'Pregunta original')->assertJsonPath('evaluation.score_mode', 'levels');
        // El nombre anterior se guarda con el nuevo: "Completamente de acuerdo" = 5 de 5 = 100 %.
        $this->assertDatabaseHas('respuestas_evaluacion', ['criterio_id' => 1, 'puntaje' => 5, 'valor' => 'completamente_de_acuerdo', 'escala' => 'acuerdo', 'porcentaje' => 100]);
        $this->postJson('/api/evaluations/'.$evaluation->id.'/score', $payload)->assertStatus(422);
        $this->assertDatabaseCount('respuestas_evaluacion', 1);
        $this->assertDatabaseCount('dictamenes_docentes', 1);
        $this->travelBack();
    }

    public function test_schema_rejects_career_mismatches_between_child_and_parent(): void
    {
        // La carrera de un proyecto es la de su grupo: no hay copia que pueda diferir.
        $this->assertSame(2, \App\Support\CareerScope::of('proyectos', 2));
        // Los dos caminos de un "rombo" deben llegar a la misma carrera.
        $this->assertSqlRejected(fn () => DB::table('cursos')->insert(['grupo_id' => 3, 'asignatura_id' => 1]));
        DB::table('salas_evaluacion')->insert(['id' => 9, 'periodo_id' => 1, 'rubrica_id' => 2, 'nombre' => 'Sala carrera 2']);
        $this->assertSqlRejected(fn () => DB::table('evaluaciones')->insert(['sala_id' => 9, 'proyecto_id' => 1, 'orden_presentacion' => 1]));
        // Con proyecto, el documento no guarda carrera; sin proyecto, la carrera es obligatoria.
        $id = DB::table('documentos')->insertGetId(['carrera_id' => 2, 'proyecto_id' => 1, 'titulo' => 'Documento con proyecto']);
        $this->assertNull(DB::table('documentos')->where('id', $id)->value('carrera_id'));
        $this->assertSame(1, \App\Support\CareerScope::of('documentos', $id));
        $this->assertSqlRejected(fn () => DB::table('documentos')->insert(['titulo' => 'Documento sin carrera']));
    }

    public function test_schema_enforces_unique_roles_and_memberships(): void
    {
        DB::table('proyectos')->insert(['id' => 4, 'grupo_id' => 1, 'titulo' => 'Tesis', 'tipo' => 'tesis']);
        DB::table('proyecto_integrantes')->insert(['proyecto_id' => 4, 'usuario_id' => 'teacher', 'rol' => 'asesor']);
        $this->assertSqlRejected(fn () => DB::table('proyecto_integrantes')->insert(['proyecto_id' => 4, 'usuario_id' => 'head', 'rol' => 'asesor']));
        $this->assertSqlRejected(fn () => DB::table('proyecto_integrantes')->insert(['proyecto_id' => 1, 'usuario_id' => 'head', 'rol' => 'revisor_1']));
        $this->assertSqlRejected(fn () => DB::table('proyecto_integrantes')->insert(['proyecto_id' => 1, 'usuario_id' => 'foreign', 'rol' => 'secundario']));
        $this->assertSqlRejected(fn () => DB::table('proyecto_integrantes')->insert(['proyecto_id' => 1, 'usuario_id' => 'head', 'rol' => 'integrante']));
        $this->assertSqlRejected(fn () => DB::table('usuario_carrera')->insert(['usuario_id' => 'student', 'carrera_id' => 2, 'perfil_id' => 3]));
        DB::table('usuario_carrera')->where('usuario_id', 'head')->update(['es_principal' => true]);
        $this->assertSqlRejected(fn () => DB::table('usuario_carrera')->insert(['usuario_id' => 'head', 'carrera_id' => 2, 'perfil_id' => 2, 'es_principal' => true]));
        DB::table('usuario_carrera')->insert(['usuario_id' => 'head', 'carrera_id' => 2, 'perfil_id' => 2, 'es_principal' => false]);
        $this->assertDatabaseCount('usuario_carrera', 5);
    }

    public function test_only_room_teachers_can_hold_a_verdict(): void
    {
        $room = $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Sala', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertCreated()->json('room.id');
        $evaluation = DB::table('evaluaciones')->where('sala_id', $room)->value('id');
        $this->assertSqlRejected(fn () => DB::table('dictamenes_docentes')->insert(['evaluacion_id' => $evaluation, 'docente_id' => 'head']));
        DB::table('dictamenes_docentes')->insert(['evaluacion_id' => $evaluation, 'docente_id' => 'teacher']);
        $this->assertDatabaseCount('dictamenes_docentes', 1);
    }

    public function test_derived_values_replace_redundant_columns(): void
    {
        $room = $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Sala', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertCreated()->json('room.id');
        $start = DB::table('salas_evaluacion')->where('id', $room)->value('inicia_en');
        $evaluation = \App\Models\Evaluation::withSchedule()->where('evaluaciones.sala_id', $room)->firstOrFail();
        $this->assertSame($start, $evaluation->fecha_exposicion->toDateTimeString());
        $this->assertSame('pendiente', $evaluation->resultado);
        DB::table('evaluaciones')->where('id', $evaluation->id)->update(['apto_titulacion' => true]);
        $this->assertSame('viable', \App\Models\Evaluation::findOrFail($evaluation->id)->resultado);

        \App\Models\CareerSetting::setValue('rubric_score_modes', ['5' => 'numeric'], 'array');
        \App\Models\CareerSetting::setValue('evaluation_manager_teacher_ids', ['teacher'], 'array');
        $this->assertDatabaseHas('rubricas', ['id' => 1, 'modo_puntaje' => 'numeric']);
        $this->assertDatabaseHas('carrera_responsables_evaluacion', ['carrera_id' => 1, 'docente_id' => 'teacher']);
        $this->assertSame(['5' => 'numeric'], \App\Models\CareerSetting::valueFor('rubric_score_modes'));
        $this->assertSame(['teacher'], \App\Models\CareerSetting::valueFor('evaluation_manager_teacher_ids'));
        $this->assertDatabaseMissing('configuraciones_carrera', ['clave' => 'rubric_score_modes']);
    }

    public function test_profiles_come_from_memberships_and_general_admin_flag(): void
    {
        $this->assertSame(2, User::withoutGlobalScopes()->findOrFail('teacher')->globalProfileId());
        DB::table('usuarios')->where('id', 'head')->update(['es_administrador_general' => true]);
        $this->assertTrue(User::withoutGlobalScopes()->findOrFail('head')->isGeneralAdmin());
        $this->assertSame(3, User::query()->findOrFail('student')->perfil_id);
    }

    /** Semestres del periodo 1 según la fecha de hoy (enero–junio pares, julio–diciembre impares). */
    private function periodOneSemesters(): array
    {
        return app(\App\Services\SemesterManagementService::class)->semestersForPeriod(AcademicPeriod::findOrFail(1));
    }

    public function test_groups_follow_half_year_parity_and_basic_semesters_need_general_admin_switch(): void
    {
        $allowed = $this->periodOneSemesters();
        $this->assertSame([], array_values(array_filter($allowed, fn ($semester) => $semester % 2 !== $allowed[0] % 2)));
        $wrong = $allowed[0] === 9 ? 8 : $allowed[0] + 1;
        $payload = ['nombre' => 'Grupo Z', 'grupo' => 'Z', 'periodo' => 'Periodo actual', 'asignatura_ids' => []];
        $this->postJson('/api/subject-groups', $payload + ['semestre' => $wrong])->assertUnprocessable();
        $this->postJson('/api/subject-groups', $payload + ['semestre' => $allowed[0]])->assertCreated();
        // 1°–4° sólo existen si el administrador general los habilita para la carrera.
        $basic = $allowed[0] % 2 === 0 ? 2 : 3;
        $this->postJson('/api/subject-groups', ['nombre' => 'Básico', 'grupo' => 'B', 'periodo' => 'Periodo actual', 'semestre' => $basic])->assertUnprocessable();
        \App\Models\CareerSetting::setValueForCareer(1, 'basic_semesters_enabled', true, 'boolean');
        $this->postJson('/api/subject-groups', ['nombre' => 'Básico', 'grupo' => 'B', 'periodo' => 'Periodo actual', 'semestre' => $basic])->assertCreated();
    }

    public function test_student_import_creates_missing_groups_and_uses_student_id_as_temporary_password(): void
    {
        $semester = max(array_filter($this->periodOneSemesters(), fn ($s) => $s >= 5));
        $wrong = $semester === 9 ? 8 : $semester - 1;
        $file = fn (string $rows) => \Illuminate\Http\UploadedFile::fake()->createWithContent('alumnos.csv', "matricula,nombres,apellido_paterno,apellido_materno,correo,semestre,grupo\n".$rows);

        $this->post('/api/users/import-excel', ['tipo' => 'alumnos', 'archivo' => $file("N1,Ana,Uno,,,{$semester},Q\nN2,Luis,Dos,,,{$wrong},Q\n")], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseMissing('usuarios', ['id' => 'N1']);

        $response = $this->post('/api/users/import-excel', ['tipo' => 'alumnos', 'archivo' => $file("N1,Ana,Uno,,,{$semester},Q\nN2,Luis,Dos,,,{$semester},Q\n")], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(["{$semester}° Q"], $response->json('groups_created'));
        $group = DB::table('grupos_academicos')->where(['periodo_id' => 1, 'semestre' => $semester, 'clave_grupo' => 'Q'])->first();
        $this->assertSame(2, DB::table('matriculas_academicas')->where('grupo_id', $group->id)->count());
        $student = User::withoutGlobalScopes()->findOrFail('N1');
        $this->assertTrue($student->debe_cambiar_contrasena);
        $this->assertTrue(Hash::check('N1', $student->password));
    }

    public function test_temporary_password_blocks_api_until_it_is_changed(): void
    {
        DB::table('usuarios')->where('id', 'student')->update(['debe_cambiar_contrasena' => true, 'contrasena' => Hash::make('student')]);
        $user = $this->acting('student', 1, 3);
        // setUp() reemplaza el middleware en el contenedor; aquí se prueba la clase real.
        // La actividad va a un caché en memoria para no depender de sesiones de corridas anteriores.
        config(['auth.activity_cache_store' => 'array']);
        $middleware = new \App\Http\Middleware\EnsureUserIsActive();
        $blocked = $middleware->handle(\Illuminate\Http\Request::create('/api/my-projects', 'GET'), fn () => response('ok'));
        $this->assertSame(428, $blocked->getStatusCode());
        $this->assertSame('ok', $middleware->handle(\Illuminate\Http\Request::create('/api/auth/me', 'GET'), fn () => response('ok'))->getContent());

        $this->postJson('/api/auth/password/change-initial', ['password' => 'student', 'password_confirmation' => 'student'])->assertUnprocessable();
        $this->postJson('/api/auth/password/change-initial', ['password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026'])->assertOk();
        $this->assertFalse(User::withoutGlobalScopes()->findOrFail('student')->debe_cambiar_contrasena);
    }

    public function test_room_special_case_registers_and_consumes_stage_authorization(): void
    {
        DB::table('rubricas')->insert(['id' => 3, 'carrera_id' => 1, 'nombre' => 'Rúbrica 8', 'etapa' => 'titulacion', 'semestre' => 8]);
        DB::table('criterios_rubrica')->insert(['id' => 3, 'rubrica_id' => 3, 'clave' => 'avance', 'pregunta' => '¿Va a titulación?', 'orden' => 1]);
        $data = array_replace($this->roomData(), ['semestre' => 8]) + ['nombre' => 'Sala titulación', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]];

        // Sin caso especial, un proyecto de 5° no entra a una sala de 8°.
        $this->postJson('/api/evaluations/rooms', $data)->assertUnprocessable();
        $this->assertDatabaseCount('autorizaciones_academicas', 0);

        $this->postJson('/api/evaluations/rooms', $data + ['special_cases' => [['project_id' => 1, 'reason' => 'Adelantó materias de octavo']]])->assertCreated();
        $authorization = DB::table('autorizaciones_academicas')->where('proyecto_id', 1)->first();
        $this->assertSame('aprobada', $authorization->estado);
        $this->assertSame(8, (int) $authorization->semestre_destino);
        $this->assertNotNull($authorization->consumida_por_evaluacion_id);
    }

    public function test_profiles_follow_levels_and_administrator_profile_no_longer_exists(): void
    {
        $this->assertSame([4, 5, 6, 7, 2, 3], DB::table('perfiles')->orderBy('nivel')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseMissing('perfiles', ['id' => 1]);
        $this->postJson('/api/admin/career-memberships', ['usuario_id' => 'teacher', 'carrera_id' => 2, 'perfil_id' => 1])->assertUnprocessable();
    }

    public function test_head_assistant_registers_lower_profiles_but_not_heads(): void
    {
        $this->acting('head', 1, 6);
        $this->postJson('/api/users', ['id' => 'NUEVO1', 'nombres' => 'Docente nuevo', 'perfil_id' => 2])->assertCreated();
        $this->assertDatabaseHas('usuario_carrera', ['usuario_id' => 'NUEVO1', 'carrera_id' => 1, 'perfil_id' => 2]);
        $this->postJson('/api/users', ['id' => 'NUEVO2', 'nombres' => 'Jefe nuevo', 'perfil_id' => 5])->assertUnprocessable();
        // La cuenta de jefatura (head) sólo la modifica el administrador general.
        $this->putJson('/api/users/head', ['nombres' => 'Cambio'])->assertForbidden();
    }

    public function test_coordinator_manages_projects_except_theses_and_approves_their_authorizations(): void
    {
        DB::table('proyectos')->where('id', 3)->update(['tipo' => 'tesis']);
        $authorization = app(AcademicAuthorizationService::class)->request($this->authorization());
        $thesisAuthorization = app(AcademicAuthorizationService::class)->request($this->authorization(['project_id' => 3]));
        $this->acting('teacher', 1, 7);
        $this->postJson('/api/projects/3/advisors', ['user_id' => 'teacher', 'rol_asesor' => 'asesor'])->assertForbidden();
        $this->putJson('/api/semester-management/exceptions/'.$authorization->id.'/decision', ['version' => 1, 'status' => 'aprobada', 'reason' => 'Coordinación', 'project_scope' => true])->assertOk();
        $this->putJson('/api/semester-management/exceptions/'.$thesisAuthorization->id.'/decision', ['version' => 1, 'status' => 'aprobada', 'reason' => 'Coordinación', 'project_scope' => true])->assertForbidden();
    }

    public function test_named_teacher_no_longer_manages_rooms(): void
    {
        DB::table('carrera_responsables_evaluacion')->insert(['carrera_id' => 1, 'docente_id' => 'teacher']);
        $this->acting('teacher', 1, 2);
        $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Sala docente', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertForbidden();
    }

    public function test_only_head_and_general_admin_get_evaluation_reports_with_teacher_names(): void
    {
        $controller = app(\App\Http\Controllers\API\EvaluationController::class);
        $limited = new \ReflectionMethod($controller, 'shouldAnonymizeTeacherReport');
        $full = \Illuminate\Http\Request::create('/api/evaluations/rooms/1/report.pdf', 'GET');
        $shared = \Illuminate\Http\Request::create('/api/evaluations/rooms/1/report.pdf', 'GET', ['audience' => 'teachers']);

        $this->acting('head', 1, 5);
        $this->assertFalse($limited->invoke($controller, $full));
        $this->assertTrue($limited->invoke($controller, $shared));
        // Asistencia, coordinación y docentes reciben el informe limitado aunque pidan el completo.
        foreach ([6, 7, 2] as $profile) {
            $this->acting('teacher', 1, $profile);
            $this->assertTrue($limited->invoke($controller, $full), "El perfil {$profile} no debe ver nombres de evaluadores.");
        }
    }

    public function test_level_simulation_never_opens_another_users_session_and_drops_admin_powers(): void
    {
        // Ya no existe la ruta para abrir la sesión de una persona específica.
        $this->postJson('/api/admin/view-as', ['carrera_id' => 1, 'usuario_id' => 'teacher'])->assertNotFound();
        // Sólo el administrador general simula niveles.
        $this->postJson('/api/admin/view-level', ['carrera_id' => 1, 'perfil_id' => 2])->assertForbidden();

        DB::table('usuarios')->insert(['id' => 'gadmin', 'nombres' => 'General', 'contrasena' => Hash::make('x'), 'es_administrador_general' => true]);
        $admin = User::withoutGlobalScopes()->findOrFail('gadmin');
        $this->assertTrue($admin->isGeneralAdmin());
        // Simulando coordinación, el administrador pierde sus permisos de administración general.
        app(CareerContext::class)->set($admin, Career::findOrFail(1), 7, 7);
        auth('api')->setUser($admin);
        $this->assertFalse($admin->isGeneralAdmin());
        $this->assertSame(7, (int) $admin->perfil_id);
        $this->assertTrue($admin->canManageProjects());
        $this->assertFalse($admin->canManageAcademics());
    }

    public function test_evaluation_requirements_depend_on_stage_and_one_released_student_is_enough(): void
    {
        $this->assertSame(['release_sheet' => true, 'presentation' => true, 'link' => false, 'report' => null], \App\Services\EvaluationRequirementsService::requirements(5));
        $this->assertSame('avance', \App\Services\EvaluationRequirementsService::requirements(7)['report']);
        $this->assertSame('final', \App\Services\EvaluationRequirementsService::requirements(8)['report']);

        DB::table('usuarios')->insert(['id' => 'student2', 'nombres' => 'Segundo', 'contrasena' => Hash::make('x')]);
        DB::table('usuario_carrera')->insert(['usuario_id' => 'student2', 'carrera_id' => 1, 'perfil_id' => 3]);
        DB::table('proyecto_integrantes')->insert(['proyecto_id' => 1, 'usuario_id' => 'student2', 'rol' => 'integrante']);
        $project = \App\Models\Project::findOrFail(1);
        $period = AcademicPeriod::findOrFail(1);
        $service = fn () => new \App\Services\EvaluationRequirementsService();
        $release = \App\Models\RepositoryDocument::create(['project_id' => 1, 'nombre' => 'Hoja', 'document_category' => \App\Models\RepositoryDocument::CATEGORY_EVALUATION_RELEASE, 'visibility' => 'private', 'activo' => true]);
        \App\Models\RepositoryDocument::create(['project_id' => 1, 'nombre' => 'Presentación', 'document_category' => \App\Models\RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION, 'visibility' => 'private', 'activo' => true]);
        DB::table('liberaciones_documentos_evaluacion')->insert([
            ['documento_repositorio_id' => $release->id, 'alumno_id' => 'student', 'liberado' => true],
            ['documento_repositorio_id' => $release->id, 'alumno_id' => 'student2', 'liberado' => false],
        ]);

        // 5° propuesta: hoja, presentación aprobada por un encargado y un integrante liberado (el otro no presenta).
        $pending = $service()->status($project, 5, $period);
        $this->assertSame(['presentación pendiente de revisión'], $pending['missing']);
        $this->assertSame(1, $pending['released_count']);
        $review = fn (string $delivery, string $status = 'aprobada', ?string $comment = null) => $this->putJson('/api/repositorio/evaluation-documents/review',
            ['project_id' => 1, 'delivery' => $delivery, 'status' => $status, 'comment' => $comment]);
        // Un docente sólo consulta: no aprueba entregas ni libera alumnos.
        $this->acting('teacher', 1, 2);
        $review('presentation')->assertForbidden();
        $this->putJson('/api/repositorio/evaluation-documents/'.$release->id.'/release-status', ['students' => [['student_id' => 'student', 'released' => true]]])->assertForbidden();
        $this->acting('head', 1, 5);
        $review('presentation', 'con_observaciones')->assertUnprocessable();
        $review('presentation', 'con_observaciones', 'Falta la diapositiva de resultados')->assertOk();
        $this->assertSame(['presentación con observaciones'], $service()->status($project, 5, $period)['missing']);
        $review('presentation')->assertOk();
        $this->assertTrue($service()->status($project, 5, $period)['ready']);

        // 6° avance: además enlace de Drive y documento de reporte (el proyecto pasa a un grupo de 6°).
        DB::table('proyectos')->where('id', 1)->update(['grupo_id' => 4]);
        $progress = $service()->status($project, 6, $period);
        $this->assertFalse($progress['ready']);
        $this->assertSame(['enlace de Drive', 'documento de reporte (avance)'], $progress['missing']);

        $this->acting('student', 1, 3);
        $this->postJson('/api/repositorio/evaluation-documents/link', ['project_id' => 1, 'url' => 'https://example.com/video'])->assertUnprocessable();
        $this->postJson('/api/repositorio/evaluation-documents/link', ['project_id' => 1, 'url' => 'https://drive.google.com/drive/folders/abc'])->assertOk();
        \App\Models\RepositoryDocument::create(['project_id' => 1, 'nombre' => 'Reporte', 'document_category' => \App\Models\RepositoryDocument::CATEGORY_EVALUATION_REPORT, 'visibility' => 'private', 'activo' => true]);
        $this->acting('head', 1, 5);
        $review('link')->assertOk();
        $review('report')->assertOk();
        $this->assertTrue($service()->status($project, 6, $period)['ready']);
        // Cambiar el enlace lo devuelve a revisión.
        $this->acting('student', 1, 3);
        $this->postJson('/api/repositorio/evaluation-documents/link', ['project_id' => 1, 'url' => 'https://drive.google.com/drive/folders/otro'])->assertOk();
        $this->assertSame(['enlace de Drive pendiente de revisión'], $service()->status($project, 6, $period)['missing']);
    }

    public function test_presentation_must_be_pptx_without_embedded_video(): void
    {
        $this->acting('student', 1, 3);
        $path = tempnam(sys_get_temp_dir(), 'pptx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('ppt/presentation.xml', '<p:presentation/>');
        $zip->addFromString('ppt/media/media1.mp4', str_repeat('0', 64));
        $zip->close();
        $withVideo = new \Illuminate\Http\UploadedFile($path, 'avance.pptx', null, null, true);
        $response = $this->post('/api/repositorio/evaluation-documents', ['project_id' => 1, 'document_type' => 'presentation', 'archivo' => $withVideo], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertStringContainsString('trae video', json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));
        $pdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('avance.pdf', '%PDF-1.4');
        $this->post('/api/repositorio/evaluation-documents', ['project_id' => 1, 'document_type' => 'presentation', 'archivo' => $pdf], ['Accept' => 'application/json'])->assertUnprocessable();
        @unlink($path);
    }

    public function test_each_question_keeps_its_scale_and_mixed_scales_average_correctly(): void
    {
        DB::table('criterios_rubrica')->insert([
            ['id' => 11, 'rubrica_id' => 1, 'clave' => 'escala5', 'pregunta' => 'Calidad técnica', 'escala' => 'puntos_5', 'orden' => 2],
            ['id' => 12, 'rubrica_id' => 1, 'clave' => 'escala10', 'pregunta' => 'Presentación', 'escala' => 'puntos_10', 'orden' => 3],
        ]);
        $room = $this->postJson('/api/evaluations/rooms', $this->roomData() + ['nombre' => 'Sala mixta', 'responsible_teacher_id' => 'teacher', 'max_attempts' => 1, 'project_order' => ['1' => 1]])->assertCreated()->json('room.id');
        $evaluation = DB::table('evaluaciones')->where('sala_id', $room)->first();
        $release = \App\Models\RepositoryDocument::create(['carrera_id' => 1, 'project_id' => 1, 'nombre' => 'Liberación', 'document_category' => 'evaluation_release_sheet', 'visibility' => 'private', 'uploaded_by' => 'student']);
        DB::table('liberaciones_documentos_evaluacion')->insert(['documento_repositorio_id' => $release->id, 'alumno_id' => 'student', 'liberado' => true]);
        \App\Models\RepositoryDocument::create(['carrera_id' => 1, 'project_id' => 1, 'nombre' => 'Presentación', 'document_category' => 'evaluation_presentation', 'visibility' => 'private', 'uploaded_by' => 'student']);
        DB::table('revisiones_entregas_evaluacion')->insert(['proyecto_id' => 1, 'periodo_id' => 1, 'entrega' => 'presentation', 'estado' => 'aprobada']);
        DB::table('salas_evaluacion')->where('id', $room)->update(['secuencia_bloqueada' => true, 'orden_actual' => 1]);
        DB::table('evaluaciones')->where('id', $evaluation->id)->update(['estado' => 'en_evaluacion']);
        $this->travelTo(now()->addDay()->setTime(10, 30));
        $this->acting('teacher', 1, 2);

        $answers = fn (string $five) => ['scores' => [
            ['criterio' => 'problema', 'valor' => 'de_acuerdo'],
            ['criterio' => 'escala5', 'valor' => $five],
            ['criterio' => 'escala10', 'valor' => '10'],
        ]];
        // Un 6 no existe en la escala del 1 al 5.
        $this->postJson('/api/evaluations/'.$evaluation->id.'/score', $answers('6'))->assertUnprocessable();
        $response = $this->postJson('/api/evaluations/'.$evaluation->id.'/score', $answers('3'))->assertOk();
        // De acuerdo = 75 %, 3 de 5 = 50 %, 10 de 10 = 100 %: promedio 75 % → apenas cumple.
        $response->assertJsonPath('evaluation.global_average', 75)->assertJsonPath('evaluation.verdict.key', 'apenas_cumple');
        $this->assertDatabaseHas('respuestas_evaluacion', ['criterio_id' => 12, 'escala' => 'puntos_10', 'puntaje' => 10, 'porcentaje' => 100]);
        $this->travelBack();
    }

    private function assertSqlRejected(callable $write): void
    {
        DB::statement('SAVEPOINT rechazo');
        try {
            $write();
            $this->fail('MySQL aceptó una fila que debía rechazar.');
        } catch (\Illuminate\Database\QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT rechazo');
            $this->addToAssertionCount(1);
        }
    }

    private function authorization(array $overrides = []): array
    {
        return $overrides + ['period_id' => 1, 'project_id' => 1, 'type' => 'presentacion_semestre', 'presentation_semester' => 8, 'reason' => 'Presentación excepcional del proyecto completo', 'expires_at' => now()->addDays(3)->toDateTimeString()];
    }

    private function roomData(): array
    {
        return ['teacher_ids' => ['teacher'], 'project_ids' => [1], 'salon_id' => 1, 'semestre' => 5,
            'fecha_evaluacion' => now()->addDay()->setTime(10,0)->toDateTimeString(), 'fecha_fin_evaluacion' => now()->addDay()->setTime(12,0)->toDateTimeString(),
            'project_presentation_minutes' => 20, 'teacher_evaluation_minutes' => 15];
    }
}
