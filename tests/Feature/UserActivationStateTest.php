<?php

namespace Tests\Feature;

use App\Http\Controllers\API\UserController;
use App\Models\Career;
use App\Models\User;
use App\Support\CareerContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserActivationStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->string('id', 10)->primary();
            $table->string('nombres')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('correo')->nullable();
            $table->string('contrasena')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedInteger('perfil_id');
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        Schema::create('usuario_carrera', function (Blueprint $table): void {
            $table->id();
            $table->string('usuario_id', 10);
            $table->unsignedInteger('carrera_id');
            $table->unsignedInteger('perfil_id');
            $table->boolean('es_principal')->default(true);
            $table->boolean('activo')->default(true);
            $table->string('asignado_por', 10)->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        Schema::create('grupos_academicos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('carrera_id');
            $table->unsignedInteger('semestre')->nullable();
            $table->string('clave_grupo')->nullable();
            $table->boolean('activo')->default(true);
        });
        Schema::create('grupo_estudiantes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('grupo_id');
            $table->string('estudiante_id', 10);
            $table->boolean('activo')->default(true);
            $table->timestamp('inscrito_en')->nullable();
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('grupo_id');
        });
        Schema::create('curso_docentes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('curso_id');
            $table->string('docente_id', 10);
            $table->boolean('activo')->default(true);
        });

        DB::table('usuarios')->insert([
            ['id' => 'ADMIN0001', 'nombres' => 'General', 'perfil_id' => 4, 'activo' => true],
            ['id' => 'STUDENT001', 'nombres' => 'Inactivo', 'perfil_id' => 3, 'activo' => false],
            ['id' => 'STUDENT002', 'nombres' => 'Activo', 'perfil_id' => 3, 'activo' => true],
        ]);
        DB::table('usuario_carrera')->insert([
            ['usuario_id' => 'STUDENT001', 'carrera_id' => 1, 'perfil_id' => 3, 'activo' => false],
            ['usuario_id' => 'STUDENT002', 'carrera_id' => 1, 'perfil_id' => 3, 'activo' => true],
        ]);

        $admin = User::withoutGlobalScopes()->findOrFail('ADMIN0001');
        $career = new Career();
        $career->setAttribute('id', 1);
        app(CareerContext::class)->set($admin, $career, 1);
        auth('api')->setUser($admin);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('curso_docentes');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('grupo_estudiantes');
        Schema::dropIfExists('grupos_academicos');
        Schema::dropIfExists('usuario_carrera');
        Schema::dropIfExists('usuarios');

        parent::tearDown();
    }

    public function test_inactive_users_remain_visible_and_can_be_reactivated(): void
    {
        $controller = app(UserController::class);
        $list = $controller->index(Request::create('/api/users', 'GET', [
            'compact' => true,
            'status' => 'inactive',
        ]))->getData(true);

        $this->assertContains('STUDENT001', array_column($list['data'], 'id'));

        $response = $controller->toggleActive(
            Request::create('/api/users/STUDENT001/toggle-active', 'POST'),
            'STUDENT001'
        );

        $this->assertSame(200, $response->status());
        $this->assertTrue($response->getData(true)['activo']);
        $this->assertDatabaseHas('usuarios', ['id' => 'STUDENT001', 'activo' => true]);
        $this->assertDatabaseHas('usuario_carrera', ['usuario_id' => 'STUDENT001', 'activo' => true]);
    }

    public function test_deactivation_updates_both_effective_states_and_keeps_user_recoverable(): void
    {
        $controller = app(UserController::class);
        $response = $controller->toggleActive(
            Request::create('/api/users/STUDENT002/toggle-active', 'POST'),
            'STUDENT002'
        );

        $this->assertSame(200, $response->status());
        $this->assertFalse($response->getData(true)['activo']);
        $this->assertDatabaseHas('usuarios', ['id' => 'STUDENT002', 'activo' => false]);
        $this->assertDatabaseHas('usuario_carrera', ['usuario_id' => 'STUDENT002', 'activo' => false]);

        $list = $controller->index(Request::create('/api/users', 'GET', [
            'compact' => true,
            'status' => 'inactive',
        ]))->getData(true);

        $this->assertContains('STUDENT002', array_column($list['data'], 'id'));
    }
}
