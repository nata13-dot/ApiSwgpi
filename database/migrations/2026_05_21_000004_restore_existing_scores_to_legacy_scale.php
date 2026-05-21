<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }

        $legacyCutoff = now();

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'totalmente_de_acuerdo')
            ->where('puntaje', 4)
            ->update(['nivel' => 'mucho', 'puntaje' => 3]);

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'de_acuerdo')
            ->where('puntaje', 3)
            ->update(['nivel' => 'bastante', 'puntaje' => 2]);

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'en_desacuerdo')
            ->where('puntaje', 1)
            ->update(['nivel' => 'poco']);

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'totalmente_en_desacuerdo')
            ->where('puntaje', 0)
            ->update(['nivel' => 'nada']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }
    }
};
