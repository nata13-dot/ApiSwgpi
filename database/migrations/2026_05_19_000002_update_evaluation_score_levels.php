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

        DB::table('evaluation_scores')->where('nivel', 'mucho')->update(['nivel' => 'totalmente_de_acuerdo', 'puntaje' => 4]);
        DB::table('evaluation_scores')->where('nivel', 'bastante')->update(['nivel' => 'de_acuerdo', 'puntaje' => 3]);
        DB::table('evaluation_scores')->where('nivel', 'poco')->update(['nivel' => 'en_desacuerdo', 'puntaje' => 1]);
        DB::table('evaluation_scores')->where('nivel', 'nada')->update(['nivel' => 'totalmente_en_desacuerdo', 'puntaje' => 0]);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }
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

        DB::table('evaluation_scores')->where('nivel', 'totalmente_de_acuerdo')->update(['nivel' => 'mucho', 'puntaje' => 3]);
        DB::table('evaluation_scores')->where('nivel', 'de_acuerdo')->update(['nivel' => 'bastante', 'puntaje' => 2]);
        DB::table('evaluation_scores')->where('nivel', 'neutral')->update(['nivel' => 'poco', 'puntaje' => 1]);
        DB::table('evaluation_scores')->where('nivel', 'en_desacuerdo')->update(['nivel' => 'poco', 'puntaje' => 1]);
        DB::table('evaluation_scores')->where('nivel', 'totalmente_en_desacuerdo')->update(['nivel' => 'nada', 'puntaje' => 0]);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho') NOT NULL");
        }
    }
};
