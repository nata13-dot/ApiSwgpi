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
        Schema::create('deliverable_document_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deliverable_id');
            $table->unsignedBigInteger('document_tag_id');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('deliverable_id')->references('id')->on('deliverables')->onDelete('cascade');
            $table->foreign('document_tag_id')->references('id')->on('document_tags')->onDelete('cascade');
            $table->unique(['deliverable_id', 'document_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverable_document_tag');
    }
};
