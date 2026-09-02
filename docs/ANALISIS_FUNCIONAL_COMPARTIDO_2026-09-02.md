# Análisis funcional compartido del SGPI

## Diagnóstico

El sistema ya cuenta con aislamiento por carrera, perfiles activos por carrera, proyectos, propuestas, grupos, entregables, evaluaciones y repositorio. La API usa Laravel y el cliente principal usa React/Capacitor.

Las brechas encontradas fueron:

1. Cada proyecto creaba una empresa nueva aunque ya existiera; no había RFC, deduplicación ni revisión administrativa.
2. La inscripción académica existente vinculaba alumnos con grupos completos, pero no permitía autoregistro individual a materias de seguimiento.
3. `tipo` mezclaba estados técnicos (propuesta/desarrollo/tesis) y no representaba Dual, Proyecto integrador o Caso integrador.
4. Evaluaciones y entregables podían deshabilitarse por carrera aunque funcionalmente deben ser compartidos.
5. Las rutas de modificación y eliminación de proyectos tenían permisos demasiado amplios.

## Modelo implementado

- `proyectos.modalidad`: `dual`, `proyecto_integrador`, `caso_integrador`.
- `empresas.rfc`: identificador normalizado y único cuando existe.
- Flujo de empresas: `pendiente`, `aprobada`, `rechazada`, con solicitante, revisor, comentario y fechas.
- `cursos.es_seguimiento_proyecto` y una clave de autoregistro almacenada exclusivamente como hash.
- `curso_estudiantes`: matrícula individual, auditable y reversible por curso.
- Evaluaciones y entregables quedan definidos como módulos obligatorios que una carrera no puede desactivar.

## Reglas de negocio

- Un alumno sólo ve empresas aprobadas como sugerencias.
- Una empresa nueva exige nombre, RFC con estructura válida, giro, contacto, cargo y dirección, y genera una solicitud de validación.
- Jefatura, asistencia, coordinación o administración pueden revisar empresas según sus permisos de gestión de proyectos.
- El RFC evita duplicados; los registros históricos sin RFC se conservan y se marcan aprobados para no romper proyectos existentes.
- La clave de una materia nunca se devuelve por API. El docente asignado puede reemplazarla y el alumno sólo puede validarla.
- Cada inscripción queda limitada a la carrera activa por el ámbito global de `cursos`.

## Riesgos y deuda técnica observada

- El esquema productivo provino de scripts SQL y no tenía una tabla de historial de migraciones. Se aplicó sólo la migración nueva; no debe ejecutarse `migrate:fresh` ni una migración global sobre producción.
- La búsqueda de empresas usa coincidencia de nombre/RFC. Conviene añadir normalización fonética y detección de razones sociales similares antes de crecer el catálogo.
- El paquete principal supera 500 kB; conviene separar dependencias del dashboard en chunks.
- La cobertura automática actual valida autenticación, perfiles y seguridad base, pero necesita pruebas de integración específicas para los nuevos flujos con datos de cada perfil.

## Propuestas para la siguiente etapa

1. Expediente de vinculación por empresa: convenio, vigencia, responsable, sectores y evidencia documental.
2. Plantillas distintas de reportes y rúbricas por modalidad.
3. Bitácora Dual semanal con horas, actividades, firma del asesor empresarial y validación docente.
4. Banco de problemáticas para Caso integrador, con asignación anónima y formación de equipos.
5. Indicadores: permanencia, avance por periodo, entregas tardías, aprobación por modalidad y empresas recurrentes.
6. Alertas automáticas por vencimiento, alumnos sin materia, empresas pendientes y proyectos sin asesor.
7. Cierre semestral con congelamiento de evidencias y exportación de expediente completo.
