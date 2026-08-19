CREATE TABLE IF NOT EXISTS politica_continuidad (
    id TINYINT UNSIGNED NOT NULL,
    objetivo_preparacion TINYINT UNSIGNED NOT NULL DEFAULT 100,
    umbral_critico TINYINT UNSIGNED NOT NULL DEFAULT 75,
    antiguedad_maxima_respaldo_horas SMALLINT UNSIGNED NOT NULL DEFAULT 26,
    retencion_respaldos_dias SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    actualizado_por VARCHAR(20) NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_politica_continuidad_actor FOREIGN KEY (actualizado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO politica_continuidad (
    id, objetivo_preparacion, umbral_critico,
    antiguedad_maxima_respaldo_horas, retencion_respaldos_dias
) VALUES (1, 100, 75, 26, 30)
ON DUPLICATE KEY UPDATE id = VALUES(id);
