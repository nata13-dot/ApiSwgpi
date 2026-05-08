<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Agregar campos faltantes documentados
            if (!Schema::hasColumn('projects', 'year')) {
                $table->integer('year')->nullable()->after('description');
            }
            
            if (!Schema::hasColumn('projects', 'file_path')) {
                $table->string('file_path', 255)->nullable()->after('year');
            }
            
            if (!Schema::hasColumn('projects', 'authors')) {
                $table->text('authors')->nullable()->after('file_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'year')) {
                $table->dropColumn('year');
            }
            if (Schema::hasColumn('projects', 'file_path')) {
                $table->dropColumn('file_path');
            }
            if (Schema::hasColumn('projects', 'authors')) {
                $table->dropColumn('authors');
            }
        });
    }
};
