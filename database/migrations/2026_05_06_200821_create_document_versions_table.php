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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deliverable_id');
            $table->integer('version_number');
            $table->text('descripcion')->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('uploaded_by', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('deliverable_id')->references('id')->on('deliverables')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
