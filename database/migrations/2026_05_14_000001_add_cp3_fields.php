<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'company_giro')) {
                $table->string('company_giro')->nullable()->after('company_name');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'apto_titulacion')) {
                $table->boolean('apto_titulacion')->nullable()->after('resultado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'apto_titulacion')) {
                $table->dropColumn('apto_titulacion');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'company_giro')) {
                $table->dropColumn('company_giro');
            }
        });
    }
};
