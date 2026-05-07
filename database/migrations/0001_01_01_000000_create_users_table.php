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
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // Matrícula/Nómina
            $table->string('password', 255);
            $table->string('nombres', 200);
            $table->string('apa', 100)->nullable(); // Apellido paterno
            $table->string('ama', 100)->nullable(); // Apellido materno
            $table->text('direccion')->nullable();
            $table->string('telefonos', 200)->nullable();
            $table->string('curp', 20)->nullable()->unique();
            $table->string('email', 255)->nullable()->unique();
            $table->tinyInteger('perfil_id')->default(3); // 1=Admin, 2=Teacher, 3=Student
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->index('perfil_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
