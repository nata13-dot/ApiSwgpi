CREATE TABLE IF NOT EXISTS alertas_operativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    huella VARCHAR(120) NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    severidad ENUM('informativa','advertencia','critica') NOT NULL,
    estado ENUM('abierta','atendida','resuelta') NOT NULL DEFAULT 'abierta',
    titulo VARCHAR(180) NOT NULL,
    detalle VARCHAR(1000) NOT NULL,
    datos JSON NULL,
    atendida_por VARCHAR(20) NULL,
    detectada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendida_en DATETIME NULL,
    resuelta_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alerta_huella (huella),
    KEY idx_alerta_estado_severidad (estado, severidad, actualizada_en),
    CONSTRAINT fk_alerta_atendida_actor FOREIGN KEY (atendida_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
