CREATE TABLE IF NOT EXISTS verificaciones_restauracion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    respaldo_id BIGINT UNSIGNED NOT NULL,
    verificado_por VARCHAR(20) NULL,
    estado ENUM('correcto','fallido') NOT NULL,
    tablas_encontradas INT UNSIGNED NOT NULL DEFAULT 0,
    filas_verificadas BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mensaje_error VARCHAR(1000) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_verificacion_respaldo_fecha (respaldo_id, creado_en),
    CONSTRAINT fk_verificacion_respaldo FOREIGN KEY (respaldo_id) REFERENCES respaldos_base_datos (id) ON DELETE CASCADE,
    CONSTRAINT fk_verificacion_actor FOREIGN KEY (verificado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
