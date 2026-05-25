<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('repository_documents', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('repository_documents', 'document_category')) {
                $table->string('document_category', 60)->default('repository')->after('archivo_tipo');
            }
            if (!Schema::hasColumn('repository_documents', 'visibility')) {
                $table->string('visibility', 20)->default('public')->after('document_category');
            }
            if (!Schema::hasColumn('repository_documents', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('visibility');
            }
            if (!Schema::hasColumn('repository_documents', 'published_by')) {
                $table->string('published_by', 10)->nullable()->after('published_at');
            }
        });

        Schema::table('repository_documents', function (Blueprint $table) {
            if (Schema::hasColumn('repository_documents', 'project_id')) {
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                $table->index(['document_category', 'visibility', 'activo'], 'repo_docs_category_visibility_idx');
            }
            if (Schema::hasColumn('repository_documents', 'published_by')) {
                $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            if (Schema::hasColumn('repository_documents', 'published_by')) {
                $table->dropForeign(['published_by']);
            }
            if (Schema::hasColumn('repository_documents', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropIndex('repo_docs_category_visibility_idx');
            }
        });

        Schema::table('repository_documents', function (Blueprint $table) {
            foreach (['published_by', 'published_at', 'visibility', 'document_category', 'project_id'] as $column) {
                if (Schema::hasColumn('repository_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
