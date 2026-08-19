CREATE TABLE IF NOT EXISTS ejecuciones_integridad (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ejecutado_por VARCHAR(20) NULL,
    origen ENUM('manual','programado','consola') NOT NULL DEFAULT 'manual',
    saludable TINYINT(1) NOT NULL,
    incidencias INT UNSIGNED NOT NULL DEFAULT 0,
    verificaciones_correctas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    verificaciones_totales SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    reporte JSON NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_integridad_fecha (creado_en),
    KEY idx_integridad_saludable_fecha (saludable, creado_en),
    CONSTRAINT fk_integridad_actor FOREIGN KEY (ejecutado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
