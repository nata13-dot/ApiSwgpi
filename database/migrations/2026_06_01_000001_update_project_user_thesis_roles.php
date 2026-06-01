<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2','primario','secundario') NULL DEFAULT NULL");
        DB::table('project_user')->where('rol_asesor', 'primario')->update(['rol_asesor' => 'asesor']);
        DB::table('project_user')->where('rol_asesor', 'secundario')->update(['rol_asesor' => 'revisor_1']);
        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2','primario','secundario') NULL DEFAULT NULL");
        DB::table('project_user')->where('rol_asesor', 'asesor')->update(['rol_asesor' => 'primario']);
        DB::table('project_user')->where('rol_asesor', 'revisor_1')->update(['rol_asesor' => 'secundario']);
        DB::table('project_user')->where('rol_asesor', 'revisor_2')->update(['rol_asesor' => null]);
        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NULL DEFAULT NULL");
    }
};
