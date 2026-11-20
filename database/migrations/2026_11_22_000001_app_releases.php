<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones publicadas de la app Android (App\Http\Controllers\API\AppReleaseController). La app consulta la
 * más reciente, descarga el APK, comprueba su SHA-256 y abre el instalador de Android.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_versiones')) return;
        Schema::create('app_versiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_code')->unique();
            $table->string('version_name', 40);
            $table->text('notas')->nullable();
            $table->boolean('obligatoria')->default(false);
            $table->boolean('activa')->default(true);
            $table->string('ruta', 255);
            $table->unsignedBigInteger('tamano_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('descargas')->default(0);
            $table->string('publicada_por', 20)->nullable();
            $table->dateTime('publicada_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versiones');
    }
};
