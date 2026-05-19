<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->index(['evaluation_room_id', 'presentation_order', 'sequence_status'], 'evaluations_room_order_status_idx');
            $table->index(['estado', 'updated_at'], 'evaluations_estado_updated_idx');
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->index(['evaluation_id', 'last_submitted_at'], 'evaluation_attempts_eval_submitted_idx');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->index(['competencia_id', 'estado', 'activo'], 'deliverables_competence_status_idx');
            $table->index(['submitted_by', 'activo'], 'deliverables_submitter_active_idx');
        });

        Schema::table('repository_document_tag', function (Blueprint $table) {
            $table->index(['document_tag_id', 'repository_document_id'], 'repo_tag_document_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repository_document_tag', function (Blueprint $table) {
            $table->dropIndex('repo_tag_document_idx');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropIndex('deliverables_submitter_active_idx');
            $table->dropIndex('deliverables_competence_status_idx');
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->dropIndex('evaluation_attempts_eval_submitted_idx');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_estado_updated_idx');
            $table->dropIndex('evaluations_room_order_status_idx');
        });
    }
};
