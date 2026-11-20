<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Llaves de publicación automática de la app (php artisan sgpi:release-token). Sólo sirven para
 * POST /api/app/publish; se guarda la huella, nunca la llave.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_llaves_publicacion')) return;
        Schema::create('app_llaves_publicacion', function (Blueprint $table) {
            $table->id();
            $table->char('huella', 64)->unique();
            $table->string('nombre', 80);
            $table->dateTime('creada_en')->useCurrent();
            $table->dateTime('ultimo_uso')->nullable();
            $table->dateTime('revocada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_llaves_publicacion');
    }
};
