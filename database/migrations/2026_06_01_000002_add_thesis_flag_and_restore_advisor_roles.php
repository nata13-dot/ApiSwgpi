<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'is_thesis')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->boolean('is_thesis')->default(false)->after('activo')->index();
            });
        }

        if (Schema::hasTable('project_user') && Schema::hasColumn('project_user', 'rol_asesor')) {
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario','asesor','revisor_1','revisor_2') NULL DEFAULT NULL");
            DB::table('project_user')->where('rol_asesor', 'asesor')->update(['rol_asesor' => 'primario']);
            DB::table('project_user')->where('rol_asesor', 'revisor_1')->update(['rol_asesor' => 'secundario']);
            DB::table('project_user')->where('rol_asesor', 'revisor_2')->update(['rol_asesor' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'is_thesis')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('is_thesis');
            });
        }

        if (Schema::hasTable('project_user') && Schema::hasColumn('project_user', 'rol_asesor')) {
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NULL DEFAULT NULL");
        }
    }
};
