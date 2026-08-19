CREATE TABLE IF NOT EXISTS mediciones_continuidad (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    medido_por VARCHAR(20) NULL,
    origen ENUM('manual','programado','consola') NOT NULL DEFAULT 'manual',
    indice_preparacion TINYINT UNSIGNED NOT NULL,
    controles_correctos TINYINT UNSIGNED NOT NULL,
    controles_totales TINYINT UNSIGNED NOT NULL,
    incidencias_integridad INT UNSIGNED NOT NULL DEFAULT 0,
    respaldos_disponibles INT UNSIGNED NOT NULL DEFAULT 0,
    respaldos_verificados INT UNSIGNED NOT NULL DEFAULT 0,
    alertas_activas INT UNSIGNED NOT NULL DEFAULT 0,
    alertas_criticas INT UNSIGNED NOT NULL DEFAULT 0,
    instantanea JSON NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_medicion_fecha (creado_en),
    KEY idx_medicion_indice_fecha (indice_preparacion, creado_en),
    CONSTRAINT fk_medicion_actor FOREIGN KEY (medido_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
