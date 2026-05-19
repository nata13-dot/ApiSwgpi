<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverables', 'categoria')) {
                $table->string('categoria', 60)->default('materia')->after('competencia_id');
                $table->index(['project_id', 'categoria'], 'deliverables_project_categoria_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            if (Schema::hasColumn('deliverables', 'categoria')) {
                $table->dropIndex('deliverables_project_categoria_idx');
                $table->dropColumn('categoria');
            }
        });
    }
};
