-- Fase 8: configuración funcional independiente por carrera.
INSERT INTO configuraciones_carrera
    (carrera_id, clave, valor, tipo, descripcion, creada_en, actualizada_en)
SELECT c.id, setting.clave,
       CASE
           WHEN c.id = 1 THEN COALESCE(global.valor, setting.valor_predeterminado)
           ELSE setting.valor_predeterminado
       END,
       setting.tipo,
       setting.descripcion,
       NOW(),
       NOW()
FROM carreras c
CROSS JOIN (
    SELECT 'system_notices' clave, JSON_OBJECT('data', JSON_ARRAY()) valor_predeterminado, 'array' tipo, 'Avisos propios de la carrera' descripcion
    UNION ALL SELECT 'proposal_registration_enabled', JSON_OBJECT('data', TRUE), 'boolean', 'Registro de propuestas para la carrera'
    UNION ALL SELECT 'max_project_members', JSON_OBJECT('data', 4), 'integer', 'Máximo de integrantes por proyecto'
    UNION ALL SELECT 'evaluation_manager_teacher_ids', JSON_OBJECT('data', JSON_ARRAY()), 'array', 'Responsables de evaluación de la carrera'
    UNION ALL SELECT 'rubric_score_modes', JSON_OBJECT('data', JSON_OBJECT()), 'array', 'Modos de rúbrica de la carrera'
) setting
LEFT JOIN configuraciones_sistema global ON global.clave = setting.clave
WHERE 1 = 1
ON DUPLICATE KEY UPDATE
    clave = VALUES(clave);

UPDATE configuraciones_sistema
SET valor = JSON_OBJECT('data', JSON_ARRAY()), actualizada_en = NOW()
WHERE clave = 'system_notices';
