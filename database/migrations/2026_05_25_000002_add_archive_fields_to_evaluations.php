<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->index('archived_at', 'evaluations_archived_at_idx');
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
                $table->dropIndex('evaluations_archived_at_idx');
                $table->dropColumn('archived_at');
            }
        });
    }
};
