<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (
                Schema::hasColumn('repository_documents', 'document_category')
                && Schema::hasColumn('repository_documents', 'visibility')
                && Schema::hasColumn('repository_documents', 'activo')
                && !$this->indexExists('repository_documents', 'repo_docs_category_visibility_idx')
            ) {
                $table->index(['document_category', 'visibility', 'activo'], 'repo_docs_category_visibility_idx');
            }
        });

        Schema::table('repository_documents', function (Blueprint $table) {
            if (
                Schema::hasColumn('repository_documents', 'project_id')
                && !$this->foreignKeyExists('repository_documents', 'repository_documents_project_id_foreign')
            ) {
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            }
            if (
                Schema::hasColumn('repository_documents', 'published_by')
                && !$this->foreignKeyExists('repository_documents', 'repository_documents_published_by_foreign')
            ) {
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

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index]
        ));
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return !empty(DB::select(
            'select 1 from information_schema.table_constraints where constraint_schema = ? and table_name = ? and constraint_name = ? and constraint_type = ? limit 1',
            [$database, $table, $constraint, 'FOREIGN KEY']
        ));
    }
};
