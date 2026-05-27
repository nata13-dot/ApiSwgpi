<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_attempts', 'apto_titulacion')) {
                $column = $table->boolean('apto_titulacion')->nullable();
                if (Schema::hasColumn('evaluation_attempts', 'general_comment')) {
                    $column->after('general_comment');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_attempts', 'apto_titulacion')) {
                $table->dropColumn('apto_titulacion');
            }
        });
    }
};
