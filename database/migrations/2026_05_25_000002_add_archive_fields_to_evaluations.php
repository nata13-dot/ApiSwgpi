<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('finalized_at');
            }
            if (!Schema::hasColumn('evaluations', 'archived_by')) {
                $table->string('archived_by', 10)->nullable()->after('archived_at');
                $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
            }
            if (
                Schema::hasColumn('evaluations', 'archived_at')
                && !$this->indexExists('evaluations', 'evaluations_archived_at_idx')
            ) {
                $table->index('archived_at', 'evaluations_archived_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'archived_by')) {
                $table->dropForeign(['archived_by']);
                $table->dropColumn('archived_by');
            }
            if (Schema::hasColumn('evaluations', 'archived_at')) {
                if ($this->indexExists('evaluations', 'evaluations_archived_at_idx')) {
                    $table->dropIndex('evaluations_archived_at_idx');
                }
                $table->dropColumn('archived_at');
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
};
