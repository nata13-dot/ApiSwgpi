CREATE TABLE IF NOT EXISTS respaldos_base_datos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    creado_por VARCHAR(20) NULL,
    origen ENUM('manual','programado','consola') NOT NULL DEFAULT 'manual',
    estado ENUM('completado','fallido') NOT NULL,
    nombre_archivo VARCHAR(255) NULL,
    ruta_privada VARCHAR(500) NULL,
    tamano_bytes BIGINT UNSIGNED NULL,
    checksum_sha256 CHAR(64) NULL,
    mensaje_error VARCHAR(1000) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_respaldos_estado_fecha (estado, creado_en),
    CONSTRAINT fk_respaldos_actor FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
