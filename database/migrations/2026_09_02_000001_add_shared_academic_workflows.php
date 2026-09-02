<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('rfc', 13)->nullable()->after('nombre');
            $table->enum('estado_validacion', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente')->after('direccion');
            $table->string('solicitada_por', 20)->nullable()->after('estado_validacion');
            $table->string('validada_por', 20)->nullable()->after('solicitada_por');
            $table->text('comentario_validacion')->nullable()->after('validada_por');
            $table->dateTime('validada_en')->nullable()->after('comentario_validacion');
            $table->dateTime('creada_en')->useCurrent()->after('validada_en');
            $table->dateTime('actualizada_en')->nullable()->after('creada_en');
            $table->unique('rfc', 'uq_empresas_rfc');
            $table->index(['estado_validacion', 'nombre'], 'idx_empresas_estado_nombre');
        });

        DB::table('empresas')->whereNull('rfc')->update(['estado_validacion' => 'aprobada']);

        Schema::table('proyectos', function (Blueprint $table) {
            $table->enum('modalidad', ['dual', 'proyecto_integrador', 'caso_integrador'])
                ->default('proyecto_integrador')->after('tipo');
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->boolean('es_seguimiento_proyecto')->default(false)->after('activo');
            $table->string('clave_autoregistro')->nullable()->after('es_seguimiento_proyecto');
            $table->dateTime('clave_actualizada_en')->nullable()->after('clave_autoregistro');
            $table->string('clave_actualizada_por', 20)->nullable()->after('clave_actualizada_en');
        });

        Schema::create('curso_estudiantes', function (Blueprint $table) {
            $table->unsignedBigInteger('curso_id');
            $table->string('estudiante_id', 20);
            $table->dateTime('inscrito_en')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->primary(['curso_id', 'estudiante_id']);
            $table->index(['estudiante_id', 'activo'], 'idx_curso_estudiantes_usuario');
            $table->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_estudiantes');
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['es_seguimiento_proyecto', 'clave_autoregistro', 'clave_actualizada_en', 'clave_actualizada_por']);
        });
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn('modalidad'));
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique('uq_empresas_rfc');
            $table->dropIndex('idx_empresas_estado_nombre');
            $table->dropColumn(['rfc', 'estado_validacion', 'solicitada_por', 'validada_por', 'comentario_validacion', 'validada_en', 'creada_en', 'actualizada_en']);
        });
    }
};
