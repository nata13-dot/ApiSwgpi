-- Fase 6: pertenencia directa de carrera para entidades operativas.
-- El relleno se deriva exclusivamente de sus entidades padre.

ALTER TABLE cursos
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_cursos_carrera_activo (carrera_id, activo);

UPDATE cursos c
JOIN grupos_academicos g ON g.id = c.grupo_id
SET c.carrera_id = g.carrera_id
WHERE c.carrera_id IS NULL;

ALTER TABLE cursos
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_cursos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE entregables
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_entregables_carrera_estado (carrera_id, estado, activo);

UPDATE entregables e
JOIN cursos c ON c.id = e.curso_id
SET e.carrera_id = c.carrera_id
WHERE e.carrera_id IS NULL;

ALTER TABLE entregables
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_entregables_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE salas_evaluacion
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_salas_carrera_estado (carrera_id, estado);

UPDATE salas_evaluacion s
JOIN rubricas r ON r.id = s.rubrica_id
SET s.carrera_id = r.carrera_id
WHERE s.carrera_id IS NULL;

ALTER TABLE salas_evaluacion
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_salas_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE evaluaciones
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_evaluaciones_carrera_estado (carrera_id, estado);

UPDATE evaluaciones e
JOIN proyectos p ON p.id = e.proyecto_id
SET e.carrera_id = p.carrera_id
WHERE e.carrera_id IS NULL;

ALTER TABLE evaluaciones
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_evaluaciones_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE notificaciones
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_notificaciones_carrera_usuario (carrera_id, usuario_id, leida_en);

ALTER TABLE notificaciones
    ADD CONSTRAINT fk_notificaciones_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE;
