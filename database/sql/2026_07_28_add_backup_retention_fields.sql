ALTER TABLE respaldos_base_datos
    ADD COLUMN eliminado_en DATETIME NULL AFTER mensaje_error,
    ADD COLUMN eliminado_por VARCHAR(20) NULL AFTER eliminado_en,
    ADD INDEX idx_respaldos_eliminado_fecha (eliminado_en, creado_en),
    ADD CONSTRAINT fk_respaldos_eliminado_actor FOREIGN KEY (eliminado_por) REFERENCES usuarios (id) ON DELETE SET NULL;
