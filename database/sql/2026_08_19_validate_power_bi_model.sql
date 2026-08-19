-- Validación posterior a la instalación de la capa semántica.

SELECT 'bi_dim_carrera' AS objeto, COUNT(*) AS filas, COUNT(DISTINCT carrera_id) AS claves_unicas
FROM bi_dim_carrera
UNION ALL
SELECT 'bi_dim_usuario', COUNT(*), COUNT(DISTINCT usuario_id) FROM bi_dim_usuario
UNION ALL
SELECT 'bi_dim_proyecto', COUNT(*), COUNT(DISTINCT proyecto_id) FROM bi_dim_proyecto
UNION ALL
SELECT 'bi_fact_usuario_carrera', COUNT(*), COUNT(DISTINCT membresia_id) FROM bi_fact_usuario_carrera
UNION ALL
SELECT 'bi_fact_grupo_estudiante', COUNT(*), COUNT(DISTINCT inscripcion_clave) FROM bi_fact_grupo_estudiante
UNION ALL
SELECT 'bi_fact_curso', COUNT(*), COUNT(DISTINCT curso_id) FROM bi_fact_curso
UNION ALL
SELECT 'bi_fact_proyecto_integrante', COUNT(*), COUNT(DISTINCT participacion_clave) FROM bi_fact_proyecto_integrante
UNION ALL
SELECT 'bi_fact_evaluacion', COUNT(*), COUNT(DISTINCT evaluacion_id) FROM bi_fact_evaluacion
UNION ALL
SELECT 'bi_fact_dictamen_docente', COUNT(*), COUNT(DISTINCT dictamen_id) FROM bi_fact_dictamen_docente
UNION ALL
SELECT 'bi_fact_respuesta_evaluacion', COUNT(*), COUNT(DISTINCT respuesta_id) FROM bi_fact_respuesta_evaluacion
UNION ALL
SELECT 'bi_fact_documento', COUNT(*), COUNT(DISTINCT documento_id) FROM bi_fact_documento
UNION ALL
SELECT 'bi_fact_entregable', COUNT(*), COUNT(DISTINCT entregable_id) FROM bi_fact_entregable
UNION ALL
SELECT 'bi_fact_entrega', COUNT(*), COUNT(DISTINCT entrega_id) FROM bi_fact_entrega;

SELECT regla, severidad, incidencias
FROM bi_control_calidad
ORDER BY FIELD(severidad, 'critica', 'advertencia'), regla;

