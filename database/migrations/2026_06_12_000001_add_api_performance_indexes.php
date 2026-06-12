<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex(
            'excepciones_presentacion_semestre',
            ['periodo_id', 'activo', 'proyecto_id'],
            'idx_excepcion_periodo_proyecto'
        );
        $this->addIndex(
            'excepciones_presentacion_semestre',
            ['periodo_id', 'activo', 'usuario_id', 'actualizado_en'],
            'idx_excepcion_periodo_usuario'
        );
        $this->addIndex(
            'proyectos_integrantes',
            ['usuario_id', 'rol', 'proyecto_id'],
            'idx_integrante_usuario_rol_proyecto'
        );
        $this->addIndex(
            'proyectos',
            ['activo', 'titulo'],
            'idx_proyectos_activo_titulo'
        );
    }

    public function down(): void
    {
        $this->dropIndex('excepciones_presentacion_semestre', 'idx_excepcion_periodo_proyecto');
        $this->dropIndex('excepciones_presentacion_semestre', 'idx_excepcion_periodo_usuario');
        $this->dropIndex('proyectos_integrantes', 'idx_integrante_usuario_rol_proyecto');
        $this->dropIndex('proyectos', 'idx_proyectos_activo_titulo');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if (!Schema::hasTable($table) || !$this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index) => ($index['name'] ?? null) === $name
        );
    }
};
