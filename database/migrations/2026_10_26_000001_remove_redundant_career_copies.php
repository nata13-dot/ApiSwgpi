<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita las copias de carrera_id/periodo_id que se deducen del padre obligatorio
 * (tabla → padre → carrera). Cada dato queda guardado en un solo lugar:
 *  - grupos_academicos, asignaturas, rubricas y operaciones_academicas son la raíz de su carrera;
 *  - el resto la obtiene por JOIN con su padre;
 *  - documentos y autorizaciones guardan carrera_id sólo cuando no tienen padre del cual deducirla.
 * Las reglas que antes dependían de la copia (unicidad y "misma carrera") pasan a triggers.
 * Los valores retirados se archivan en hist_copias_carrera. Cada paso es idempotente.
 */
return new class extends Migration
{
    // tabla => [columna_padre, tabla_padre, regla ON DELETE]
    private const DERIVED = [
        'proyectos' => ['grupo_id', 'grupos_academicos'],
        'cursos' => ['grupo_id', 'grupos_academicos'],
        'entregables' => ['curso_id', 'cursos'],
        'salas_evaluacion' => ['rubrica_id', 'rubricas'],
        'evaluaciones' => ['sala_id', 'salas_evaluacion'],
        'matriculas_academicas' => ['grupo_id', 'grupos_academicos'],
        'proyecto_periodos' => ['proyecto_id', 'proyectos'],
        'residencias' => ['proyecto_id', 'proyectos'],
        'movimientos_academicos' => ['operacion_id', 'operaciones_academicas'],
    ];

    // Expresión que obtiene la carrera de una fila a partir de su padre (para vistas y triggers).
    private const CAREER_OF = [
        'proyectos' => '(SELECT g.carrera_id FROM grupos_academicos g WHERE g.id = %s.grupo_id)',
        'cursos' => '(SELECT g.carrera_id FROM grupos_academicos g WHERE g.id = %s.grupo_id)',
        'entregables' => '(SELECT g.carrera_id FROM cursos c JOIN grupos_academicos g ON g.id = c.grupo_id WHERE c.id = %s.curso_id)',
        'salas_evaluacion' => '(SELECT r.carrera_id FROM rubricas r WHERE r.id = %s.rubrica_id)',
        'evaluaciones' => '(SELECT r.carrera_id FROM salas_evaluacion s JOIN rubricas r ON r.id = s.rubrica_id WHERE s.id = %s.sala_id)',
        'documentos' => 'COALESCE(%1$s.carrera_id, (SELECT g.carrera_id FROM proyectos p JOIN grupos_academicos g ON g.id = p.grupo_id WHERE p.id = %1$s.proyecto_id))',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->archive();
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_validar_integrante');
        foreach (['trg_proyecto_integrantes_bi', 'trg_proyecto_integrantes_bu', 'trg_documentos_carrera_bi', 'trg_documentos_carrera_bu'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
        $views = $this->captureViews();

        foreach (self::DERIVED as $table => [$column, $parent]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $this->replaceCompositeKeys($table);
            if (Schema::hasColumn($table, 'carrera_id')) {
                $this->dropColumn($table, 'carrera_id');
            }
        }
        // El periodo de una matrícula o del grupo de un proyecto es el periodo de su grupo.
        foreach (['matriculas_academicas', 'proyecto_periodos'] as $table) {
            if (Schema::hasColumn($table, 'periodo_id')) {
                $this->dropColumn($table, 'periodo_id');
            }
        }

        $this->optionalParentCareer();
        foreach (['grupos_academicos', 'asignaturas', 'rubricas', 'proyectos', 'cursos', 'salas_evaluacion', 'evaluaciones', 'operaciones_academicas'] as $parent) {
            if (Schema::hasTable($parent) && $this->hasIndex($parent, "uq_{$parent}_id_carrera")) {
                $this->replaceCompositeKeys($parent);
                DB::statement("ALTER TABLE {$parent} DROP INDEX uq_{$parent}_id_carrera");
            }
        }

        $this->triggers();
        $this->recreateViews($views);
    }

    public function down(): void
    {
        throw new RuntimeException('Restaura el respaldo previo; las copias retiradas están en hist_copias_carrera.');
    }

    private function archive(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS hist_copias_carrera (
            tabla VARCHAR(64) NOT NULL,
            fila_id VARCHAR(40) NOT NULL,
            carrera_id SMALLINT UNSIGNED NULL,
            periodo_id BIGINT UNSIGNED NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tabla, fila_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        foreach ([...array_keys(self::DERIVED), 'documentos', 'autorizaciones_academicas'] as $table) {
            if (!Schema::hasColumn($table, 'carrera_id')) {
                continue;
            }
            $period = in_array($table, ['matriculas_academicas', 'proyecto_periodos'], true) && Schema::hasColumn($table, 'periodo_id') ? 'periodo_id' : 'NULL';
            DB::statement("INSERT IGNORE INTO hist_copias_carrera (tabla, fila_id, carrera_id, periodo_id) SELECT ?, id, carrera_id, {$period} FROM {$table}", [$table]);
        }
    }

    // Una FK compuesta (padre_id, carrera_id) vuelve a ser la FK simple padre_id → padre.id.
    private function replaceCompositeKeys(string $table): void
    {
        $composites = DB::select("SELECT k.table_name AS child, k.constraint_name AS name, r.delete_rule AS rule, r.referenced_table_name AS parent,
                MAX(CASE WHEN k.column_name <> 'carrera_id' THEN k.column_name END) AS col
            FROM information_schema.key_column_usage k
            JOIN information_schema.referential_constraints r ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name
            WHERE k.table_schema = DATABASE() AND (k.table_name = ? OR k.referenced_table_name = ?) AND k.referenced_table_name IS NOT NULL
            GROUP BY k.table_name, k.constraint_name, r.delete_rule, r.referenced_table_name
            HAVING SUM(k.column_name = 'carrera_id') = 1 AND COUNT(*) = 2", [$table, $table]);

        foreach ($composites as $fk) {
            $rule = in_array($fk->rule, ['CASCADE', 'SET NULL'], true) ? $fk->rule : 'RESTRICT';
            $simple = $this->constraintName('fk', $fk->child, $fk->col);
            DB::statement("ALTER TABLE {$fk->child} DROP FOREIGN KEY `{$fk->name}`");
            if (!$this->hasConstraint($fk->child, $simple)) {
                DB::statement("ALTER TABLE {$fk->child} ADD CONSTRAINT {$simple} FOREIGN KEY ({$fk->col}) REFERENCES {$fk->parent} (id) ON DELETE {$rule}");
            }
        }
        // Las FK simples carrera_id → carreras de las tablas derivadas también sobran.
        if (array_key_exists($table, self::DERIVED) && ($fk = $this->foreignKeyOn($table, 'carrera_id'))) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY `{$fk}`");
        }
    }

    private function dropColumn(string $table, string $column): void
    {
        if ($column === 'periodo_id' && ($fk = $this->foreignKeyOn($table, 'periodo_id'))) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY `{$fk}`");
        }
        $indexes = $this->indexesWithColumn($table, $column);
        // Si el índice que se va sostenía otra FK por su primera columna, esa FK conserva un índice propio.
        foreach ($indexes as $index) {
            $first = DB::selectOne('SELECT column_name AS name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? AND seq_in_index = 1', [$table, $index])->name;
            $covered = DB::selectOne('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND seq_in_index = 1 AND column_name = ? AND index_name NOT IN (' . implode(',', array_fill(0, count($indexes), '?')) . ')', [$table, $first, ...$indexes]);
            if ($first !== $column && !$covered) {
                DB::statement("ALTER TABLE {$table} ADD INDEX " . $this->constraintName('idx', $table, $first) . " ({$first})");
            }
        }
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE {$table} DROP INDEX `{$index}`");
        }
        DB::statement("ALTER TABLE {$table} DROP COLUMN {$column}");
    }

    // Documentos y autorizaciones: carrera_id sólo cuando no hay padre del cual deducirla.
    private function optionalParentCareer(): void
    {
        DB::statement('ALTER TABLE documentos MODIFY carrera_id SMALLINT UNSIGNED NULL');
        DB::statement('UPDATE documentos SET carrera_id = NULL WHERE proyecto_id IS NOT NULL');

        if (Schema::hasTable('autorizaciones_academicas')) {
            $this->replaceCompositeKeys('autorizaciones_academicas');
            DB::statement('ALTER TABLE autorizaciones_academicas MODIFY carrera_id SMALLINT UNSIGNED NULL');
            DB::statement('UPDATE autorizaciones_academicas SET carrera_id = NULL WHERE proyecto_id IS NOT NULL OR curso_id IS NOT NULL');
        }
    }

    private function triggers(): void
    {
        $career = self::CAREER_OF;
        $projectCareer = sprintf($career['proyectos'], 'p');
        $statements = [
            // Un curso une un grupo y una asignatura de la misma carrera.
            'trg_cursos_carrera_bi' => ['BEFORE INSERT ON cursos', $this->sameCareerCheck('cursos')],
            'trg_cursos_carrera_bu' => ['BEFORE UPDATE ON cursos', $this->sameCareerCheck('cursos')],
            // Una evaluación une una sala y un proyecto de la misma carrera.
            'trg_evaluaciones_carrera_bi' => ['BEFORE INSERT ON evaluaciones', $this->sameCareerCheck('evaluaciones')],
            'trg_evaluaciones_carrera_bu' => ['BEFORE UPDATE ON evaluaciones', $this->sameCareerCheck('evaluaciones')],
            'trg_criterios_carrera_bi' => ['BEFORE INSERT ON criterios_rubrica', $this->sameCareerCheck('criterios_rubrica')],
            'trg_criterios_carrera_bu' => ['BEFORE UPDATE ON criterios_rubrica', $this->sameCareerCheck('criterios_rubrica')],
            'trg_documentos_carrera_bi' => ['BEFORE INSERT ON documentos', $this->optionalCareerCheck('documentos', 'NEW.proyecto_id IS NOT NULL')],
            'trg_documentos_carrera_bu' => ['BEFORE UPDATE ON documentos', $this->optionalCareerCheck('documentos', 'NEW.proyecto_id IS NOT NULL')],
            // Si se borra un proyecto, sus documentos conservan la carrera.
            'trg_proyectos_documentos_bd' => ['BEFORE DELETE ON proyectos', "BEGIN
                UPDATE documentos SET carrera_id = (SELECT carrera_id FROM grupos_academicos WHERE id = OLD.grupo_id) WHERE proyecto_id = OLD.id;
            END"],
            'trg_proyecto_integrantes_bi' => ['BEFORE INSERT ON proyecto_integrantes', 'BEGIN CALL sp_validar_integrante(NEW.proyecto_id, NEW.usuario_id, NEW.rol); END'],
            'trg_proyecto_integrantes_bu' => ['BEFORE UPDATE ON proyecto_integrantes', 'BEGIN
                IF NEW.rol <> OLD.rol OR NEW.usuario_id <> OLD.usuario_id OR NEW.proyecto_id <> OLD.proyecto_id THEN
                  CALL sp_validar_integrante(NEW.proyecto_id, NEW.usuario_id, NEW.rol);
                END IF;
            END'],
        ];

        if (Schema::hasTable('matriculas_academicas')) {
            $enrollment = "BEGIN
                IF EXISTS (SELECT 1 FROM matriculas_academicas m
                           JOIN grupos_academicos g ON g.id = m.grupo_id
                           JOIN grupos_academicos n ON n.id = NEW.grupo_id
                           WHERE m.estudiante_id = NEW.estudiante_id AND m.id <> COALESCE(NEW.id, 0)
                             AND g.carrera_id = n.carrera_id AND g.periodo_id = n.periodo_id) THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El alumno ya tiene matrícula en ese periodo de la carrera';
                END IF;
            END";
            $statements['trg_matriculas_unica_bi'] = ['BEFORE INSERT ON matriculas_academicas', $enrollment];
            $statements['trg_matriculas_unica_bu'] = ['BEFORE UPDATE ON matriculas_academicas', $enrollment];
            $projectGroup = "BEGIN
                IF (SELECT carrera_id FROM grupos_academicos WHERE id = NEW.grupo_id) <> (SELECT {$projectCareer} FROM proyectos p WHERE p.id = NEW.proyecto_id) THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El grupo debe pertenecer a la carrera del proyecto';
                END IF;
                IF EXISTS (SELECT 1 FROM proyecto_periodos pp
                           JOIN grupos_academicos g ON g.id = pp.grupo_id
                           WHERE pp.proyecto_id = NEW.proyecto_id AND pp.id <> COALESCE(NEW.id, 0)
                             AND g.periodo_id = (SELECT periodo_id FROM grupos_academicos WHERE id = NEW.grupo_id)) THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El proyecto ya tiene grupo en ese periodo';
                END IF;
            END";
            $statements['trg_proyecto_periodos_bi'] = ['BEFORE INSERT ON proyecto_periodos', $projectGroup];
            $statements['trg_proyecto_periodos_bu'] = ['BEFORE UPDATE ON proyecto_periodos', $projectGroup];
            $movement = "BEGIN
                IF EXISTS (SELECT 1 FROM movimientos_academicos m
                           JOIN operaciones_academicas o ON o.id = m.operacion_id
                           WHERE m.estudiante_id = NEW.estudiante_id AND m.origen_id = NEW.origen_id AND m.destino_id = NEW.destino_id
                             AND o.carrera_id = (SELECT carrera_id FROM operaciones_academicas WHERE id = NEW.operacion_id)) THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El movimiento de este alumno ya fue confirmado';
                END IF;
            END";
            $statements['trg_movimientos_unico_bi'] = ['BEFORE INSERT ON movimientos_academicos', $movement];
            $statements['trg_autorizaciones_carrera_bi'] = ['BEFORE INSERT ON autorizaciones_academicas', $this->optionalCareerCheck('autorizaciones_academicas', 'NEW.proyecto_id IS NOT NULL OR NEW.curso_id IS NOT NULL')];
            $statements['trg_autorizaciones_carrera_bu'] = ['BEFORE UPDATE ON autorizaciones_academicas', $this->optionalCareerCheck('autorizaciones_academicas', 'NEW.proyecto_id IS NOT NULL OR NEW.curso_id IS NOT NULL')];
        }

        DB::unprepared("CREATE PROCEDURE sp_validar_integrante(IN p_proyecto BIGINT UNSIGNED, IN p_usuario VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_rol VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
            BEGIN
              DECLARE v_carrera SMALLINT UNSIGNED;
              DECLARE v_tipo VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
              SELECT g.carrera_id, p.tipo INTO v_carrera, v_tipo FROM proyectos p JOIN grupos_academicos g ON g.id = p.grupo_id WHERE p.id = p_proyecto;
              IF p_rol IN ('lider', 'integrante') THEN
                IF NOT EXISTS (SELECT 1 FROM usuario_carrera WHERE usuario_id = p_usuario AND carrera_id = v_carrera AND perfil_id = 3 AND activo = 1) THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo estudiantes activos de la carrera pueden ser integrantes';
                END IF;
              ELSE
                IF NOT EXISTS (SELECT 1 FROM usuario_carrera WHERE usuario_id = p_usuario AND carrera_id = v_carrera AND perfil_id <> 3 AND activo = 1) THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El asesor debe ser personal activo de la carrera';
                END IF;
                IF (p_rol IN ('asesor', 'revisor_1', 'revisor_2')) <> (v_tipo = 'tesis') THEN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El rol del asesor no corresponde al tipo de proyecto';
                END IF;
              END IF;
            END");

        foreach ($statements as $name => [$event, $body]) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared("CREATE TRIGGER {$name} {$event} FOR EACH ROW {$body}");
        }
    }

    // Las dos rutas hacia la carrera (el "rombo") deben llegar a la misma.
    private function sameCareerCheck(string $table): string
    {
        [$left, $right, $message] = match ($table) {
            'cursos' => ['(SELECT carrera_id FROM grupos_academicos WHERE id = NEW.grupo_id)',
                '(SELECT carrera_id FROM asignaturas WHERE id = NEW.asignatura_id)', 'La asignatura debe pertenecer a la carrera del grupo'],
            'evaluaciones' => ['(SELECT r.carrera_id FROM salas_evaluacion s JOIN rubricas r ON r.id = s.rubrica_id WHERE s.id = NEW.sala_id)',
                '(SELECT g.carrera_id FROM proyectos p JOIN grupos_academicos g ON g.id = p.grupo_id WHERE p.id = NEW.proyecto_id)', 'La sala y el proyecto deben ser de la misma carrera'],
            'criterios_rubrica' => ['(SELECT carrera_id FROM rubricas WHERE id = NEW.rubrica_id)',
                '(SELECT g.carrera_id FROM proyectos p JOIN grupos_academicos g ON g.id = p.grupo_id WHERE p.id = NEW.proyecto_id)', 'El criterio personalizado debe ser de la carrera de su rúbrica'],
        };
        $guard = $table === 'criterios_rubrica' ? 'NEW.proyecto_id IS NOT NULL AND ' : '';

        return "BEGIN
            IF {$guard}{$left} <> {$right} THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
            END IF;
        END";
    }

    // Con padre, la carrera se deduce de él y no se guarda; sin padre, es obligatoria.
    private function optionalCareerCheck(string $table, string $hasParent): string
    {
        return "BEGIN
            IF ({$hasParent}) AND NEW.carrera_id IS NOT NULL THEN
              SET NEW.carrera_id = NULL;
            END IF;
            IF NOT ({$hasParent}) AND NEW.carrera_id IS NULL THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sin proyecto ni curso, el registro debe indicar su carrera';
            END IF;
        END";
    }

    private function captureViews(): array
    {
        return array_map(fn ($row) => [$row->name, $row->definition], DB::select("SELECT table_name AS name, view_definition AS definition
            FROM information_schema.views WHERE table_schema = DATABASE() AND view_definition REGEXP 'carrera_id|periodo_id'"));
    }

    // Donde una vista leía la copia, ahora lee la carrera del padre.
    private function recreateViews(array $views): void
    {
        foreach ($views as [$name, $definition]) {
            $aliases = [];
            preg_match_all('/`(\w+)`(?:\s+(?:AS\s+)?`(\w+)`)?/i', $definition, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (isset(self::CAREER_OF[$match[1]]) && isset($match[2]) && $match[2] !== '') {
                    $aliases[$match[2]] = $match[1];
                }
                if (isset(self::CAREER_OF[$match[1]])) {
                    $aliases[$match[1]] = $match[1];
                }
            }
            foreach ($aliases as $alias => $table) {
                $definition = str_replace("`{$alias}`.`carrera_id`", sprintf(self::CAREER_OF[$table], "`{$alias}`"), $definition);
            }
            DB::statement("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `{$name}` AS {$definition}");
        }
    }

    private function foreignKeyOn(string $table, string $column): ?string
    {
        return DB::selectOne("SELECT k.constraint_name AS name FROM information_schema.key_column_usage k
            WHERE k.table_schema = DATABASE() AND k.table_name = ? AND k.column_name = ? AND k.referenced_table_name IS NOT NULL
              AND (SELECT COUNT(*) FROM information_schema.key_column_usage x WHERE x.constraint_schema = k.constraint_schema
                   AND x.table_name = k.table_name AND x.constraint_name = k.constraint_name) = 1", [$table, $column])?->name;
    }

    private function hasConstraint(string $table, string $name): bool
    {
        return (bool) DB::selectOne('SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?', [$table, $name]);
    }

    private function hasIndex(string $table, string $name): bool
    {
        return (bool) DB::selectOne('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$table, $name]);
    }

    private function indexesWithColumn(string $table, string $column): array
    {
        return array_map(fn ($row) => $row->name, DB::select("SELECT DISTINCT index_name AS name FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND index_name <> 'PRIMARY'", [$table, $column]));
    }

    private function constraintName(string ...$parts): string
    {
        $name = implode('_', $parts);

        return strlen($name) <= 64 ? $name : substr($name, 0, 55) . '_' . substr(md5($name), 0, 8);
    }
};
