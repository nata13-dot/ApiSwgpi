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

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE project_user SET rol_asesor = 'secundario' WHERE rol_asesor IS NULL");
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NOT NULL DEFAULT 'secundario'");
        }
    }
};
