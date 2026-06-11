<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE proyectos p
            INNER JOIN usuarios u ON u.id = p.creado_por
            SET p.es_propuesta = 1
            WHERE u.perfil_id = 3
        ");

        DB::statement("
            UPDATE proyectos p
            LEFT JOIN usuarios u ON u.id = p.creado_por
            SET p.es_propuesta = 0,
                p.estado_propuesta = 'aprobado',
                p.revisado_por = NULL,
                p.comentario_revision = NULL,
                p.revisado_en = NULL,
                p.revision_permitida_hasta = NULL
            WHERE u.id IS NULL OR u.perfil_id <> 3
        ");
    }

    public function down(): void
    {
        // La clasificacion anterior no puede reconstruirse de forma confiable.
    }
};
