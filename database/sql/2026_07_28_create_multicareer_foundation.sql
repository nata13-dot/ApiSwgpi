-- SGPI: fase 1 de soporte multicarrera.
-- Este script está dirigido a la base normalizada `sgpi_v2_completa`.
-- Antes de ejecutarlo debe existir un respaldo completo.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS carreras (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave VARCHAR(20) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    nombre_corto VARCHAR(100) NOT NULL,
    color_primario CHAR(7) NOT NULL DEFAULT '#1B396A',
    color_secundario CHAR(7) NOT NULL DEFAULT '#2D5A96',
    color_acento CHAR(7) DEFAULT NULL,
    lema VARCHAR(255) DEFAULT NULL,
    logo_ruta VARCHAR(255) DEFAULT NULL,
    portada_ruta VARCHAR(255) DEFAULT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizada_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carreras_clave (clave),
    UNIQUE KEY uq_carreras_slug (slug),
    KEY idx_carreras_activas (activa, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO carreras
    (id, clave, slug, nombre, nombre_corto, color_primario, color_secundario, color_acento, lema)
VALUES
    (1, 'ISC', 'ingenieria-sistemas-computacionales', 'Ingeniería en Sistemas Computacionales', 'Ing. Sistemas Computacionales', '#1B396A', '#2D5A96', '#00A6D6', 'Tecnología, innovación y transformación digital.'),
    (2, 'IIND', 'ingenieria-industrial', 'Ingeniería Industrial', 'Ing. Industrial', '#073763', '#0B66D4', '#FF5A00', 'Planeación, mejora continua y productividad.'),
    (3, 'IEME', 'ingenieria-electromecanica', 'Ingeniería Electromecánica', 'Ing. Electromecánica', '#062B63', '#0B5CC7', '#F4C400', 'Innovación en energía, automatización y mantenimiento.'),
    (4, 'CP', 'contador-publico', 'Contador Público', 'Contador Público', '#073F3A', '#168B37', '#73C900', 'Gestión financiera, análisis y transparencia institucional.')
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug),
    nombre = VALUES(nombre),
    nombre_corto = VALUES(nombre_corto),
    color_primario = VALUES(color_primario),
    color_secundario = VALUES(color_secundario),
    color_acento = VALUES(color_acento),
    lema = VALUES(lema);

INSERT INTO perfiles (id, nombre)
VALUES (4, 'administrador_general')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

CREATE TABLE IF NOT EXISTS usuario_carrera (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id VARCHAR(20) NOT NULL,
    carrera_id SMALLINT UNSIGNED NOT NULL,
    perfil_id TINYINT UNSIGNED NOT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    asignado_por VARCHAR(20) DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_carrera (usuario_id, carrera_id),
    KEY idx_usuario_carrera_contexto (usuario_id, activo, es_principal),
    KEY idx_carrera_perfil_activo (carrera_id, perfil_id, activo),
    CONSTRAINT fk_usuario_carrera_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_carrera_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id),
    CONSTRAINT fk_usuario_carrera_perfil FOREIGN KEY (perfil_id) REFERENCES perfiles (id),
    CONSTRAINT fk_usuario_carrera_asignador FOREIGN KEY (asignado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carrera_modulos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    carrera_id SMALLINT UNSIGNED NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    habilitado TINYINT(1) NOT NULL DEFAULT 1,
    configuracion JSON DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carrera_modulo (carrera_id, modulo),
    CONSTRAINT fk_carrera_modulos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuraciones_carrera (
    carrera_id SMALLINT UNSIGNED NOT NULL,
    clave VARCHAR(100) NOT NULL,
    valor JSON DEFAULT NULL,
    tipo VARCHAR(30) NOT NULL DEFAULT 'string',
    descripcion TEXT DEFAULT NULL,
    creada_en DATETIME DEFAULT NULL,
    actualizada_en DATETIME DEFAULT NULL,
    PRIMARY KEY (carrera_id, clave),
    CONSTRAINT fk_configuraciones_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO carrera_modulos (carrera_id, modulo, habilitado)
SELECT c.id, m.modulo, 1
FROM carreras c
CROSS JOIN (
    SELECT 'usuarios' modulo UNION ALL
    SELECT 'proyectos' UNION ALL
    SELECT 'academico' UNION ALL
    SELECT 'entregables' UNION ALL
    SELECT 'evaluaciones' UNION ALL
    SELECT 'repositorio' UNION ALL
    SELECT 'reportes' UNION ALL
    SELECT 'configuracion'
) m
WHERE 1 = 1
ON DUPLICATE KEY UPDATE habilitado = VALUES(habilitado);

INSERT INTO usuario_carrera (usuario_id, carrera_id, perfil_id, es_principal, activo)
SELECT id, 1, perfil_id, 1, activo
FROM usuarios
ON DUPLICATE KEY UPDATE
    perfil_id = VALUES(perfil_id),
    es_principal = 1,
    activo = VALUES(activo);

ALTER TABLE grupos_academicos
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_grupos_carrera_periodo_activo (carrera_id, periodo_id, activo);
UPDATE grupos_academicos SET carrera_id = 1 WHERE carrera_id IS NULL;
ALTER TABLE grupos_academicos
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    DROP INDEX uq_grupo_periodo_semestre_clave,
    ADD UNIQUE KEY uq_grupo_carrera_periodo_semestre_clave (carrera_id, periodo_id, semestre, clave_grupo),
    ADD CONSTRAINT fk_grupos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE asignaturas
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_asignaturas_carrera_activo (carrera_id, activo);
UPDATE asignaturas SET carrera_id = 1 WHERE carrera_id IS NULL;
ALTER TABLE asignaturas
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    DROP INDEX uq_asignaturas_clave,
    ADD UNIQUE KEY uq_asignaturas_carrera_clave (carrera_id, clave),
    ADD CONSTRAINT fk_asignaturas_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE proyectos
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_proyectos_carrera_estado_activo (carrera_id, estado, activo);
UPDATE proyectos p
JOIN grupos_academicos g ON g.id = p.grupo_id
SET p.carrera_id = g.carrera_id
WHERE p.carrera_id IS NULL;
ALTER TABLE proyectos
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_proyectos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE rubricas
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_rubricas_carrera_etapa_activa (carrera_id, etapa, activa);
UPDATE rubricas SET carrera_id = 1 WHERE carrera_id IS NULL;
ALTER TABLE rubricas
    MODIFY carrera_id SMALLINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_rubricas_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

ALTER TABLE documentos
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_documentos_carrera_visibilidad_estado (carrera_id, visibilidad, estado),
    ADD CONSTRAINT fk_documentos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);
UPDATE documentos d
LEFT JOIN proyectos p ON p.id = d.proyecto_id
SET d.carrera_id = COALESCE(p.carrera_id, 1)
WHERE d.carrera_id IS NULL;

ALTER TABLE etiquetas
    ADD COLUMN carrera_id SMALLINT UNSIGNED NULL AFTER id,
    ADD KEY idx_etiquetas_carrera_activa (carrera_id, activa),
    ADD CONSTRAINT fk_etiquetas_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id),
    DROP INDEX uq_etiquetas_nombre,
    ADD UNIQUE KEY uq_etiquetas_carrera_nombre (carrera_id, nombre);
UPDATE etiquetas SET carrera_id = 1 WHERE carrera_id IS NULL;
