<?php

namespace Tests\Feature;

use App\Http\Controllers\API\DeliverableController;
use App\Models\Deliverable;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\RepositoryDocument;
use App\Models\User;
use App\Services\DeliverySubmissionService;
use App\Services\ProtectedDownloadService;
use App\Services\RepositoryFileService;
use App\Services\StoredFileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class DeliverySubmissionServiceTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Storage::fake('local');
        Storage::fake('legacy_public');
        Storage::fake('swgpi_private');
        config([
            'uploads.temporary_disk' => 'local',
            'uploads.write_disk' => 'legacy_public',
            'uploads.legacy_disk' => 'legacy_public',
            'uploads.private_disk' => 'swgpi_private',
            'uploads.legacy_public_fallback' => true,
            'uploads.x_accel_downloads' => false,
        ]);
        $this->seedContext();
    }

    protected function tearDown(): void
    {
        DocumentVersion::flushEventListeners();
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        Schema::dropAllTables();
        parent::tearDown();
    }

    public function test_first_submission_creates_delivery_document_and_version_one(): void
    {
        $result = $this->submit('primera.pdf');

        $this->assertDatabaseCount('entregas', 1);
        $this->assertDatabaseCount('documentos', 1);
        $this->assertDatabaseHas('documento_versiones', [
            'documento_id' => $result['document']->id,
            'numero_version' => 1,
            'disco' => 'legacy_public',
            'nombre_original' => 'primera.pdf',
        ]);
        Storage::disk('legacy_public')->assertExists($result['version']->file_path);
    }

    public function test_resubmission_reuses_logical_delivery_and_preserves_version_one(): void
    {
        $first = $this->submit('primera.pdf');
        $second = $this->submit('corregida.pdf');

        $this->assertDatabaseCount('entregas', 1);
        $this->assertDatabaseCount('documentos', 1);
        $this->assertSame($first['document']->id, $second['document']->id);
        $this->assertSame(2, $second['version']->version_number);
        $this->assertDatabaseHas('documento_versiones', ['documento_id' => $first['document']->id, 'numero_version' => 1]);
        $this->assertDatabaseHas('documento_versiones', ['documento_id' => $first['document']->id, 'numero_version' => 2]);
        Storage::disk('legacy_public')->assertExists($first['version']->file_path);
        Storage::disk('legacy_public')->assertExists($second['version']->file_path);
    }

    public function test_repeated_submissions_keep_unique_version_numbers_and_one_delivery(): void
    {
        $this->submit('uno.pdf');
        $this->submit('dos.pdf');
        $this->submit('tres.pdf');

        $this->assertDatabaseCount('entregas', 1);
        $this->assertSame([1, 2, 3], DB::table('documento_versiones')->orderBy('numero_version')->pluck('numero_version')->all());

        $documentId = DB::table('documentos')->value('id');
        $this->expectException(QueryException::class);
        DB::table('documento_versiones')->insert([
            'documento_id' => $documentId,
            'numero_version' => 3,
            'nombre_archivo' => 'colision.pdf',
            'ruta_archivo' => 'colision.pdf',
            'creado_en' => now(),
        ]);
    }

    public function test_a_version_unique_collision_is_retried_in_a_controlled_way(): void
    {
        $service = new class(app(StoredFileService::class)) extends DeliverySubmissionService
        {
            public int $attempts = 0;

            protected function persist(
                Deliverable $deliverable,
                Project $project,
                User $user,
                array $prepared,
                array $stored
            ): array {
                $this->attempts++;
                if ($this->attempts === 1) {
                    $previous = new PDOException(
                        'SQLSTATE[23000]: Integrity constraint violation: UNIQUE constraint failed: documento_versiones.documento_id, documento_versiones.numero_version'
                    );
                    $previous->errorInfo = ['23000', 19, 'UNIQUE constraint failed: documento_versiones.documento_id, documento_versiones.numero_version'];
                    throw new QueryException('sqlite', 'insert into documento_versiones', [], $previous);
                }

                return parent::persist($deliverable, $project, $user, $prepared, $stored);
            }
        };

        $result = $service->submit(
            Deliverable::findOrFail(1), Project::findOrFail(10), User::findOrFail('student'),
            $this->pdf('colision-controlada.pdf'), ['pdf'], 50
        );

        $this->assertSame(2, $service->attempts);
        $this->assertSame(1, $result['version']->version_number);
        $this->assertDatabaseCount('entregas', 1);
        $this->assertDatabaseCount('documento_versiones', 1);
    }

    public function test_wrong_project_is_rejected(): void
    {
        DB::table('proyectos')->insert([
            'id' => 20, 'carrera_id' => 1, 'grupo_id' => 99, 'titulo' => 'Otro',
            'activo' => true, 'creado_en' => now(), 'actualizado_en' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(DeliverySubmissionService::class)->submit(
            Deliverable::findOrFail(1), Project::findOrFail(20), User::findOrFail('student'),
            $this->pdf('archivo.pdf'), ['pdf'], 50
        );
    }

    public function test_upload_endpoint_requires_project_id(): void
    {
        $this->withoutMiddleware();
        auth('api')->setUser(User::findOrFail('student'));

        $this->postJson('/api/deliverables/1/upload', [
            'archivo' => $this->pdf('archivo.pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');
    }

    public function test_unauthorized_user_is_rejected(): void
    {
        DB::table('usuarios')->insert([
            'id' => 'outsider', 'nombres' => 'Ajeno', 'apellido_paterno' => 'Usuario',
            'perfil_id' => 3, 'activo' => true,
        ]);

        $this->expectException(AuthorizationException::class);
        app(DeliverySubmissionService::class)->submit(
            Deliverable::findOrFail(1), Project::findOrFail(10), User::findOrFail('outsider'),
            $this->pdf('archivo.pdf'), ['pdf'], 50
        );
    }

    public function test_database_failure_removes_new_file_and_temporary_file(): void
    {
        DocumentVersion::creating(function (): void {
            throw new RuntimeException('Fallo de BD simulado');
        });

        try {
            $this->submit('fallo.pdf');
            $this->fail('La operación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de BD simulado', $exception->getMessage());
        }

        $this->assertDatabaseCount('entregas', 0);
        $this->assertDatabaseCount('documentos', 0);
        $this->assertSame([], Storage::disk('legacy_public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_legacy_public_version_is_resolved_and_downloaded_by_laravel(): void
    {
        Storage::disk('legacy_public')->put('repositorio/legacy.pdf', "%PDF-1.7\nlegacy");
        $version = new DocumentVersion([
            'file_name' => 'legacy.pdf',
            'file_path' => 'repositorio/legacy.pdf',
            'mime_type' => 'application/pdf',
        ]);

        [$disk, $path] = app(ProtectedDownloadService::class)->resolve($version);
        $response = app(ProtectedDownloadService::class)->download($version);

        $this->assertSame('legacy_public', $disk);
        $this->assertSame('repositorio/legacy.pdf', $path);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Accel-Redirect'));
    }

    public function test_legacy_fallback_resolves_a_file_missing_from_recorded_disk(): void
    {
        Storage::disk('legacy_public')->put('repositorio/fallback.pdf', "%PDF-1.7\nfallback");
        $version = new DocumentVersion([
            'file_name' => 'fallback.pdf',
            'file_path' => 'repositorio/fallback.pdf',
            'disk' => 'swgpi_private',
            'mime_type' => 'application/pdf',
        ]);

        $this->assertSame(
            ['legacy_public', 'repositorio/fallback.pdf'],
            app(ProtectedDownloadService::class)->resolve($version)
        );
    }

    public function test_private_download_can_return_x_accel_header_when_enabled(): void
    {
        Storage::disk('swgpi_private')->put('deliverables/private.pdf', "%PDF-1.7\nprivate");
        config(['uploads.x_accel_downloads' => true]);
        $version = new DocumentVersion([
            'file_name' => 'private.pdf',
            'file_path' => 'deliverables/private.pdf',
            'disk' => 'swgpi_private',
            'mime_type' => 'application/pdf',
        ]);

        $response = app(ProtectedDownloadService::class)->download($version);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/_swgpi_private/deliverables/private.pdf', $response->headers->get('X-Accel-Redirect'));
    }

    public function test_student_listing_uses_latest_version_and_delivery_grade(): void
    {
        $first = $this->submit('version-uno.pdf');
        $second = $this->submit('version-dos.pdf');
        DB::table('entregas')->update(['calificacion' => 91]);
        DB::table('entregables')->insert([
            'id' => 2, 'carrera_id' => 1, 'curso_id' => 100, 'nombre' => 'Pendiente',
            'tipo_documento' => 'documento', 'estado' => 'publicado', 'activo' => true,
            'creado_en' => now()->subMinute(),
        ]);

        $this->withoutMiddleware();
        auth('api')->setUser(User::findOrFail('student'));
        $response = $this->getJson('/api/my-deliverables')->assertOk();
        $items = collect($response->json('data'));
        $submitted = $items->firstWhere('id', 1);
        $pending = $items->firstWhere('id', 2);

        $this->assertCount(2, $items);
        $this->assertSame(2, $submitted['version_number']);
        $this->assertSame($second['version']->file_path, $submitted['archivo_path']);
        $this->assertEquals(91.0, $submitted['calificacion']);
        $this->assertDatabaseHas('documento_versiones', ['id' => $first['version']->id, 'numero_version' => 1]);
        $this->assertNull($pending['archivo_path']);
        $this->assertNull($pending['delivery_id']);

        $catalogItem = $this->getJson('/api/deliverables?project_id=10')
            ->assertOk()
            ->json('data.0');
        $this->assertSame(10, $catalogItem['project_id']);
        $this->assertSame(2, $catalogItem['version_number']);
        $this->assertSame($second['version']->file_path, $catalogItem['archivo_path']);
    }

    public function test_same_deliverable_keeps_projects_and_files_separate(): void
    {
        $projectOne = $this->submit('proyecto-uno.pdf');
        DB::table('proyectos')->insert([
            'id' => 20, 'carrera_id' => 1, 'grupo_id' => 5, 'titulo' => 'Proyecto Dos',
            'activo' => true, 'creado_en' => now(), 'actualizado_en' => now(),
        ]);
        DB::table('proyecto_integrantes')->insert([
            'proyecto_id' => 20, 'usuario_id' => 'student', 'rol' => 'lider',
        ]);
        $projectTwo = app(DeliverySubmissionService::class)->submit(
            Deliverable::findOrFail(1), Project::findOrFail(20), User::findOrFail('student'),
            $this->pdf('proyecto-dos.pdf'), ['pdf'], 50
        );

        $this->withoutMiddleware();
        auth('api')->setUser(User::findOrFail('student'));
        $items = collect($this->getJson('/api/my-deliverables')->assertOk()->json('data'));

        $this->assertCount(2, $items);
        $this->assertSame($projectOne['version']->file_path, $items->firstWhere('project_id', 10)['archivo_path']);
        $this->assertSame($projectTwo['version']->file_path, $items->firstWhere('project_id', 20)['archivo_path']);
        $this->getJson('/api/deliverables/1/download?project_id=999')->assertUnprocessable();
    }

    public function test_teacher_matrix_reads_multiple_project_deliveries_without_duplication(): void
    {
        DB::table('usuarios')->insert([
            'id' => 'teacher', 'nombres' => 'Docente', 'apellido_paterno' => 'Prueba',
            'perfil_id' => 2, 'activo' => true,
        ]);
        DB::table('proyecto_integrantes')->insert([
            'proyecto_id' => 10, 'usuario_id' => 'teacher', 'rol' => 'asesor',
        ]);
        $this->submit('matriz.pdf');
        DB::table('entregas')->update(['calificacion' => 88]);

        $this->withoutMiddleware();
        auth('api')->setUser(User::findOrFail('teacher'));
        $response = $this->getJson('/api/teacher/deliverables-matrix')->assertOk();

        $this->assertSame(88.0, (float) $response->json('data.0.students.0.items.0.calificacion'));
        $this->assertSame(1, $response->json('data.0.students.0.items.0.deliverable.id'));
        $this->assertDatabaseCount('entregas', 1);
    }

    public function test_grading_updates_only_the_requested_project_delivery(): void
    {
        $this->submit('calificar-uno.pdf');
        DB::table('proyectos')->insert([
            'id' => 20, 'carrera_id' => 1, 'grupo_id' => 5, 'titulo' => 'Proyecto Dos',
            'activo' => true, 'creado_en' => now(), 'actualizado_en' => now(),
        ]);
        DB::table('proyecto_integrantes')->insert(['proyecto_id' => 20, 'usuario_id' => 'student', 'rol' => 'integrante']);
        app(DeliverySubmissionService::class)->submit(
            Deliverable::findOrFail(1), Project::findOrFail(20), User::findOrFail('student'),
            $this->pdf('calificar-dos.pdf'), ['pdf'], 50
        );
        DB::table('usuarios')->insert([
            'id' => 'admin', 'nombres' => 'Admin', 'apellido_paterno' => 'Prueba',
            'perfil_id' => 1, 'activo' => true,
        ]);

        $this->withoutMiddleware();
        auth('api')->setUser(User::findOrFail('admin'));
        $this->postJson('/api/deliverables/1/calificar', [
            'project_id' => 20,
            'calificacion' => 95,
        ])->assertOk();

        $this->assertDatabaseHas('entregas', ['entregable_id' => 1, 'proyecto_id' => 20, 'calificacion' => 95]);
        $this->assertDatabaseHas('entregas', ['entregable_id' => 1, 'proyecto_id' => 10, 'calificacion' => null]);
    }

    public function test_evaluation_deliverable_creation_uses_courses_without_legacy_pivot(): void
    {
        $controller = app(DeliverableController::class);
        $method = new \ReflectionMethod($controller, 'ensureEvaluationDeliverables');
        $method->invoke($controller, Project::findOrFail(10));

        $this->assertFalse(Schema::hasTable('entregables_proyecto'));
        $this->assertDatabaseHas('entregables', [
            'curso_id' => 100,
            'nombre' => 'Diapositivas de evaluacion',
        ]);
    }

    public function test_repository_replacement_creates_a_new_version_and_keeps_the_previous_file(): void
    {
        $user = User::findOrFail('student');
        $service = app(RepositoryFileService::class);
        $first = $service->persist($this->pdf('repo-uno.pdf'), $user, fn () => RepositoryDocument::create([
            'project_id' => 10, 'nombre' => 'Documento', 'document_category' => 'repository',
            'visibility' => 'private', 'uploaded_by' => $user->id, 'activo' => true,
        ]), ['pdf']);
        $second = $service->persist($this->pdf('repo-dos.pdf'), $user, function () use ($first) {
            $document = RepositoryDocument::query()->lockForUpdate()->findOrFail($first['document']->id);
            $document->update(['descripcion' => 'Segunda versión']);

            return $document;
        }, ['pdf']);

        $this->assertSame($first['document']->id, $second['document']->id);
        $this->assertSame(2, $second['version']->version_number);
        $this->assertDatabaseHas('documento_versiones', ['id' => $first['version']->id, 'numero_version' => 1]);
        Storage::disk('legacy_public')->assertExists($first['version']->file_path);
        Storage::disk('legacy_public')->assertExists($second['version']->file_path);
    }

    public function test_repository_download_visibility_rules_use_latest_version(): void
    {
        Storage::disk('legacy_public')->put('repository/public.pdf', "%PDF-1.7\npublic");
        $publishedId = DB::table('documentos')->insertGetId([
            'titulo' => 'Público', 'categoria' => 'repositorio', 'visibilidad' => 'publico',
            'estado' => 'publicado', 'publicado_en' => now(), 'activo' => true,
        ]);
        DB::table('documento_versiones')->insert([
            'documento_id' => $publishedId, 'numero_version' => 1, 'nombre_archivo' => 'public.pdf',
            'ruta_archivo' => 'repository/public.pdf', 'disco' => 'legacy_public',
            'mime_type' => 'application/pdf', 'creado_en' => now(),
        ]);
        $draftId = DB::table('documentos')->insertGetId([
            'titulo' => 'Borrador', 'categoria' => 'repositorio', 'visibilidad' => 'publico',
            'estado' => 'borrador', 'publicado_en' => null, 'activo' => true,
        ]);
        DB::table('documento_versiones')->insert([
            'documento_id' => $draftId, 'numero_version' => 1, 'nombre_archivo' => 'draft.pdf',
            'ruta_archivo' => 'repository/public.pdf', 'disco' => 'legacy_public',
            'mime_type' => 'application/pdf', 'creado_en' => now(),
        ]);
        $privateId = DB::table('documentos')->insertGetId([
            'proyecto_id' => 10, 'titulo' => 'Privado', 'categoria' => 'repositorio', 'visibilidad' => 'privado',
            'estado' => 'borrador', 'publicado_en' => null, 'activo' => true,
        ]);
        DB::table('documento_versiones')->insert([
            'documento_id' => $privateId, 'numero_version' => 1, 'nombre_archivo' => 'private.pdf',
            'ruta_archivo' => 'repository/public.pdf', 'disco' => 'legacy_public',
            'mime_type' => 'application/pdf', 'creado_en' => now(),
        ]);

        $this->withoutMiddleware();
        auth('api')->forgetUser();
        $this->get("/api/repositorio/{$publishedId}/download")->assertOk();
        $this->getJson("/api/repositorio/{$draftId}/download")->assertNotFound();
        $this->getJson("/api/repositorio/{$privateId}/download")->assertNotFound();
        auth('api')->setUser(User::findOrFail('student'));
        $this->get("/api/repositorio/{$privateId}/download")->assertOk();
    }

    public function test_repository_validation_and_database_compensation_use_secure_file_service(): void
    {
        $user = User::findOrFail('student');
        $service = app(RepositoryFileService::class);
        try {
            $service->persist($this->pdf('archivo.zip'), $user, fn () => throw new RuntimeException('No debe persistir'), ['zip']);
            $this->fail('El MIME discordante debía rechazarse.');
        } catch (ValidationException) {
            $this->assertSame([], Storage::disk('legacy_public')->allFiles());
        }

        try {
            $service->persist($this->pdf('compensar.pdf'), $user, fn () => throw new RuntimeException('Fallo de BD'), ['pdf']);
            $this->fail('La operación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de BD', $exception->getMessage());
            $this->assertSame([], Storage::disk('legacy_public')->allFiles());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    private function submit(string $name): array
    {
        return app(DeliverySubmissionService::class)->submit(
            Deliverable::findOrFail(1), Project::findOrFail(10), User::findOrFail('student'),
            $this->pdf($name), ['pdf'], 50
        );
    }

    private function pdf(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'swgpi-delivery-');
        file_put_contents($path, "%PDF-1.7\n{$name}\n");
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, 'application/pdf', UPLOAD_ERR_OK, true);
    }

    private function seedContext(): void
    {
        DB::table('usuarios')->insert([
            'id' => 'student', 'nombres' => 'Alumno', 'apellido_paterno' => 'Prueba',
            'perfil_id' => 3, 'activo' => true,
        ]);
        DB::table('grupos_academicos')->insert([
            'id' => 5, 'carrera_id' => 1, 'nombre' => 'Grupo 5', 'clave_grupo' => 'A',
            'semestre' => 7, 'activo' => true, 'creado_en' => now(), 'actualizado_en' => now(),
        ]);
        DB::table('asignaturas')->insert([
            'id' => 50, 'carrera_id' => 1, 'clave' => 'SWG-01', 'nombre' => 'Proyecto Integrador', 'activo' => true,
        ]);
        DB::table('competencias')->insert([
            'id' => 60, 'asignatura_id' => 50, 'nombre' => 'Competencia uno',
            'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString(),
        ]);
        DB::table('cursos')->insert([
            'id' => 100, 'carrera_id' => 1, 'grupo_id' => 5, 'asignatura_id' => 50,
            'activo' => true, 'es_seguimiento_proyecto' => true,
        ]);
        DB::table('proyectos')->insert([
            'id' => 10, 'carrera_id' => 1, 'grupo_id' => 5, 'titulo' => 'Proyecto',
            'activo' => true, 'creado_en' => now(), 'actualizado_en' => now(),
        ]);
        DB::table('proyecto_integrantes')->insert([
            'proyecto_id' => 10, 'usuario_id' => 'student', 'rol' => 'integrante',
        ]);
        DB::table('grupo_estudiantes')->insert([
            'grupo_id' => 5, 'estudiante_id' => 'student', 'activo' => true, 'inscrito_en' => now(),
        ]);
        DB::table('entregables')->insert([
            'id' => 1, 'carrera_id' => 1, 'curso_id' => 100, 'nombre' => 'Reporte',
            'tipo_documento' => 'documento', 'estado' => 'publicado', 'activo' => true,
            'creado_en' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->string('id', 20)->primary();
            $table->string('nombres');
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->unsignedTinyInteger('perfil_id');
            $table->boolean('activo')->default(true);
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('asignatura_id')->nullable();
            $table->boolean('activo');
            $table->boolean('es_seguimiento_proyecto')->default(false);
        });
        Schema::create('grupos_academicos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id');
            $table->string('nombre');
            $table->string('clave_grupo')->nullable();
            $table->unsignedTinyInteger('semestre');
            $table->unsignedBigInteger('periodo_id')->nullable();
            $table->boolean('activo');
            $table->dateTime('creado_en');
            $table->dateTime('actualizado_en')->nullable();
        });
        Schema::create('asignaturas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id');
            $table->string('clave');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo');
        });
        Schema::create('competencias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asignatura_id');
            $table->string('nombre');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
        });
        Schema::create('grupo_estudiantes', function (Blueprint $table): void {
            $table->unsignedBigInteger('grupo_id');
            $table->string('estudiante_id', 20);
            $table->boolean('activo');
            $table->dateTime('inscrito_en');
        });
        Schema::create('proyectos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id');
            $table->unsignedInteger('grupo_id');
            $table->string('titulo');
            $table->boolean('activo');
            $table->dateTime('creado_en');
            $table->dateTime('actualizado_en')->nullable();
        });
        Schema::create('proyecto_integrantes', function (Blueprint $table): void {
            $table->unsignedBigInteger('proyecto_id');
            $table->string('usuario_id', 20);
            $table->string('rol');
        });
        Schema::create('entregables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id');
            $table->unsignedBigInteger('curso_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('tipo_documento');
            $table->string('estado');
            $table->boolean('activo');
            $table->dateTime('creado_en');
        });
        Schema::create('documentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id')->nullable();
            $table->unsignedBigInteger('proyecto_id')->nullable();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('categoria')->default('repositorio');
            $table->string('visibilidad')->default('privado');
            $table->string('estado')->default('borrador');
            $table->string('autor_nombre')->nullable();
            $table->string('subido_por', 20)->nullable();
            $table->string('publicado_por', 20)->nullable();
            $table->dateTime('publicado_en')->nullable();
            $table->boolean('activo');
            $table->dateTime('creado_en')->nullable();
            $table->dateTime('actualizado_en')->nullable();
        });
        Schema::create('entregas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entregable_id');
            $table->unsignedBigInteger('proyecto_id');
            $table->unsignedBigInteger('documento_id');
            $table->string('enviado_por', 20)->nullable();
            $table->dateTime('entregado_en');
            $table->decimal('calificacion', 5, 2)->nullable();
            $table->text('comentarios_docente')->nullable();
            $table->unique(['entregable_id', 'proyecto_id']);
        });
        Schema::create('documento_versiones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('documento_id');
            $table->unsignedInteger('numero_version');
            $table->string('nombre_archivo');
            $table->string('nombre_original')->nullable();
            $table->string('ruta_archivo');
            $table->string('disco', 32)->nullable();
            $table->string('extension')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('subido_por', 20)->nullable();
            $table->dateTime('creado_en');
            $table->unique(['documento_id', 'numero_version'], 'uq_documento_version');
        });
    }
}
