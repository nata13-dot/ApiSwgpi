-- Datos de prueba para Repositorio SGPI.
-- Ejecutar sobre la BD de pruebas/produccion controlada.
-- Nota: este script crea registros y etiquetas. Para probar descarga/vista previa,
-- al menos un registro existente debe tener un archivo_path real; si no existe,
-- los documentos apareceran en listas pero download/view responderan 404 hasta subir archivos reales.
-- Cubre casos PUBLICOS y PRIVADOS para:
-- - Repositorio general
-- - Desarrollo de proyecto
-- - Tesis 9no
-- - Residencias 9no

START TRANSACTION;

SET @admin_id := (
    SELECT id FROM users
    WHERE activo = 1 AND perfil_id = 1
    ORDER BY id
    LIMIT 1
);

SET @student_id := (
    SELECT id FROM users
    WHERE activo = 1 AND perfil_id = 3
    ORDER BY id
    LIMIT 1
);

SET @publisher_id := COALESCE(@admin_id, @student_id, (SELECT id FROM users WHERE activo = 1 ORDER BY id LIMIT 1));
SET @uploader_id := COALESCE(@student_id, @publisher_id);

SET @project_id := (
    SELECT id FROM projects
    WHERE activo = 1
    ORDER BY id
    LIMIT 1
);

SET @sample_path := COALESCE(
    (SELECT archivo_path FROM repository_documents WHERE archivo_path IS NOT NULL AND archivo_path <> '' ORDER BY id DESC LIMIT 1),
    'repositorio/prueba-repositorio.pdf'
);

INSERT INTO document_tags (nombre, color, descripcion, created_at, activo) VALUES
('Prueba repositorio', '#1B396A', 'Etiqueta para validar busqueda y filtros del repositorio.', NOW(), 1),
('Residencias', '#0f766e', 'Documentos relacionados con residencia profesional.', NOW(), 1),
('Tesis 9no', '#6f42c1', 'Avances y documentos de tesis de noveno semestre.', NOW(), 1),
('Publico demo', '#218838', 'Documentos visibles en el repositorio publico.', NOW(), 1),
('Privado revision', '#b38600', 'Documentos privados para revision docente o administrativa.', NOW(), 1),
('Desarrollo de proyecto', '#2D5A96', 'Documentos de desarrollo vinculados a proyectos.', NOW(), 1)
ON DUPLICATE KEY UPDATE
    color = VALUES(color),
    descripcion = VALUES(descripcion),
    activo = 1;

DELETE FROM repository_documents
WHERE nombre IN (
    'Demo publico - Proyecto integrador',
    'Demo publico - Manual tecnico',
    'Demo publico - Articulo de divulgacion',
    'Demo privado - Borrador general',
    'Demo privado - Documento de evaluacion',
    'Demo privado - Desarrollo capitulo 1',
    'Demo privado - Desarrollo capitulo 2',
    'Demo publico - Desarrollo aprobado',
    'Demo tesis general 9no',
    'Demo tesis general publicada',
    'Demo tesis residencia 9no',
    'Demo tesis residencia publicada',
    'Demo residencia privada - Reporte parcial',
    'Demo residencia publica - Reporte final'
);

INSERT INTO repository_documents
    (project_id, nombre, descripcion, autores, archivo_path, archivo_tipo, uploaded_by, document_category, visibility, published_at, published_by, created_at, updated_at, activo)
VALUES
    (@project_id, 'Demo publico - Proyecto integrador', 'Documento publico para validar listado principal, detalle, etiquetas y filtros.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'repository', 'public', NOW(), @publisher_id, NOW(), NOW(), 1),
    (@project_id, 'Demo publico - Manual tecnico', 'Manual publico para validar multiples documentos generales visibles para visitantes.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'repository', 'public', NOW(), @publisher_id, NOW(), NOW(), 1),
    (@project_id, 'Demo publico - Articulo de divulgacion', 'Articulo publico usado para validar busqueda por descripcion y autores.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'repository', 'public', NOW(), @publisher_id, NOW(), NOW(), 1),
    (@project_id, 'Demo privado - Borrador general', 'Documento general privado que solo docentes y administradores deben poder consultar.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'repository', 'private', NULL, NULL, NOW(), NOW(), 1),

    (@project_id, 'Demo privado - Documento de evaluacion', 'Documento privado visible para docentes y administradores.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'evaluation_document', 'private', NULL, NULL, NOW(), NOW(), 1),
    (@project_id, 'Demo privado - Desarrollo capitulo 1', 'Capitulo privado de desarrollo de proyecto para revision docente.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'evaluation_document', 'private', NULL, NULL, NOW(), NOW(), 1),
    (@project_id, 'Demo privado - Desarrollo capitulo 2', 'Segundo avance privado de desarrollo de proyecto para comparar versiones.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'evaluation_document', 'private', NULL, NULL, NOW(), NOW(), 1),
    (@project_id, 'Demo publico - Desarrollo aprobado', 'Documento de desarrollo publicado por administracion despues de revision.', 'Equipo SGPI Demo', @sample_path, 'pdf', @uploader_id, 'evaluation_document', 'public', NOW(), @publisher_id, NOW(), NOW(), 1),

    (@project_id, 'Demo tesis general 9no', 'Avance de tesis de noveno semestre para validar categoria general.', 'Alumno 9no Demo', @sample_path, 'pdf', @uploader_id, 'thesis_general', 'private', NULL, NULL, NOW(), NOW(), 1),
    (@project_id, 'Demo tesis general publicada', 'Tesis general publicada para validar visibilidad publica despues de aprobacion.', 'Alumno 9no Demo', @sample_path, 'pdf', @uploader_id, 'thesis_general', 'public', NOW(), @publisher_id, NOW(), NOW(), 1),

    (@project_id, 'Demo tesis residencia 9no', 'Avance con filtro de residencias para validar separacion de categoria.', 'Alumno 9no Demo', @sample_path, 'pdf', @uploader_id, 'thesis_residency', 'private', NULL, NULL, NOW(), NOW(), 1),
    (@project_id, 'Demo tesis residencia publicada', 'Residencia publicada por administrador para validar visibilidad publica.', 'Alumno 9no Demo', @sample_path, 'pdf', @uploader_id, 'thesis_residency', 'public', NOW(), @publisher_id, NOW(), NOW(), 1),
    (@project_id, 'Demo residencia privada - Reporte parcial', 'Reporte parcial privado de residencia para revision interna.', 'Alumno 9no Demo', @sample_path, 'pdf', @uploader_id, 'thesis_residency', 'private', NULL, NULL, NOW(), NOW(), 1),
    (@project_id, 'Demo residencia publica - Reporte final', 'Reporte final de residencia publicado para visitantes del repositorio.', 'Alumno 9no Demo', @sample_path, 'pdf', @uploader_id, 'thesis_residency', 'public', NOW(), @publisher_id, NOW(), NOW(), 1);

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Prueba repositorio', 'Publico demo')
WHERE rd.nombre IN ('Demo publico - Proyecto integrador', 'Demo publico - Manual tecnico', 'Demo publico - Articulo de divulgacion');

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Prueba repositorio', 'Privado revision')
WHERE rd.nombre = 'Demo privado - Borrador general';

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Desarrollo de proyecto', 'Privado revision')
WHERE rd.nombre IN ('Demo privado - Documento de evaluacion', 'Demo privado - Desarrollo capitulo 1', 'Demo privado - Desarrollo capitulo 2');

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Desarrollo de proyecto', 'Publico demo')
WHERE rd.nombre = 'Demo publico - Desarrollo aprobado';

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Tesis 9no', 'Privado revision')
WHERE rd.nombre = 'Demo tesis general 9no';

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Tesis 9no', 'Publico demo')
WHERE rd.nombre = 'Demo tesis general publicada';

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Tesis 9no', 'Residencias', 'Privado revision')
WHERE rd.nombre IN ('Demo tesis residencia 9no', 'Demo residencia privada - Reporte parcial');

INSERT IGNORE INTO repository_document_tag (repository_document_id, document_tag_id, created_at)
SELECT rd.id, dt.id, NOW()
FROM repository_documents rd
JOIN document_tags dt ON dt.nombre IN ('Tesis 9no', 'Residencias', 'Publico demo')
WHERE rd.nombre IN ('Demo tesis residencia publicada', 'Demo residencia publica - Reporte final');

COMMIT;
