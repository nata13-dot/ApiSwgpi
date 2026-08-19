-- Capa semántica de lectura para Power BI y herramientas de inteligencia de negocio.
-- Grano: cada vista fact_* declara una fila por evento/relación y evita unir
-- simultáneamente varias tablas puente sobre la misma consulta.
-- No contiene contraseñas, CURP, teléfono, dirección ni rutas privadas.

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_carrera AS
SELECT
    c.id AS carrera_id,
    c.clave AS carrera_clave,
    c.nombre AS carrera_nombre,
    c.nombre_corto AS carrera_nombre_corto,
    c.slug AS carrera_slug,
    c.activa AS carrera_activa
FROM carreras c;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_perfil AS
SELECT
    p.id AS perfil_id,
    p.nombre AS perfil_nombre,
    p.descripcion AS perfil_descripcion
FROM perfiles p;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_usuario AS
SELECT
    u.id AS usuario_id,
    CONCAT_WS(' ', u.nombres, u.apellido_paterno, u.apellido_materno) AS usuario_nombre,
    u.perfil_id AS perfil_global_id,
    p.nombre AS perfil_global_nombre,
    u.activo AS usuario_activo,
    u.perfil_completado_en,
    DATE(u.creado_en) AS fecha_alta
FROM usuarios u
JOIN perfiles p ON p.id = u.perfil_id;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_periodo AS
SELECT
    p.id AS periodo_id,
    p.nombre AS periodo_nombre,
    p.fecha_inicio,
    p.fecha_fin,
    YEAR(p.fecha_inicio) AS anio_inicio,
    p.activo AS periodo_activo
FROM periodos_academicos p;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_grupo AS
SELECT
    g.id AS grupo_id,
    g.carrera_id,
    g.periodo_id,
    g.nombre AS grupo_nombre,
    g.semestre,
    g.clave_grupo,
    g.activo AS grupo_activo,
    (g.registro_proyectos_desde IS NOT NULL AND g.registro_proyectos_hasta IS NOT NULL) AS tiene_ventana_proyectos
FROM grupos_academicos g;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_asignatura AS
SELECT
    a.id AS asignatura_id,
    a.carrera_id,
    a.clave AS asignatura_clave,
    a.nombre AS asignatura_nombre,
    a.activo AS asignatura_activa
FROM asignaturas a;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_empresa AS
SELECT
    e.id AS empresa_id,
    e.nombre AS empresa_nombre,
    e.giro AS empresa_giro
FROM empresas e;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_proyecto AS
SELECT
    p.id AS proyecto_id,
    p.carrera_id,
    p.grupo_id,
    g.periodo_id,
    p.empresa_id,
    p.titulo AS proyecto_titulo,
    p.tipo AS proyecto_tipo,
    p.estado AS proyecto_estado,
    p.activo AS proyecto_activo,
    DATE(p.creado_en) AS fecha_creacion,
    DATE(p.revisado_en) AS fecha_revision,
    COALESCE(m.integrantes, 0) AS integrantes,
    COALESCE(m.estudiantes, 0) AS estudiantes,
    COALESCE(m.asesores, 0) AS asesores
FROM proyectos p
JOIN grupos_academicos g ON g.id = p.grupo_id
LEFT JOIN (
    SELECT
        pi.proyecto_id,
        COUNT(*) AS integrantes,
        SUM(pi.rol IN ('lider', 'integrante')) AS estudiantes,
        SUM(pi.rol IN ('asesor', 'primario', 'secundario', 'revisor_1', 'revisor_2')) AS asesores
    FROM proyecto_integrantes pi
    GROUP BY pi.proyecto_id
) m ON m.proyecto_id = p.id;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_dim_sala_evaluacion AS
SELECT
    s.id AS sala_id,
    s.carrera_id,
    s.rubrica_id,
    s.nombre AS sala_nombre,
    s.salon,
    s.estado AS sala_estado,
    s.inicia_en,
    s.termina_en,
    s.minutos_evaluacion_docente,
    s.minutos_presentacion_proyecto,
    s.max_intentos
FROM salas_evaluacion s;

-- Tabla de hechos sin medida: una fila por membresía usuario-carrera.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_usuario_carrera AS
SELECT
    uc.id AS membresia_id,
    uc.usuario_id,
    uc.carrera_id,
    uc.perfil_id,
    uc.es_principal,
    uc.activo AS membresia_activa,
    DATE(uc.creado_en) AS fecha_asignacion
FROM usuario_carrera uc;

-- Una fila por inscripción de estudiante a grupo.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_grupo_estudiante AS
SELECT
    CONCAT(ge.grupo_id, ':', ge.estudiante_id) AS inscripcion_clave,
    ge.grupo_id,
    g.carrera_id,
    g.periodo_id,
    ge.estudiante_id AS usuario_id,
    ge.activo AS inscripcion_activa,
    DATE(ge.inscrito_en) AS fecha_inscripcion
FROM grupo_estudiantes ge
JOIN grupos_academicos g ON g.id = ge.grupo_id;

-- Una fila por materia abierta dentro de un grupo y periodo.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_curso AS
SELECT
    c.id AS curso_id,
    c.carrera_id,
    c.grupo_id,
    g.periodo_id,
    c.asignatura_id,
    c.activo AS curso_activo,
    DATE(c.creado_en) AS fecha_apertura,
    COALESCE(cd.docentes, 0) AS docentes_asignados,
    COALESCE(ge.estudiantes, 0) AS estudiantes_inscritos
FROM cursos c
JOIN grupos_academicos g ON g.id = c.grupo_id
LEFT JOIN (
    SELECT curso_id, COUNT(*) AS docentes
    FROM curso_docentes
    WHERE activo = 1
    GROUP BY curso_id
) cd ON cd.curso_id = c.id
LEFT JOIN (
    SELECT grupo_id, COUNT(*) AS estudiantes
    FROM grupo_estudiantes
    WHERE activo = 1
    GROUP BY grupo_id
) ge ON ge.grupo_id = c.grupo_id;

-- Una fila por persona participante en un proyecto.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_proyecto_integrante AS
SELECT
    CONCAT(pi.proyecto_id, ':', pi.usuario_id) AS participacion_clave,
    pi.proyecto_id,
    p.carrera_id,
    p.grupo_id,
    g.periodo_id,
    pi.usuario_id,
    pi.rol AS rol_proyecto,
    DATE(pi.agregado_en) AS fecha_asignacion
FROM proyecto_integrantes pi
JOIN proyectos p ON p.id = pi.proyecto_id
JOIN grupos_academicos g ON g.id = p.grupo_id;

-- Una fila por evaluación. Los dictámenes y respuestas están previamente
-- agregados para impedir el producto cartesiano docente x criterio.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_evaluacion AS
SELECT
    ev.id AS evaluacion_id,
    ev.carrera_id,
    ev.proyecto_id,
    p.grupo_id,
    g.periodo_id,
    ev.sala_id,
    ev.orden_presentacion,
    ev.estado AS evaluacion_estado,
    ev.resultado AS evaluacion_resultado,
    ev.apto_titulacion,
    ev.fecha_exposicion,
    DATE(ev.fecha_exposicion) AS fecha_evaluacion,
    COALESCE(da.dictamenes_esperados, 0) AS dictamenes_esperados,
    COALESCE(da.dictamenes_enviados, 0) AS dictamenes_enviados,
    COALESCE(da.respuestas, 0) AS respuestas,
    da.puntaje_promedio
FROM evaluaciones ev
JOIN proyectos p ON p.id = ev.proyecto_id
JOIN grupos_academicos g ON g.id = p.grupo_id
LEFT JOIN (
    SELECT
        d.evaluacion_id,
        COUNT(*) AS dictamenes_esperados,
        SUM(d.enviado_en IS NOT NULL) AS dictamenes_enviados,
        SUM(COALESCE(ra.respuestas, 0)) AS respuestas,
        AVG(ra.puntaje_promedio) AS puntaje_promedio
    FROM dictamenes_docentes d
    LEFT JOIN (
        SELECT dictamen_id, COUNT(*) AS respuestas, AVG(puntaje) AS puntaje_promedio
        FROM respuestas_evaluacion
        GROUP BY dictamen_id
    ) ra ON ra.dictamen_id = d.id
    GROUP BY d.evaluacion_id
) da ON da.evaluacion_id = ev.id;

-- Una fila por dictamen de un docente en una evaluación.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_dictamen_docente AS
SELECT
    d.id AS dictamen_id,
    d.evaluacion_id,
    ev.carrera_id,
    ev.proyecto_id,
    p.grupo_id,
    g.periodo_id,
    ev.sala_id,
    d.docente_id AS usuario_id,
    d.intentos_realizados,
    (d.enviado_en IS NOT NULL) AS dictamen_enviado,
    d.enviado_en,
    COALESCE(ra.respuestas, 0) AS respuestas,
    ra.puntaje_promedio
FROM dictamenes_docentes d
JOIN evaluaciones ev ON ev.id = d.evaluacion_id
JOIN proyectos p ON p.id = ev.proyecto_id
JOIN grupos_academicos g ON g.id = p.grupo_id
LEFT JOIN (
    SELECT dictamen_id, COUNT(*) AS respuestas, AVG(puntaje) AS puntaje_promedio
    FROM respuestas_evaluacion
    GROUP BY dictamen_id
) ra ON ra.dictamen_id = d.id;

-- Máximo detalle: una fila por respuesta, nunca debe relacionarse con otra
-- tabla de hechos en Power BI; se conecta directamente con dimensiones.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_respuesta_evaluacion AS
SELECT
    r.id AS respuesta_id,
    r.dictamen_id,
    d.evaluacion_id,
    ev.carrera_id,
    ev.proyecto_id,
    p.grupo_id,
    g.periodo_id,
    ev.sala_id,
    d.docente_id AS usuario_id,
    r.criterio_id,
    cr.rubrica_id,
    r.valor,
    r.puntaje,
    cr.puntaje_maximo,
    CASE
        WHEN cr.puntaje_maximo > 0 AND r.puntaje IS NOT NULL
        THEN ROUND((r.puntaje / cr.puntaje_maximo) * 100, 2)
        ELSE NULL
    END AS porcentaje_puntaje,
    DATE(r.creado_en) AS fecha_respuesta
FROM respuestas_evaluacion r
JOIN dictamenes_docentes d ON d.id = r.dictamen_id
JOIN evaluaciones ev ON ev.id = d.evaluacion_id
JOIN proyectos p ON p.id = ev.proyecto_id
JOIN grupos_academicos g ON g.id = p.grupo_id
JOIN criterios_rubrica cr ON cr.id = r.criterio_id;

-- Una fila por documento. Versiones y etiquetas se agregan antes del JOIN.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_documento AS
SELECT
    d.id AS documento_id,
    d.carrera_id,
    d.proyecto_id,
    p.grupo_id,
    g.periodo_id,
    d.autor_usuario_id,
    d.subido_por,
    d.categoria,
    d.visibilidad,
    d.estado AS documento_estado,
    d.activo AS documento_activo,
    DATE(d.creado_en) AS fecha_creacion,
    DATE(d.publicado_en) AS fecha_publicacion,
    COALESCE(v.versiones, 0) AS versiones,
    v.ultima_version,
    v.tamano_total_bytes,
    COALESCE(t.etiquetas, 0) AS etiquetas,
    t.lista_etiquetas
FROM documentos d
LEFT JOIN proyectos p ON p.id = d.proyecto_id
LEFT JOIN grupos_academicos g ON g.id = p.grupo_id
LEFT JOIN (
    SELECT
        documento_id,
        COUNT(*) AS versiones,
        MAX(numero_version) AS ultima_version,
        SUM(COALESCE(tamano_bytes, 0)) AS tamano_total_bytes
    FROM documento_versiones
    GROUP BY documento_id
) v ON v.documento_id = d.id
LEFT JOIN (
    SELECT
        de.documento_id,
        COUNT(*) AS etiquetas,
        GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ' | ') AS lista_etiquetas
    FROM documento_etiquetas de
    JOIN etiquetas e ON e.id = de.etiqueta_id
    GROUP BY de.documento_id
) t ON t.documento_id = d.id;

-- Una fila por entregable configurado; las entregas se resumen antes de unir.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_entregable AS
SELECT
    e.id AS entregable_id,
    e.carrera_id,
    e.curso_id,
    c.grupo_id,
    g.periodo_id,
    c.asignatura_id,
    e.tipo_documento,
    e.estado AS entregable_estado,
    e.fecha_limite,
    e.activo AS entregable_activo,
    COALESCE(a.entregas, 0) AS entregas,
    a.calificacion_promedio,
    COALESCE(a.entregas_calificadas, 0) AS entregas_calificadas
FROM entregables e
JOIN cursos c ON c.id = e.curso_id
JOIN grupos_academicos g ON g.id = c.grupo_id
LEFT JOIN (
    SELECT
        entregable_id,
        COUNT(*) AS entregas,
        AVG(calificacion) AS calificacion_promedio,
        SUM(calificacion IS NOT NULL) AS entregas_calificadas
    FROM entregas
    GROUP BY entregable_id
) a ON a.entregable_id = e.id;

-- Una fila por entrega efectiva de un proyecto.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_fact_entrega AS
SELECT
    en.id AS entrega_id,
    en.entregable_id,
    en.proyecto_id,
    p.carrera_id,
    p.grupo_id,
    g.periodo_id,
    c.asignatura_id,
    en.documento_id,
    en.enviado_por AS usuario_id,
    en.entregado_en,
    en.calificacion,
    (en.entregado_en <= e.fecha_limite) AS entrega_en_tiempo,
    (en.calificacion IS NOT NULL) AS entrega_calificada
FROM entregas en
JOIN entregables e ON e.id = en.entregable_id
JOIN cursos c ON c.id = e.curso_id
JOIN proyectos p ON p.id = en.proyecto_id
JOIN grupos_academicos g ON g.id = p.grupo_id;

-- Control visible de anomalías. Una fila por regla, no por registro operativo.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_control_calidad AS
SELECT 'curso_carrera_grupo' AS regla, 'critica' AS severidad, COUNT(*) AS incidencias
FROM cursos c JOIN grupos_academicos g ON g.id = c.grupo_id WHERE c.carrera_id <> g.carrera_id
UNION ALL
SELECT 'curso_carrera_asignatura', 'critica', COUNT(*)
FROM cursos c JOIN asignaturas a ON a.id = c.asignatura_id WHERE c.carrera_id <> a.carrera_id
UNION ALL
SELECT 'proyecto_carrera_grupo', 'critica', COUNT(*)
FROM proyectos p JOIN grupos_academicos g ON g.id = p.grupo_id WHERE p.carrera_id <> g.carrera_id
UNION ALL
SELECT 'evaluacion_carrera_proyecto', 'critica', COUNT(*)
FROM evaluaciones e JOIN proyectos p ON p.id = e.proyecto_id WHERE e.carrera_id <> p.carrera_id
UNION ALL
SELECT 'evaluacion_carrera_sala', 'critica', COUNT(*)
FROM evaluaciones e JOIN salas_evaluacion s ON s.id = e.sala_id WHERE e.carrera_id <> s.carrera_id
UNION ALL
SELECT 'documento_carrera_proyecto', 'critica', COUNT(*)
FROM documentos d JOIN proyectos p ON p.id = d.proyecto_id WHERE d.carrera_id <> p.carrera_id
UNION ALL
SELECT 'integrante_sin_membresia_activa', 'advertencia', COUNT(*)
FROM proyecto_integrantes pi
JOIN proyectos p ON p.id = pi.proyecto_id
LEFT JOIN usuario_carrera uc ON uc.usuario_id = pi.usuario_id AND uc.carrera_id = p.carrera_id AND uc.activo = 1
WHERE uc.id IS NULL
UNION ALL
SELECT 'estudiante_en_multiples_carreras', 'critica', COUNT(*)
FROM (
    SELECT usuario_id
    FROM usuario_carrera
    WHERE perfil_id = 3 AND activo = 1
    GROUP BY usuario_id
    HAVING COUNT(*) > 1
) duplicados
UNION ALL
SELECT 'proyecto_activo_sin_integrantes', 'advertencia', COUNT(*)
FROM proyectos p
WHERE p.activo = 1
  AND NOT EXISTS (SELECT 1 FROM proyecto_integrantes pi WHERE pi.proyecto_id = p.id)
UNION ALL
SELECT 'criterio_sin_rubrica', 'critica', COUNT(*)
FROM criterios_rubrica cr
LEFT JOIN rubricas r ON r.id = cr.rubrica_id
WHERE r.id IS NULL
UNION ALL
SELECT 'criterio_con_proyecto_inexistente', 'critica', COUNT(*)
FROM criterios_rubrica cr
LEFT JOIN proyectos p ON p.id = cr.proyecto_id
WHERE cr.proyecto_id IS NOT NULL AND p.id IS NULL;

-- Catálogo importable para documentar relaciones unidireccionales 1:*.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW bi_modelo_relaciones AS
SELECT 'bi_dim_carrera' AS dimension_tabla, 'carrera_id' AS dimension_clave, 'bi_fact_usuario_carrera' AS hecho_tabla, 'carrera_id' AS hecho_clave, '1:*' AS cardinalidad, 'dimension_a_hecho' AS filtro
UNION ALL SELECT 'bi_dim_usuario', 'usuario_id', 'bi_fact_usuario_carrera', 'usuario_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_perfil', 'perfil_id', 'bi_fact_usuario_carrera', 'perfil_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_grupo', 'grupo_id', 'bi_fact_grupo_estudiante', 'grupo_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_usuario', 'usuario_id', 'bi_fact_grupo_estudiante', 'usuario_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_asignatura', 'asignatura_id', 'bi_fact_curso', 'asignatura_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_grupo', 'grupo_id', 'bi_fact_curso', 'grupo_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_proyecto', 'proyecto_id', 'bi_fact_proyecto_integrante', 'proyecto_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_usuario', 'usuario_id', 'bi_fact_proyecto_integrante', 'usuario_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_proyecto', 'proyecto_id', 'bi_fact_evaluacion', 'proyecto_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_sala_evaluacion', 'sala_id', 'bi_fact_evaluacion', 'sala_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_usuario', 'usuario_id', 'bi_fact_dictamen_docente', 'usuario_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_proyecto', 'proyecto_id', 'bi_fact_dictamen_docente', 'proyecto_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_usuario', 'usuario_id', 'bi_fact_respuesta_evaluacion', 'usuario_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_proyecto', 'proyecto_id', 'bi_fact_respuesta_evaluacion', 'proyecto_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_proyecto', 'proyecto_id', 'bi_fact_documento', 'proyecto_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_proyecto', 'proyecto_id', 'bi_fact_entrega', 'proyecto_id', '1:*', 'dimension_a_hecho'
UNION ALL SELECT 'bi_dim_asignatura', 'asignatura_id', 'bi_fact_entrega', 'asignatura_id', '1:*', 'dimension_a_hecho';
