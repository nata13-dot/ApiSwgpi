<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_documents', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->text('autores')->nullable();
            $table->string('archivo_path');
            $table->string('archivo_tipo', 20);
            $table->string('uploaded_by', 10)->nullable();
            $table->timestamps();
            $table->boolean('activo')->default(true);

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['activo', 'created_at']);
        });

        Schema::create('repository_document_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repository_document_id');
            $table->unsignedBigInteger('document_tag_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('repository_document_id')->references('id')->on('repository_documents')->onDelete('cascade');
            $table->foreign('document_tag_id')->references('id')->on('document_tags')->onDelete('cascade');
            $table->unique(['repository_document_id', 'document_tag_id'], 'repo_document_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_document_tag');
        Schema::dropIfExists('repository_documents');
    }
};
