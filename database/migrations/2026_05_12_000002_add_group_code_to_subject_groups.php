<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('subject_groups', 'grupo')) {
                $table->string('grupo', 20)->nullable()->after('semestre');
                $table->index(['semestre', 'grupo', 'activo']);
            }
        });

        DB::table('subject_groups')
            ->whereNull('grupo')
            ->orderBy('id')
            ->get()
            ->each(function ($group) {
                preg_match('/\b([A-Z])\b/i', (string) $group->nombre, $matches);
                DB::table('subject_groups')
                    ->where('id', $group->id)
                    ->update(['grupo' => strtoupper($matches[1] ?? 'A')]);
            });
    }

    public function down(): void
    {
        Schema::table('subject_groups', function (Blueprint $table) {
            if (Schema::hasColumn('subject_groups', 'grupo')) {
                $table->dropIndex(['semestre', 'grupo', 'activo']);
                $table->dropColumn('grupo');
            }
        });
    }
};
