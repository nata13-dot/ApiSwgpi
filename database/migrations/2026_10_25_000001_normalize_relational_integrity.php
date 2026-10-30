<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza el esquema sin perder información:
 *  - carrera_id repetido en cadena queda amarrado al del padre con FKs compuestas;
 *  - se agregan las FKs que faltaban y se rompe el ciclo evaluaciones ⇄ autorizaciones;
 *  - se eliminan columnas y configuraciones que duplicaban otro dato;
 *  - las reglas de unicidad se declaran con índices y las reglas entre tablas con triggers.
 * Todo valor retirado se copia antes a una tabla hist_*. Cada paso es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->careerConsistentForeignKeys();
        $this->missingForeignKeys();
        $this->breakAuthorizationCycle();
        $this->generalAdministratorFlag();
        $this->derivedEvaluationColumns();
        $this->tableBackedSettings();
        $this->legacyAdvisorRoles();
        $this->duplicateMigratedProjects();
        $this->declarativeUniqueness();
        $this->crossTableTriggers();
        $this->views();
    }

    public function down(): void
    {
        throw new RuntimeException('La normalización conserva lo retirado en las tablas hist_*; restaura el respaldo previo en lugar de revertir.');
    }

    // carrera_id se conserva para filtrar sin JOIN, pero ya no puede diferir del de su padre.
    private function careerConsistentForeignKeys(): void
    {
        foreach (['grupos_academicos', 'asignaturas', 'rubricas', 'proyectos', 'cursos', 'salas_evaluacion', 'evaluaciones', 'operaciones_academicas'] as $parent) {
            if (!$this->hasIndex($parent, "uq_{$parent}_id_carrera")) {
                DB::statement("ALTER TABLE {$parent} ADD UNIQUE KEY uq_{$parent}_id_carrera (id, carrera_id)");
            }
        }

        foreach ([
            ['proyectos', 'grupo_id', 'grupos_academicos'],
            ['cursos', 'grupo_id', 'grupos_academicos'],
            ['cursos', 'asignatura_id', 'asignaturas'],
            ['entregables', 'curso_id', 'cursos'],
            ['salas_evaluacion', 'rubrica_id', 'rubricas'],
            ['evaluaciones', 'sala_id', 'salas_evaluacion'],
            ['evaluaciones', 'proyecto_id', 'proyectos'],
            ['matriculas_academicas', 'grupo_id', 'grupos_academicos'],
            ['proyecto_periodos', 'proyecto_id', 'proyectos'],
            ['proyecto_periodos', 'grupo_id', 'grupos_academicos'],
            ['residencias', 'proyecto_id', 'proyectos'],
            ['autorizaciones_academicas', 'proyecto_id', 'proyectos'],
            ['autorizaciones_academicas', 'curso_id', 'cursos'],
            ['autorizaciones_academicas', 'evaluacion_id', 'evaluaciones'],
            ['movimientos_academicos', 'operacion_id', 'operaciones_academicas'],
        ] as [$child, $column, $parent]) {
            $this->compositeCareerForeignKey($child, $column, $parent);
        }
    }

    private function compositeCareerForeignKey(string $child, string $column, string $parent, ?string $rule = null): void
    {
        $name = $this->constraintName('fk', $child, $column, 'carrera');
        if (!Schema::hasTable($child) || $this->hasConstraint($child, $name)) {
            return;
        }

        $mismatches = DB::table("{$child} as c")->join("{$parent} as p", 'p.id', '=', "c.{$column}")
            ->whereColumn('c.carrera_id', '<>', 'p.carrera_id')->count();
        if ($mismatches) {
            throw new RuntimeException("{$child}.{$column}: {$mismatches} filas con carrera distinta a {$parent}; corrígelas antes de migrar.");
        }

        $old = $this->foreignKeyOn($child, $column);
        $rule ??= $old ? $this->normalizedRule($old->rule) : 'RESTRICT';
        $drop = $old ? "DROP FOREIGN KEY `{$old->name}`, " : '';
        DB::statement("ALTER TABLE {$child} {$drop}ADD CONSTRAINT {$name} FOREIGN KEY ({$column}, carrera_id) REFERENCES {$parent} (id, carrera_id) ON DELETE {$rule}");
        $this->dropSingleColumnIndexes($child, $column);
    }

    private function missingForeignKeys(): void
    {
        foreach ([
            ['autorizaciones_academicas', 'solicitada_por', 'usuarios'],
            ['autorizaciones_academicas', 'resuelta_por', 'usuarios'],
            ['bitacora_academica', 'actor_id', 'usuarios'],
            ['bitacora_academica', 'carrera_id', 'carreras'],
            ['cursos', 'clave_actualizada_por', 'usuarios'],
            ['empresas', 'solicitada_por', 'usuarios'],
            ['empresas', 'validada_por', 'usuarios'],
            ['liberaciones_documentos_evaluacion', 'alumno_id', 'usuarios'],
            ['liberaciones_documentos_evaluacion', 'revisado_por', 'usuarios'],
            ['operaciones_academicas', 'actor_id', 'usuarios'],
            ['operaciones_academicas', 'carrera_id', 'carreras'],
            ['operaciones_academicas', 'periodo_id', 'periodos_academicos'],
            ['residencia_versiones', 'revisada_por', 'usuarios'],
        ] as [$table, $column, $parent]) {
            if (!Schema::hasColumn($table, $column) || $this->foreignKeyOn($table, $column)) {
                continue;
            }

            $nullable = DB::selectOne('SELECT is_nullable AS nullable FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $column])->nullable === 'YES';
            $orphans = DB::table("{$table} as c")->leftJoin("{$parent} as p", 'p.id', '=', "c.{$column}")
                ->whereNotNull("c.{$column}")->whereNull('p.id')->count();
            if ($orphans && $nullable) {
                DB::table($table)->whereNotNull($column)->whereNotIn($column, DB::table($parent)->select('id'))->update([$column => null]);
            } elseif ($orphans) {
                throw new RuntimeException("{$table}.{$column}: {$orphans} referencias a {$parent} inexistentes.");
            }

            $name = $this->constraintName('fk', $table, $column);
            $rule = $nullable ? 'SET NULL' : 'RESTRICT';
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$parent} (id) ON DELETE {$rule}");
        }
    }

    // La autorización registra qué evaluación la consumió; así ambas FKs apuntan en la misma dirección.
    private function breakAuthorizationCycle(): void
    {
        if (!Schema::hasTable('autorizaciones_academicas')) {
            return;
        }

        if (!Schema::hasColumn('autorizaciones_academicas', 'consumida_por_evaluacion_id')) {
            DB::statement('ALTER TABLE autorizaciones_academicas ADD COLUMN consumida_por_evaluacion_id BIGINT UNSIGNED NULL AFTER consumida_en, ADD UNIQUE KEY uq_autorizacion_consumida_por (consumida_por_evaluacion_id)');
        }

        foreach (['autorizacion_id', 'presentacion_autorizacion_id'] as $column) {
            if (!Schema::hasColumn('evaluaciones', $column)) {
                continue;
            }
            DB::statement("UPDATE autorizaciones_academicas a JOIN evaluaciones e ON e.{$column} = a.id SET a.consumida_por_evaluacion_id = e.id, a.consumida_en = COALESCE(a.consumida_en, e.creada_en)");
            if ($old = $this->foreignKeyOn('evaluaciones', $column)) {
                DB::statement("ALTER TABLE evaluaciones DROP FOREIGN KEY `{$old->name}`");
            }
            $this->dropSingleColumnIndexes('evaluaciones', $column);
            DB::statement("ALTER TABLE evaluaciones DROP COLUMN {$column}");
        }

        $name = $this->constraintName('fk', 'autorizaciones_academicas', 'consumida_por_evaluacion_id', 'carrera');
        if (!$this->hasConstraint('autorizaciones_academicas', $name)) {
            DB::statement("ALTER TABLE autorizaciones_academicas ADD CONSTRAINT {$name} FOREIGN KEY (consumida_por_evaluacion_id, carrera_id) REFERENCES evaluaciones (id, carrera_id) ON DELETE RESTRICT");
        }
    }

    // usuario_carrera define el perfil por carrera; del perfil global sólo queda "administrador general".
    private function generalAdministratorFlag(): void
    {
        if (!Schema::hasColumn('usuarios', 'es_administrador_general')) {
            DB::statement('ALTER TABLE usuarios ADD COLUMN es_administrador_general TINYINT(1) NOT NULL DEFAULT 0 AFTER id');
        }
        if (!Schema::hasColumn('usuarios', 'perfil_id')) {
            return;
        }

        DB::statement('CREATE TABLE IF NOT EXISTS hist_usuarios_perfil (
            usuario_id VARCHAR(20) NOT NULL PRIMARY KEY,
            perfil_id TINYINT UNSIGNED NOT NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::statement('INSERT IGNORE INTO hist_usuarios_perfil (usuario_id, perfil_id) SELECT id, perfil_id FROM usuarios');
        DB::table('usuarios')->where('perfil_id', 4)->update(['es_administrador_general' => 1]);

        if ($old = $this->foreignKeyOn('usuarios', 'perfil_id')) {
            DB::statement("ALTER TABLE usuarios DROP FOREIGN KEY `{$old->name}`");
        }
        foreach ($this->indexesWithColumn('usuarios', 'perfil_id') as $index) {
            DB::statement("ALTER TABLE usuarios DROP INDEX `{$index}`");
        }
        DB::statement('ALTER TABLE usuarios DROP COLUMN perfil_id');
    }

    // resultado repetía apto_titulacion y fecha_exposicion repetía salas_evaluacion.inicia_en.
    private function derivedEvaluationColumns(): void
    {
        if (!Schema::hasColumn('evaluaciones', 'resultado') && !Schema::hasColumn('evaluaciones', 'fecha_exposicion')) {
            return;
        }

        DB::statement('CREATE TABLE IF NOT EXISTS hist_evaluaciones_campos (
            evaluacion_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            resultado VARCHAR(20) NULL,
            fecha_exposicion DATETIME NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $resultado = Schema::hasColumn('evaluaciones', 'resultado') ? 'resultado' : 'NULL';
        $fecha = Schema::hasColumn('evaluaciones', 'fecha_exposicion') ? 'fecha_exposicion' : 'NULL';
        DB::statement("INSERT IGNORE INTO hist_evaluaciones_campos (evaluacion_id, resultado, fecha_exposicion) SELECT id, {$resultado}, {$fecha} FROM evaluaciones");

        foreach (['resultado', 'fecha_exposicion'] as $column) {
            if (Schema::hasColumn('evaluaciones', $column)) {
                foreach ($this->indexesWithColumn('evaluaciones', $column) as $index) {
                    DB::statement("ALTER TABLE evaluaciones DROP INDEX `{$index}`");
                }
                DB::statement("ALTER TABLE evaluaciones DROP COLUMN {$column}");
            }
        }
    }

    // Configuración guardada como JSON que en realidad era una columna o una relación.
    private function tableBackedSettings(): void
    {
        if (!Schema::hasColumn('rubricas', 'modo_puntaje')) {
            DB::statement("ALTER TABLE rubricas ADD COLUMN modo_puntaje ENUM('levels','numeric') NOT NULL DEFAULT 'levels' AFTER semestre");
        }
        DB::statement('CREATE TABLE IF NOT EXISTS carrera_responsables_evaluacion (
            carrera_id SMALLINT UNSIGNED NOT NULL,
            docente_id VARCHAR(20) NOT NULL,
            asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (carrera_id, docente_id),
            KEY idx_resp_eval_docente (docente_id),
            CONSTRAINT fk_resp_eval_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE,
            CONSTRAINT fk_resp_eval_docente FOREIGN KEY (docente_id) REFERENCES usuarios (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $careerKeys = ['rubric_score_modes', 'evaluation_manager_teacher_ids'];
        foreach (DB::table('configuraciones_carrera')->whereIn('clave', $careerKeys)->get() as $setting) {
            $value = json_decode((string) $setting->valor, true)['data'] ?? [];
            if ($setting->clave === 'rubric_score_modes') {
                foreach ((array) $value as $semester => $mode) {
                    if (in_array($mode, ['levels', 'numeric'], true)) {
                        DB::table('rubricas')->where('carrera_id', $setting->carrera_id)->where('semestre', (int) $semester)->update(['modo_puntaje' => $mode]);
                    }
                }
                continue;
            }
            $teachers = DB::table('usuarios')->whereIn('id', array_map('strval', (array) $value))->pluck('id');
            foreach ($teachers as $teacher) {
                DB::table('carrera_responsables_evaluacion')->insertOrIgnore(['carrera_id' => $setting->carrera_id, 'docente_id' => $teacher]);
            }
        }

        DB::statement("CREATE TABLE IF NOT EXISTS hist_configuraciones (
            origen ENUM('sistema','carrera') NOT NULL,
            carrera_id SMALLINT UNSIGNED NULL,
            clave VARCHAR(100) NOT NULL,
            valor JSON NULL,
            tipo VARCHAR(30) NOT NULL,
            descripcion TEXT NULL,
            creada_en DATETIME NULL,
            actualizada_en DATETIME NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // La carrera es dueña de estos ajustes y el periodo activo vive en carrera_periodos.
        $systemKeys = [...$careerKeys, 'active_academic_period', 'max_project_members', 'proposal_registration_enabled'];
        DB::statement("INSERT INTO hist_configuraciones (origen, carrera_id, clave, valor, tipo, descripcion, creada_en, actualizada_en)
            SELECT 'sistema', NULL, clave, valor, tipo, descripcion, creada_en, actualizada_en FROM configuraciones_sistema WHERE clave IN (" . $this->placeholders($systemKeys) . ")", $systemKeys);
        DB::statement("INSERT INTO hist_configuraciones (origen, carrera_id, clave, valor, tipo, descripcion, creada_en, actualizada_en)
            SELECT 'carrera', carrera_id, clave, valor, tipo, descripcion, creada_en, actualizada_en FROM configuraciones_carrera WHERE clave IN (" . $this->placeholders($careerKeys) . ")", $careerKeys);
        DB::table('configuraciones_sistema')->whereIn('clave', $systemKeys)->delete();
        DB::table('configuraciones_carrera')->whereIn('clave', $careerKeys)->delete();
    }

    // 'asesor' pertenece al comité de tesis; en proyectos que no son tesis equivalía a primario/secundario.
    private function legacyAdvisorRoles(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS hist_proyecto_integrantes_rol (
            proyecto_id BIGINT UNSIGNED NOT NULL,
            usuario_id VARCHAR(20) NOT NULL,
            rol_original VARCHAR(20) NOT NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (proyecto_id, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $legacy = DB::table('proyecto_integrantes as pi')->join('proyectos as p', 'p.id', '=', 'pi.proyecto_id')
            ->where('pi.rol', 'asesor')->where('p.tipo', '<>', 'tesis')
            ->orderBy('pi.proyecto_id')->orderBy('pi.agregado_en')->orderBy('pi.usuario_id')
            ->get(['pi.proyecto_id', 'pi.usuario_id'])->groupBy('proyecto_id');

        foreach ($legacy as $projectId => $advisors) {
            $taken = DB::table('proyecto_integrantes')->where('proyecto_id', $projectId)->whereIn('rol', ['primario', 'secundario'])->pluck('rol')->all();
            $free = array_values(array_diff(['primario', 'secundario'], $taken));
            foreach ($advisors as $index => $advisor) {
                if (!isset($free[$index])) {
                    throw new RuntimeException("El proyecto {$projectId} tiene más asesores de los que admite; revísalo antes de migrar.");
                }
                DB::table('hist_proyecto_integrantes_rol')->insertOrIgnore(['proyecto_id' => $projectId, 'usuario_id' => $advisor->usuario_id, 'rol_original' => 'asesor']);
                DB::table('proyecto_integrantes')->where('proyecto_id', $projectId)->where('usuario_id', $advisor->usuario_id)->update(['rol' => $free[$index]]);
            }
        }
    }

    // Proyectos que la migración de grupos duplicó vacíos; su copia con datos se conserva.
    private function duplicateMigratedProjects(): void
    {
        if (!Schema::hasTable('hist_proyectos_eliminados')) {
            DB::statement('CREATE TABLE hist_proyectos_eliminados LIKE proyectos');
            DB::statement('ALTER TABLE hist_proyectos_eliminados ADD COLUMN archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }

        $references = DB::select("SELECT table_name AS tabla, column_name AS columna FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE() AND referenced_table_name = 'proyectos' AND referenced_column_name = 'id'");
        $unused = fn (string $alias) => collect($references)
            ->map(fn ($ref) => "NOT EXISTS (SELECT 1 FROM `{$ref->tabla}` WHERE `{$ref->columna}` = {$alias}.id)")
            ->implode(' AND ');

        $empty = DB::select("SELECT p.id FROM proyectos p WHERE {$unused('p')} AND EXISTS (
            SELECT 1 FROM proyectos o WHERE o.carrera_id = p.carrera_id AND o.titulo = p.titulo AND o.id <> p.id
              AND EXISTS (SELECT 1 FROM proyecto_integrantes pi WHERE pi.proyecto_id = o.id))");
        $ids = array_map(fn ($row) => (int) $row->id, $empty);
        if (!$ids) {
            return;
        }

        $columns = collect(Schema::getColumnListing('proyectos'))->map(fn ($c) => "`{$c}`")->implode(', ');
        DB::statement("INSERT IGNORE INTO hist_proyectos_eliminados ({$columns}) SELECT {$columns} FROM proyectos WHERE id IN (" . $this->placeholders($ids) . ')', $ids);
        DB::table('proyectos')->whereIn('id', $ids)->delete();
    }

    // Unicidad declarada en el esquema: MySQL la garantiza sin depender del código.
    private function declarativeUniqueness(): void
    {
        $this->uniqueGeneratedColumn('proyecto_integrantes', 'cargo_unico', "VARCHAR(20) AS (IF(rol = 'integrante', NULL, rol)) STORED", 'uq_proyecto_cargo', 'proyecto_id, cargo_unico',
            'Un proyecto tiene un solo líder, asesor primario, secundario, asesor de tesis y cada revisor.');
        $this->uniqueGeneratedColumn('usuario_carrera', 'principal_unica', 'TINYINT AS (IF(es_principal = 1, 1, NULL)) STORED', 'uq_usuario_carrera_principal', 'usuario_id, principal_unica',
            'Cada persona tiene una sola carrera principal.');
        $this->uniqueGeneratedColumn('usuario_carrera', 'estudiante_activo_unico', 'TINYINT AS (IF(perfil_id = 3 AND activo = 1, 1, NULL)) STORED', 'uq_usuario_estudiante_activo', 'usuario_id, estudiante_activo_unico',
            'Un estudiante sólo está activo en una carrera.');
    }

    private function uniqueGeneratedColumn(string $table, string $column, string $definition, string $index, string $columns, string $rule): void
    {
        if (!Schema::hasColumn($table, $column)) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
        if ($this->hasIndex($table, $index)) {
            return;
        }
        $duplicates = DB::select("SELECT {$columns}, COUNT(*) AS total FROM {$table} WHERE {$column} IS NOT NULL GROUP BY {$columns} HAVING COUNT(*) > 1");
        if ($duplicates) {
            throw new RuntimeException("{$rule} Hay " . count($duplicates) . " casos en {$table} que lo incumplen.");
        }
        DB::statement("ALTER TABLE {$table} ADD UNIQUE KEY {$index} ({$columns})");
    }

    // Reglas que abarcan dos tablas; sólo validan filas nuevas o modificadas.
    private function crossTableTriggers(): void
    {
        $triggers = [
            'trg_documentos_carrera_bi' => "BEFORE INSERT ON documentos FOR EACH ROW
                BEGIN
                  IF NEW.proyecto_id IS NOT NULL AND NEW.carrera_id <> (SELECT carrera_id FROM proyectos WHERE id = NEW.proyecto_id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El documento debe pertenecer a la carrera de su proyecto';
                  END IF;
                END",
            'trg_documentos_carrera_bu' => "BEFORE UPDATE ON documentos FOR EACH ROW
                BEGIN
                  IF NEW.proyecto_id IS NOT NULL AND NEW.carrera_id <> (SELECT carrera_id FROM proyectos WHERE id = NEW.proyecto_id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El documento debe pertenecer a la carrera de su proyecto';
                  END IF;
                END",
            'trg_proyecto_integrantes_bi' => "BEFORE INSERT ON proyecto_integrantes FOR EACH ROW
                BEGIN
                  CALL sp_validar_integrante(NEW.proyecto_id, NEW.usuario_id, NEW.rol);
                END",
            'trg_proyecto_integrantes_bu' => "BEFORE UPDATE ON proyecto_integrantes FOR EACH ROW
                BEGIN
                  IF NEW.rol <> OLD.rol OR NEW.usuario_id <> OLD.usuario_id OR NEW.proyecto_id <> OLD.proyecto_id THEN
                    CALL sp_validar_integrante(NEW.proyecto_id, NEW.usuario_id, NEW.rol);
                  END IF;
                END",
            'trg_dictamenes_sala_bi' => "BEFORE INSERT ON dictamenes_docentes FOR EACH ROW
                BEGIN
                  IF NOT EXISTS (SELECT 1 FROM evaluaciones e JOIN sala_docentes sd ON sd.sala_id = e.sala_id
                                 WHERE e.id = NEW.evaluacion_id AND sd.docente_id = NEW.docente_id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El docente no está asignado a la sala de esta evaluación';
                  END IF;
                END",
        ];

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_validar_integrante');
        DB::unprepared("CREATE PROCEDURE sp_validar_integrante(IN p_proyecto BIGINT UNSIGNED, IN p_usuario VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_rol VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
            BEGIN
              DECLARE v_carrera SMALLINT UNSIGNED;
              DECLARE v_tipo VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
              SELECT carrera_id, tipo INTO v_carrera, v_tipo FROM proyectos WHERE id = p_proyecto;
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

        foreach ($triggers as $name => $body) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared("CREATE TRIGGER {$name} {$body}");
        }
    }

    private function views(): void
    {
        DB::statement("CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_evaluacion_horario AS
            SELECT e.id AS evaluacion_id, e.sala_id, e.proyecto_id, e.orden_presentacion,
                   s.inicia_en AS inicio_sala,
                   s.inicia_en + INTERVAL ((e.orden_presentacion - 1) * s.minutos_presentacion_proyecto) MINUTE AS inicio_estimado,
                   CASE e.apto_titulacion WHEN 1 THEN 'viable' WHEN 0 THEN 'no_viable' ELSE 'pendiente' END AS resultado
            FROM evaluaciones e JOIN salas_evaluacion s ON s.id = e.sala_id");

        DB::statement("CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_usuario AS
            SELECT u.id AS usuario_id,
                   CONCAT_WS(' ', u.nombres, u.apellido_paterno, u.apellido_materno) AS usuario_nombre,
                   IF(u.es_administrador_general = 1, 4, uc.perfil_id) AS perfil_global_id,
                   p.nombre AS perfil_global_nombre,
                   u.es_administrador_general,
                   u.activo AS usuario_activo,
                   u.perfil_completado_en,
                   CAST(u.creado_en AS DATE) AS fecha_alta
            FROM usuarios u
            LEFT JOIN usuario_carrera uc ON uc.usuario_id = u.id AND uc.es_principal = 1
            LEFT JOIN perfiles p ON p.id = IF(u.es_administrador_general = 1, 4, uc.perfil_id)");

        DB::statement("CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_evaluacion AS
            SELECT ev.id AS evaluacion_id, ev.carrera_id, ev.proyecto_id, p.grupo_id, g.periodo_id,
                   ev.sala_id, ev.orden_presentacion, ev.estado AS evaluacion_estado,
                   h.resultado AS evaluacion_resultado, ev.apto_titulacion,
                   h.inicio_sala AS fecha_exposicion, CAST(h.inicio_sala AS DATE) AS fecha_evaluacion,
                   COALESCE(da.dictamenes_esperados, 0) AS dictamenes_esperados,
                   COALESCE(da.dictamenes_enviados, 0) AS dictamenes_enviados,
                   COALESCE(da.respuestas, 0) AS respuestas,
                   da.puntaje_promedio
            FROM evaluaciones ev
            JOIN v_evaluacion_horario h ON h.evaluacion_id = ev.id
            JOIN proyectos p ON p.id = ev.proyecto_id
            JOIN grupos_academicos g ON g.id = p.grupo_id
            LEFT JOIN (
              SELECT d.evaluacion_id, COUNT(*) AS dictamenes_esperados,
                     SUM(d.enviado_en IS NOT NULL) AS dictamenes_enviados,
                     SUM(COALESCE(ra.respuestas, 0)) AS respuestas,
                     AVG(ra.puntaje_promedio) AS puntaje_promedio
              FROM dictamenes_docentes d
              LEFT JOIN (SELECT dictamen_id, COUNT(*) AS respuestas, AVG(puntaje) AS puntaje_promedio
                         FROM respuestas_evaluacion GROUP BY dictamen_id) ra ON ra.dictamen_id = d.id
              GROUP BY d.evaluacion_id) da ON da.evaluacion_id = ev.id");
    }

    private function foreignKeyOn(string $table, string $column): ?object
    {
        return DB::selectOne("SELECT k.constraint_name AS name, r.delete_rule AS rule FROM information_schema.key_column_usage k
            JOIN information_schema.referential_constraints r ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name
            WHERE k.table_schema = DATABASE() AND k.table_name = ? AND k.column_name = ? AND k.referenced_table_name IS NOT NULL
              AND (SELECT COUNT(*) FROM information_schema.key_column_usage x WHERE x.constraint_schema = k.constraint_schema
                   AND x.table_name = k.table_name AND x.constraint_name = k.constraint_name) = 1", [$table, $column]);
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

    // Tras una FK compuesta (columna, carrera_id), el índice de una sola columna sobra.
    private function dropSingleColumnIndexes(string $table, string $column): void
    {
        $indexes = DB::select("SELECT index_name AS name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ?
            AND non_unique = 1 GROUP BY index_name HAVING COUNT(*) = 1 AND MAX(column_name) = ?", [$table, $column]);
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE {$table} DROP INDEX `{$index->name}`");
        }
    }

    private function normalizedRule(string $rule): string
    {
        return in_array($rule, ['CASCADE', 'SET NULL'], true) ? $rule : 'RESTRICT';
    }

    private function constraintName(string ...$parts): string
    {
        $name = implode('_', $parts);

        return strlen($name) <= 64 ? $name : substr($name, 0, 55) . '_' . substr(md5($name), 0, 8);
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
};
