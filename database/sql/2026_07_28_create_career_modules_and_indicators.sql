-- SGPI fase 5: catálogos modulares e indicadores independientes por carrera.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS registros_modulo_carrera (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    carrera_id SMALLINT UNSIGNED NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    clave VARCHAR(50) DEFAULT NULL,
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'activo',
    responsable_id VARCHAR(20) DEFAULT NULL,
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    datos JSON DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registro_modulo_clave (carrera_id, modulo, clave),
    KEY idx_registro_modulo_estado (carrera_id, modulo, estado, activo),
    KEY idx_registro_modulo_responsable (responsable_id),
    CONSTRAINT fk_registro_modulo_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE,
    CONSTRAINT fk_registro_modulo_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicadores_carrera (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    carrera_id SMALLINT UNSIGNED NOT NULL,
    modulo VARCHAR(80) NOT NULL DEFAULT 'indicadores',
    clave VARCHAR(80) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT 'porcentaje',
    valor_actual DECIMAL(12,2) DEFAULT NULL,
    valor_meta DECIMAL(12,2) DEFAULT NULL,
    color CHAR(7) DEFAULT NULL,
    icono VARCHAR(80) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_indicador_carrera_clave (carrera_id, clave),
    KEY idx_indicador_carrera_modulo (carrera_id, modulo, activo),
    CONSTRAINT fk_indicador_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO carrera_modulos (carrera_id, modulo, habilitado, configuracion)
VALUES
    (1, 'desarrollo_software', 1, JSON_OBJECT('label', 'Desarrollo de software', 'icon', 'bi-code-slash')),
    (1, 'infraestructura_ti', 1, JSON_OBJECT('label', 'Infraestructura TI', 'icon', 'bi-hdd-network')),
    (2, 'procesos', 1, JSON_OBJECT('label', 'Procesos', 'icon', 'bi-diagram-3')),
    (2, 'indicadores_operativos', 1, JSON_OBJECT('label', 'Indicadores operativos', 'icon', 'bi-bar-chart')),
    (2, 'mejora_continua', 1, JSON_OBJECT('label', 'Mejora continua', 'icon', 'bi-arrow-repeat')),
    (3, 'laboratorios', 1, JSON_OBJECT('label', 'Laboratorios', 'icon', 'bi-tools')),
    (3, 'equipos', 1, JSON_OBJECT('label', 'Equipos', 'icon', 'bi-cpu')),
    (3, 'mantenimiento', 1, JSON_OBJECT('label', 'Mantenimiento', 'icon', 'bi-wrench-adjustable')),
    (4, 'reportes_financieros', 1, JSON_OBJECT('label', 'Reportes financieros', 'icon', 'bi-file-earmark-bar-graph')),
    (4, 'presupuestos', 1, JSON_OBJECT('label', 'Presupuestos', 'icon', 'bi-calculator')),
    (4, 'auditoria', 1, JSON_OBJECT('label', 'Auditoría', 'icon', 'bi-clipboard-data'))
ON DUPLICATE KEY UPDATE
    habilitado = VALUES(habilitado),
    configuracion = VALUES(configuracion),
    actualizado_en = NOW();

INSERT INTO indicadores_carrera
    (carrera_id, modulo, clave, nombre, descripcion, unidad, valor_actual, valor_meta, color, icono)
VALUES
    (1, 'desarrollo_software', 'avance_proyectos', 'Avance de proyectos', 'Cumplimiento global de proyectos integradores.', 'porcentaje', NULL, 85, '#00A6D6', 'bi-kanban'),
    (1, 'academico', 'entregables_aprobados', 'Entregables aprobados', 'Porcentaje de entregables cerrados satisfactoriamente.', 'porcentaje', NULL, 90, '#218838', 'bi-file-earmark-check'),
    (1, 'evaluaciones', 'evaluaciones_completadas', 'Evaluaciones completadas', 'Evaluaciones finalizadas en el periodo activo.', 'porcentaje', NULL, 90, '#2D5A96', 'bi-clipboard-check'),
    (2, 'indicadores_operativos', 'eficiencia_operacional', 'Eficiencia operacional', 'Eficiencia de los procesos académicos y operativos.', 'porcentaje', NULL, 90, '#0B66D4', 'bi-speedometer2'),
    (2, 'indicadores_operativos', 'productividad', 'Productividad', 'Productividad registrada en los procesos activos.', 'porcentaje', NULL, 85, '#FF5A00', 'bi-graph-up-arrow'),
    (2, 'mejora_continua', 'mejora_continua', 'Mejora continua', 'Cumplimiento de acciones de mejora.', 'porcentaje', NULL, 80, '#35A853', 'bi-arrow-repeat'),
    (3, 'equipos', 'disponibilidad_equipos', 'Disponibilidad de equipos', 'Equipos disponibles respecto del inventario activo.', 'porcentaje', NULL, 90, '#0B5CC7', 'bi-cpu'),
    (3, 'laboratorios', 'eficiencia_operativa', 'Eficiencia operativa', 'Eficiencia de uso de laboratorios.', 'porcentaje', NULL, 85, '#F4C400', 'bi-tools'),
    (3, 'mantenimiento', 'mantenimiento_preventivo', 'Mantenimiento preventivo', 'Cumplimiento del programa de mantenimiento.', 'porcentaje', NULL, 80, '#35A853', 'bi-wrench-adjustable'),
    (4, 'reportes_financieros', 'eficiencia_operativa', 'Eficiencia operativa', 'Eficiencia de los procesos contables.', 'porcentaje', NULL, 90, '#0B5CC7', 'bi-speedometer2'),
    (4, 'auditoria', 'transparencia_financiera', 'Transparencia financiera', 'Cumplimiento de controles y evidencias financieras.', 'porcentaje', NULL, 85, '#168B37', 'bi-shield-check'),
    (4, 'presupuestos', 'cumplimiento_presupuestal', 'Cumplimiento presupuestal', 'Avance respecto de las metas presupuestales.', 'porcentaje', NULL, 80, '#73C900', 'bi-calculator')
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    unidad = VALUES(unidad),
    valor_meta = VALUES(valor_meta),
    color = VALUES(color),
    icono = VALUES(icono),
    actualizado_en = NOW();
