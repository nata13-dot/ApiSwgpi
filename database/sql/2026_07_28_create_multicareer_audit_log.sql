CREATE TABLE IF NOT EXISTS auditoria_carreras (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    carrera_id SMALLINT UNSIGNED NULL,
    actor_id VARCHAR(20) NULL,
    metodo VARCHAR(10) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    accion VARCHAR(180) NULL,
    estado_http SMALLINT UNSIGNED NOT NULL,
    direccion_ip VARCHAR(45) NULL,
    agente_usuario VARCHAR(255) NULL,
    detalle JSON NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_carrera_fecha (carrera_id, creado_en),
    KEY idx_auditoria_actor_fecha (actor_id, creado_en),
    KEY idx_auditoria_estado_fecha (estado_http, creado_en),
    CONSTRAINT fk_auditoria_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE SET NULL,
    CONSTRAINT fk_auditoria_actor FOREIGN KEY (actor_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
