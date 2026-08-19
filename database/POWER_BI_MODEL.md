# Modelo semántico para Power BI

La base `sgpi_v2_completa` es el modelo transaccional de la API. Sus tablas puente son correctas para escritura, pero no deben conectarse todas entre sí en Power BI porque pueden producir relaciones ambiguas y multiplicación de filas.

El script `database/sql/2026_08_19_create_power_bi_semantic_layer.sql` crea una capa de vistas `bi_dim_*` y `bi_fact_*`. Las vistas no duplican ni modifican datos.

## Reglas de conexión

1. Importar únicamente vistas con prefijo `bi_` para el modelo analítico.
2. Usar relaciones `1:*` desde dimensiones hacia hechos y filtro en una sola dirección.
3. No relacionar una tabla de hechos con otra tabla de hechos.
4. Desactivar la detección automática de relaciones. Campos como `carrera_id`
   dentro de `bi_dim_proyecto` son atributos descriptivos; no deben usarse para
   conectar una dimensión con otra dimensión.
5. Elegir el grano requerido:
   - `bi_fact_evaluacion`: una fila por evaluación, con respuestas agregadas.
   - `bi_fact_dictamen_docente`: una fila por docente y evaluación.
   - `bi_fact_respuesta_evaluacion`: una fila por criterio respondido.
6. No cargar simultáneamente los tres hechos anteriores en una misma visual sin medidas explícitas.
7. Consultar `bi_modelo_relaciones` para conocer las relaciones recomendadas.
8. Supervisar `bi_control_calidad`; las reglas críticas deben permanecer en cero.

## Evitar conteos duplicados

- Contar proyectos con `DISTINCTCOUNT(proyecto_id)` cuando la visual use una tabla de integrantes.
- Contar usuarios con `DISTINCTCOUNT(usuario_id)` en membresías e inscripciones.
- Para documentos, usar `bi_fact_documento`: las etiquetas y versiones ya están agregadas.
- Para evaluaciones institucionales, usar `bi_fact_evaluacion`: los dictámenes y respuestas ya están agregados.

## Seguridad

Las vistas no exponen contraseñas, CURP, teléfono, domicilio ni rutas privadas de archivos. Se recomienda crear en producción un usuario MySQL de solo lectura con permisos `SELECT` exclusivamente sobre vistas `bi_*`.
