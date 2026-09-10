<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documento_versiones')) {
            return;
        }

        Schema::table('documento_versiones', function (Blueprint $table): void {
            if (! Schema::hasColumn('documento_versiones', 'disco')) {
                $table->string('disco', 32)->nullable()->after('ruta_archivo');
            }
            if (! Schema::hasColumn('documento_versiones', 'checksum_sha256')) {
                $table->char('checksum_sha256', 64)->nullable()->after('tamano_bytes');
            }
            if (! Schema::hasColumn('documento_versiones', 'nombre_original')) {
                $table->string('nombre_original')->nullable()->after('nombre_archivo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documento_versiones')) {
            return;
        }

        $columns = collect(['disco', 'checksum_sha256', 'nombre_original'])
            ->filter(fn (string $column) => Schema::hasColumn('documento_versiones', $column))
            ->all();

        if ($columns) {
            Schema::table('documento_versiones', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
