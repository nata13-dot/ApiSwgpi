-- Completa las dos relaciones físicas que faltaban en criterios_rubrica.
-- El script es idempotente y debe ejecutarse después de comprobar que no hay
-- referencias huérfanas (bi_control_calidad y el diagnóstico institucional).

SET @fk_rubrica_existe = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_criterios_rubrica'
);
SET @sql_fk_rubrica = IF(
    @fk_rubrica_existe = 0,
    'ALTER TABLE criterios_rubrica ADD CONSTRAINT fk_criterios_rubrica FOREIGN KEY (rubrica_id) REFERENCES rubricas(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_rubrica FROM @sql_fk_rubrica;
EXECUTE stmt_fk_rubrica;
DEALLOCATE PREPARE stmt_fk_rubrica;

SET @fk_proyecto_existe = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_criterios_proyecto'
);
SET @sql_fk_proyecto = IF(
    @fk_proyecto_existe = 0,
    -- proyecto_id alimenta la columna generada proyecto_scope. MariaDB no
    -- permite ON UPDATE CASCADE sobre una columna usada por una expresión.
    'ALTER TABLE criterios_rubrica ADD CONSTRAINT fk_criterios_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_fk_proyecto FROM @sql_fk_proyecto;
EXECUTE stmt_fk_proyecto;
DEALLOCATE PREPARE stmt_fk_proyecto;
