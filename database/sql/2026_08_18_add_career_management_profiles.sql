-- Perfiles de gestión por carrera para sgpi_v2_completa.
-- Script idempotente: puede ejecutarse más de una vez sin duplicar registros.

USE `sgpi_v2_completa`;

INSERT INTO `perfiles` (`id`, `nombre`, `descripcion`) VALUES
    (5, 'jefe_carrera', 'Responsable académico y administrativo de una carrera'),
    (6, 'asistente_jefe_carrera', 'Apoyo operativo académico del jefe de carrera'),
    (7, 'coordinador_proyectos', 'Responsable del seguimiento de proyectos integradores')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

