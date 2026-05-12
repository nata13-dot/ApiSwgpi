<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'semestre')) {
                $table->unsignedTinyInteger('semestre')->nullable()->after('description');
            }
            if (!Schema::hasColumn('projects', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('semestre');
            }
            if (!Schema::hasColumn('projects', 'authors')) {
                $table->text('authors')->nullable()->after('year');
            }
            if (!Schema::hasColumn('projects', 'file_path')) {
                $table->string('file_path')->nullable()->after('authors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'file_path')) $table->dropColumn('file_path');
            if (Schema::hasColumn('projects', 'authors')) $table->dropColumn('authors');
            if (Schema::hasColumn('projects', 'year')) $table->dropColumn('year');
            if (Schema::hasColumn('projects', 'semestre')) $table->dropColumn('semestre');
        });
    }
};