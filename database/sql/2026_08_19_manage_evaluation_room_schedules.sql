-- Politica de horarios y autorizacion de evaluaciones fuera de tiempo.
-- Idempotente y compatible con sgpi_v2_completa.

SET @col_fuera_horario = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion'
      AND COLUMN_NAME = 'permite_evaluacion_fuera_horario'
);
SET @sql = IF(@col_fuera_horario = 0,
    'ALTER TABLE salas_evaluacion ADD COLUMN permite_evaluacion_fuera_horario TINYINT(1) NOT NULL DEFAULT 0 AFTER temporizador_actualizado_por, ADD COLUMN evaluacion_fuera_horario_hasta DATETIME NULL AFTER permite_evaluacion_fuera_horario, ADD COLUMN motivo_evaluacion_fuera_horario VARCHAR(500) NULL AFTER evaluacion_fuera_horario_hasta, ADD COLUMN horario_actualizado_por VARCHAR(20) NULL AFTER motivo_evaluacion_fuera_horario, ADD COLUMN horario_actualizado_en DATETIME NULL AFTER horario_actualizado_por',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_horario_usuario = (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_salas_horario_usuario'
);
SET @sql = IF(@fk_horario_usuario = 0,
    'ALTER TABLE salas_evaluacion ADD CONSTRAINT fk_salas_horario_usuario FOREIGN KEY (horario_actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_ventana = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salas_evaluacion'
      AND INDEX_NAME = 'idx_salas_ventana_evaluacion'
);
SET @sql = IF(@idx_ventana = 0,
    'ALTER TABLE salas_evaluacion ADD INDEX idx_salas_ventana_evaluacion (estado, inicia_en, termina_en, permite_evaluacion_fuera_horario, evaluacion_fuera_horario_hasta)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
