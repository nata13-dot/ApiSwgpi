-- Control transaccional de turnos y temporizador para salas de evaluacion.
-- Compatible con sgpi_v2_completa e idempotente para despliegues repetibles.

SET @col_responsable = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion' AND COLUMN_NAME = 'responsable_id'
);
SET @sql = IF(@col_responsable = 0,
    'ALTER TABLE salas_evaluacion ADD COLUMN responsable_id VARCHAR(20) NULL AFTER creado_por',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_secuencia = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion' AND COLUMN_NAME = 'secuencia_bloqueada'
);
SET @sql = IF(@col_secuencia = 0,
    'ALTER TABLE salas_evaluacion ADD COLUMN secuencia_bloqueada TINYINT(1) NOT NULL DEFAULT 0 AFTER estado, ADD COLUMN orden_actual SMALLINT UNSIGNED NULL AFTER secuencia_bloqueada, ADD COLUMN secuencia_version BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER orden_actual, ADD COLUMN completada_en DATETIME NULL AFTER secuencia_version',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_temporizador = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion' AND COLUMN_NAME = 'temporizador_estado'
);
SET @sql = IF(@col_temporizador = 0,
    "ALTER TABLE salas_evaluacion ADD COLUMN temporizador_estado ENUM('detenido','en_curso','pausado','finalizado') NOT NULL DEFAULT 'detenido' AFTER completada_en, ADD COLUMN temporizador_orden SMALLINT UNSIGNED NULL AFTER temporizador_estado, ADD COLUMN temporizador_duracion_segundos INT UNSIGNED NOT NULL DEFAULT 1200 AFTER temporizador_orden, ADD COLUMN temporizador_iniciado_en DATETIME NULL AFTER temporizador_duracion_segundos, ADD COLUMN temporizador_finaliza_en DATETIME NULL AFTER temporizador_iniciado_en, ADD COLUMN temporizador_restante_segundos INT UNSIGNED NULL AFTER temporizador_finaliza_en, ADD COLUMN temporizador_actualizado_por VARCHAR(20) NULL AFTER temporizador_restante_segundos",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Los registros existentes usaban creado_por como responsable de facto.
UPDATE salas_evaluacion
SET responsable_id = creado_por
WHERE responsable_id IS NULL AND creado_por IS NOT NULL;

SET @fk_responsable = (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_salas_responsable'
);
SET @sql = IF(@fk_responsable = 0,
    'ALTER TABLE salas_evaluacion ADD CONSTRAINT fk_salas_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_timer_usuario = (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_salas_timer_usuario'
);
SET @sql = IF(@fk_timer_usuario = 0,
    'ALTER TABLE salas_evaluacion ADD CONSTRAINT fk_salas_timer_usuario FOREIGN KEY (temporizador_actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_turno = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion' AND INDEX_NAME = 'idx_salas_turno'
);
SET @sql = IF(@idx_turno = 0,
    'ALTER TABLE salas_evaluacion ADD INDEX idx_salas_turno (estado, secuencia_bloqueada, orden_actual)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_timer = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion' AND INDEX_NAME = 'idx_salas_temporizador'
);
SET @sql = IF(@idx_timer = 0,
    'ALTER TABLE salas_evaluacion ADD INDEX idx_salas_temporizador (temporizador_estado, temporizador_finaliza_en)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
