# Modelos, campos, relaciones y evolución del esquema

Diccionario extraído de las declaraciones PHP, sin consultar datos de la conexión configurada. `$fillable` describe asignación masiva; no es un inventario de todas las columnas SQL ni implica que el cliente pueda modificarlas. `$casts` describe conversión; no reemplaza el tipo físico. Para los detalles se incluyen modelos completos y declaraciones de migraciones. Las tablas antiguas y nuevas coexisten en el historial del código: no se afirma que todas existan actualmente en la base desplegada.

| Clase | Tabla declarada | Referencia completa |
|---|---|---|
| `AcademicPeriod` | `periodos_academicos` | [AcademicPeriod](referencia/app/Models/AcademicPeriod.md) |
| `ActivityNotification` | `notificaciones` | [ActivityNotification](referencia/app/Models/ActivityNotification.md) |
| `Asignatura` | `asignaturas` | [Asignatura](referencia/app/Models/Asignatura.md) |
| `Avance` | `avances_proyecto` | [Avance](referencia/app/Models/Avance.md) |
| `Career` | `carreras` | [Career](referencia/app/Models/Career.md) |
| `CareerIndicator` | `indicadores_carrera` | [CareerIndicator](referencia/app/Models/CareerIndicator.md) |
| `CareerModule` | `carrera_modulos` | [CareerModule](referencia/app/Models/CareerModule.md) |
| `CareerModuleRecord` | `registros_modulo_carrera` | [CareerModuleRecord](referencia/app/Models/CareerModuleRecord.md) |
| `CareerSetting` | `Sin tabla propia declarada` | [CareerSetting](referencia/app/Models/CareerSetting.md) |
| `Competencia` | `competencias` | [Competencia](referencia/app/Models/Competencia.md) |
| `BelongsToCareer` | `Sin tabla propia declarada` | [BelongsToCareer](referencia/app/Models/Concerns/BelongsToCareer.md) |
| `HasLegacyAliases` | `Sin tabla propia declarada` | [HasLegacyAliases](referencia/app/Models/Concerns/HasLegacyAliases.md) |
| `LegacyAliasBuilder` | `Sin tabla propia declarada` | [LegacyAliasBuilder](referencia/app/Models/Concerns/LegacyAliasBuilder.md) |
| `ScopedToCareerMembership` | `Sin tabla propia declarada` | [ScopedToCareerMembership](referencia/app/Models/Concerns/ScopedToCareerMembership.md) |
| `Course` | `cursos` | [Course](referencia/app/Models/Course.md) |
| `Deliverable` | `entregables` | [Deliverable](referencia/app/Models/Deliverable.md) |
| `Delivery` | `entregas` | [Delivery](referencia/app/Models/Delivery.md) |
| `DocumentTag` | `etiquetas` | [DocumentTag](referencia/app/Models/DocumentTag.md) |
| `DocumentVersion` | `documento_versiones` | [DocumentVersion](referencia/app/Models/DocumentVersion.md) |
| `Empresa` | `empresas` | [Empresa](referencia/app/Models/Empresa.md) |
| `Evaluation` | `evaluaciones` | [Evaluation](referencia/app/Models/Evaluation.md) |
| `EvaluationAttempt` | `dictamenes_docentes` | [EvaluationAttempt](referencia/app/Models/EvaluationAttempt.md) |
| `EvaluationDocumentRelease` | `liberaciones_documentos_evaluacion` | [EvaluationDocumentRelease](referencia/app/Models/EvaluationDocumentRelease.md) |
| `EvaluationRoom` | `salas_evaluacion` | [EvaluationRoom](referencia/app/Models/EvaluationRoom.md) |
| `EvaluationScore` | `respuestas_evaluacion` | [EvaluationScore](referencia/app/Models/EvaluationScore.md) |
| `Feedback` | `feedback` | [Feedback](referencia/app/Models/Feedback.md) |
| `Grafo` | `grafos` | [Grafo](referencia/app/Models/Grafo.md) |
| `EvaluationRoomProjectPivot` | `Sin tabla propia declarada` | [EvaluationRoomProjectPivot](referencia/app/Models/Pivots/EvaluationRoomProjectPivot.md) |
| `ProjectMemberPivot` | `Sin tabla propia declarada` | [ProjectMemberPivot](referencia/app/Models/Pivots/ProjectMemberPivot.md) |
| `Project` | `proyectos` | [Project](referencia/app/Models/Project.md) |
| `ProjectRegistrationWindow` | `ventanas_registro_proyectos` | [ProjectRegistrationWindow](referencia/app/Models/ProjectRegistrationWindow.md) |
| `ProposalReviewException` | `excepciones_revision_propuesta` | [ProposalReviewException](referencia/app/Models/ProposalReviewException.md) |
| `RepositoryDocument` | `documentos` | [RepositoryDocument](referencia/app/Models/RepositoryDocument.md) |
| `RepositoryDocumentAuthor` | `documentos_repositorio_autores` | [RepositoryDocumentAuthor](referencia/app/Models/RepositoryDocumentAuthor.md) |
| `Rubric` | `rubricas` | [Rubric](referencia/app/Models/Rubric.md) |
| `RubricCriterion` | `criterios_rubrica` | [RubricCriterion](referencia/app/Models/RubricCriterion.md) |
| `SemesterPresentationException` | `autorizaciones_excepcionales` | [SemesterPresentationException](referencia/app/Models/SemesterPresentationException.md) |
| `SubjectGroup` | `grupos_academicos` | [SubjectGroup](referencia/app/Models/SubjectGroup.md) |
| `SystemSetting` | `configuraciones_sistema` | [SystemSetting](referencia/app/Models/SystemSetting.md) |
| `TeacherGroupAssignment` | `asignaciones_docentes_grupos` | [TeacherGroupAssignment](referencia/app/Models/TeacherGroupAssignment.md) |
| `User` | `usuarios` | [User](referencia/app/Models/User.md) |
| `UserCareer` | `usuario_carrera` | [UserCareer](referencia/app/Models/UserCareer.md) |
| `UserPhone` | `usuarios_telefonos` | [UserPhone](referencia/app/Models/UserPhone.md) |

## AcademicPeriod

### table

```json
"periodos_academicos"
```

### fillable

```json
[
  "nombre",
  "fecha_inicio",
  "fecha_fin",
  "activo",
  "promocion_automatica",
  "promocion_aplicada_en"
]
```

### casts

```json
{
  "fecha_inicio": "date",
  "fecha_fin": "date",
  "activo": "boolean",
  "promocion_automatica": "boolean",
  "promocion_aplicada_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `subjectGroups`. Véase su definición exacta en la referencia del modelo.


## ActivityNotification

### table

```json
"notificaciones"
```

### fillable

```json
[
  "carrera_id",
  "usuario_id",
  "actor_id",
  "tipo",
  "titulo",
  "mensaje",
  "url",
  "leida_en"
]
```

### casts

```json
{
  "leida_en": "datetime",
  "creada_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `user`, `actor`. Véase su definición exacta en la referencia del modelo.


## Asignatura

### table

```json
"asignaturas"
```

### fillable

```json
[
  "carrera_id",
  "clave",
  "nombre",
  "descripcion",
  "activo"
]
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### casts

```json
[]
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `competencias`, `projects`, `subjectGroups`. Véase su definición exacta en la referencia del modelo.


## Avance

### table

```json
"avances_proyecto"
```

### fillable

```json
[
  "project_id",
  "descripcion",
  "porcentaje",
  "reportado_por"
]
```

### casts

```json
{
  "porcentaje": "decimal:2"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `project`, `reportedBy`. Véase su definición exacta en la referencia del modelo.


## Career

### table

```json
"carreras"
```

### fillable

```json
[
  "clave",
  "slug",
  "nombre",
  "nombre_corto",
  "color_primario",
  "color_secundario",
  "color_acento",
  "lema",
  "logo_ruta",
  "portada_ruta",
  "activa"
]
```

### casts

```json
{
  "activa": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `users`, `memberships`. Véase su definición exacta en la referencia del modelo.


## CareerIndicator

### table

```json
"indicadores_carrera"
```

### fillable

```json
[
  "carrera_id",
  "modulo",
  "clave",
  "nombre",
  "descripcion",
  "unidad",
  "valor_actual",
  "valor_meta",
  "color",
  "icono",
  "activo"
]
```

### casts

```json
{
  "valor_actual": "decimal:2",
  "valor_meta": "decimal:2",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```


## CareerModule

### table

```json
"carrera_modulos"
```

### fillable

```json
[
  "carrera_id",
  "modulo",
  "habilitado",
  "configuracion"
]
```

### casts

```json
{
  "habilitado": "boolean",
  "configuracion": "array"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```


## CareerModuleRecord

### table

```json
"registros_modulo_carrera"
```

### fillable

```json
[
  "carrera_id",
  "modulo",
  "clave",
  "titulo",
  "descripcion",
  "estado",
  "responsable_id",
  "fecha_inicio",
  "fecha_fin",
  "datos",
  "activo"
]
```

### casts

```json
{
  "fecha_inicio": "date",
  "fecha_fin": "date",
  "datos": "array",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `responsible`. Véase su definición exacta en la referencia del modelo.


## CareerSetting


## Competencia

### table

```json
"competencias"
```

### fillable

```json
[
  "nombre",
  "asignatura_id",
  "fecha_inicio",
  "fecha_fin"
]
```

### casts

```json
{
  "fecha_inicio": "date",
  "fecha_fin": "date"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `asignatura`. Véase su definición exacta en la referencia del modelo.


## BelongsToCareer

Relaciones declaradas: `career`. Véase su definición exacta en la referencia del modelo.


## HasLegacyAliases


## LegacyAliasBuilder


## ScopedToCareerMembership


## Course

### table

```json
"cursos"
```

### fillable

```json
[
  "carrera_id",
  "grupo_id",
  "asignatura_id",
  "activo",
  "es_seguimiento_proyecto",
  "clave_autoregistro",
  "clave_actualizada_en",
  "clave_actualizada_por",
  "inscripcion_desde",
  "inscripcion_hasta"
]
```

### hidden

```json
[
  "clave_autoregistro"
]
```

### casts

```json
{
  "inscripcion_desde": "datetime",
  "inscripcion_hasta": "datetime",
  "activo": "boolean",
  "es_seguimiento_proyecto": "boolean",
  "clave_actualizada_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

Relaciones declaradas: `subject`, `group`, `students`, `deliverables`. Véase su definición exacta en la referencia del modelo.


## Deliverable

### table

```json
"entregables"
```

### legacyAliases

```json
{
  "created_at": "creado_en"
}
```

### fillable

```json
[
  "carrera_id",
  "curso_id",
  "nombre",
  "descripcion",
  "tipo_documento",
  "fecha_limite",
  "estado",
  "activo"
]
```

### casts

```json
{
  "activo": "boolean",
  "creado_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `course`, `deliveries`, `projects`, `feedbacks`. Véase su definición exacta en la referencia del modelo.


## Delivery

### table

```json
"entregas"
```

### fillable

```json
[
  "entregable_id",
  "proyecto_id",
  "documento_id",
  "enviado_por",
  "entregado_en",
  "calificacion",
  "comentarios_docente"
]
```

### casts

```json
{
  "entregado_en": "datetime",
  "calificacion": "decimal:2"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `deliverable`, `project`, `document`, `submittedBy`. Véase su definición exacta en la referencia del modelo.


## DocumentTag

### table

```json
"etiquetas"
```

### fillable

```json
[
  "carrera_id",
  "nombre",
  "color",
  "descripcion",
  "activo"
]
```

### casts

```json
{
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `repositoryDocuments`. Véase su definición exacta en la referencia del modelo.


## DocumentVersion

### table

```json
"documento_versiones"
```

### legacyAliases

```json
{
  "document_id": "documento_id",
  "version_number": "numero_version",
  "file_name": "nombre_archivo",
  "original_name": "nombre_original",
  "file_path": "ruta_archivo",
  "disk": "disco",
  "mime_type": "mime_type",
  "size_bytes": "tamano_bytes",
  "checksum": "checksum_sha256",
  "uploaded_by": "subido_por",
  "created_at": "creado_en"
}
```

### fillable

```json
[
  "document_id",
  "version_number",
  "file_name",
  "original_name",
  "file_path",
  "disk",
  "extension",
  "mime_type",
  "size_bytes",
  "checksum",
  "descripcion",
  "uploaded_by",
  "created_at"
]
```

### casts

```json
{
  "numero_version": "integer",
  "tamano_bytes": "integer",
  "creado_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `document`, `uploadedBy`. Véase su definición exacta en la referencia del modelo.


## Empresa

### table

```json
"empresas"
```

### fillable

```json
[
  "nombre",
  "rfc",
  "giro",
  "contacto_nombre",
  "contacto_cargo",
  "direccion",
  "estado_validacion",
  "solicitada_por",
  "validada_por",
  "comentario_validacion",
  "validada_en"
]
```

### casts

```json
{
  "validada_en": "datetime",
  "creada_en": "datetime",
  "actualizada_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `projects`. Véase su definición exacta en la referencia del modelo.


## Evaluation

### table

```json
"evaluaciones"
```

### legacyAliases

```json
{
  "project_id": "proyecto_id",
  "evaluation_room_id": "sala_id",
  "presentation_order": "orden_presentacion",
  "room_feedback": "retroalimentacion",
  "feedback_by": "retroalimentado_por",
  "feedback_at": "retroalimentado_en",
  "finalized_at": "finalizada_en",
  "archived_at": "archivada_en",
  "archived_by": "archivada_por",
  "created_by": "creada_por",
  "created_at": "creada_en",
  "updated_at": "actualizada_en"
}
```

### legacyVirtualColumns

```json
[
  "sala",
  "semestre",
  "etapa",
  "sequence_status"
]
```

### fillable

```json
[
  "criterios_snapshot",
  "modo_puntaje",
  "autorizacion_id",
  "presentacion_autorizacion_id",
  "carrera_id",
  "project_id",
  "evaluation_room_id",
  "semestre",
  "etapa",
  "sala",
  "fecha_exposicion",
  "presentation_order",
  "sequence_status",
  "estado",
  "resultado",
  "room_feedback",
  "feedback_by",
  "feedback_at",
  "finalized_at",
  "archived_at",
  "archived_by",
  "apto_titulacion",
  "created_by"
]
```

### casts

```json
{
  "criterios_snapshot": "array",
  "semestre": "integer",
  "fecha_exposicion": "datetime",
  "retroalimentado_en": "datetime",
  "finalizada_en": "datetime",
  "archivada_en": "datetime",
  "apto_titulacion": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `project`, `room`, `creator`, `scores`, `attempts`. Véase su definición exacta en la referencia del modelo.


## EvaluationAttempt

### table

```json
"dictamenes_docentes"
```

### legacyAliases

```json
{
  "evaluation_id": "evaluacion_id",
  "teacher_id": "docente_id",
  "attempts_count": "intentos_realizados",
  "general_comment": "comentario_general",
  "last_submitted_at": "enviado_en",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### fillable

```json
[
  "evaluation_id",
  "teacher_id",
  "attempts_count",
  "general_comment",
  "apto_titulacion",
  "last_submitted_at"
]
```

### casts

```json
{
  "attempts_count": "integer",
  "apto_titulacion": "boolean",
  "last_submitted_at": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `evaluation`, `teacher`. Véase su definición exacta en la referencia del modelo.


## EvaluationDocumentRelease

### table

```json
"liberaciones_documentos_evaluacion"
```

### fillable

```json
[
  "documento_repositorio_id",
  "alumno_id",
  "liberado",
  "revisado_por",
  "revisado_en"
]
```

### casts

```json
{
  "liberado": "boolean",
  "revisado_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `document`, `student`, `reviewer`. Véase su definición exacta en la referencia del modelo.


## EvaluationRoom

### table

```json
"salas_evaluacion"
```

### legacyAliases

```json
{
  "responsible_teacher_id": "responsable_id",
  "fecha_evaluacion": "inicia_en",
  "fecha_fin_evaluacion": "termina_en",
  "teacher_evaluation_minutes": "minutos_evaluacion_docente",
  "project_presentation_minutes": "minutos_presentacion_proyecto",
  "max_attempts": "max_intentos",
  "sequence_locked": "secuencia_bloqueada",
  "current_order": "orden_actual",
  "sequence_version": "secuencia_version",
  "completed_at": "completada_en",
  "timer_status": "temporizador_estado",
  "timer_order": "temporizador_orden",
  "timer_duration_seconds": "temporizador_duracion_segundos",
  "timer_started_at": "temporizador_iniciado_en",
  "timer_ends_at": "temporizador_finaliza_en",
  "timer_remaining_seconds": "temporizador_restante_segundos",
  "timer_updated_by": "temporizador_actualizado_por",
  "allow_late_evaluations": "permite_evaluacion_fuera_horario",
  "late_evaluation_until": "evaluacion_fuera_horario_hasta",
  "late_evaluation_reason": "motivo_evaluacion_fuera_horario",
  "schedule_updated_by": "horario_actualizado_por",
  "schedule_updated_at": "horario_actualizado_en",
  "created_by": "creado_por",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### legacyVirtualColumns

```json
[
  "semestre",
  "etapa"
]
```

### fillable

```json
[
  "periodo_id",
  "salon_id",
  "carrera_id",
  "rubrica_id",
  "nombre",
  "salon",
  "semestre",
  "etapa",
  "responsible_teacher_id",
  "fecha_evaluacion",
  "fecha_fin_evaluacion",
  "teacher_evaluation_minutes",
  "project_presentation_minutes",
  "max_attempts",
  "estado",
  "sequence_locked",
  "current_order",
  "sequence_version",
  "completed_at",
  "timer_status",
  "timer_order",
  "timer_duration_seconds",
  "timer_started_at",
  "timer_ends_at",
  "timer_remaining_seconds",
  "timer_updated_by",
  "created_by",
  "activo",
  "allow_late_evaluations",
  "late_evaluation_until",
  "late_evaluation_reason",
  "schedule_updated_by",
  "schedule_updated_at"
]
```

### casts

```json
{
  "semestre": "integer",
  "fecha_evaluacion": "datetime",
  "inicia_en": "datetime",
  "fecha_fin_evaluacion": "datetime",
  "termina_en": "datetime",
  "teacher_evaluation_minutes": "integer",
  "minutos_evaluacion_docente": "integer",
  "project_presentation_minutes": "integer",
  "minutos_presentacion_proyecto": "integer",
  "max_attempts": "integer",
  "max_intentos": "integer",
  "sequence_locked": "boolean",
  "secuencia_bloqueada": "boolean",
  "current_order": "integer",
  "orden_actual": "integer",
  "sequence_version": "integer",
  "secuencia_version": "integer",
  "completed_at": "datetime",
  "completada_en": "datetime",
  "timer_order": "integer",
  "temporizador_orden": "integer",
  "timer_duration_seconds": "integer",
  "temporizador_duracion_segundos": "integer",
  "timer_started_at": "datetime",
  "temporizador_iniciado_en": "datetime",
  "timer_ends_at": "datetime",
  "temporizador_finaliza_en": "datetime",
  "timer_remaining_seconds": "integer",
  "temporizador_restante_segundos": "integer",
  "allow_late_evaluations": "boolean",
  "permite_evaluacion_fuera_horario": "boolean",
  "late_evaluation_until": "datetime",
  "evaluacion_fuera_horario_hasta": "datetime",
  "schedule_updated_at": "datetime",
  "horario_actualizado_en": "datetime",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `teachers`, `rubric`, `projects`, `evaluations`, `responsibleTeacher`. Véase su definición exacta en la referencia del modelo.


## EvaluationScore

### table

```json
"respuestas_evaluacion"
```

### legacyAliases

```json
{
  "nivel": "valor",
  "rubric_criterion_id": "criterio_id",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### legacyVirtualColumns

```json
[
  "evaluation_id",
  "teacher_id",
  "criterio"
]
```

### appends

```json
[
  "evaluation_id",
  "teacher_id",
  "criterio",
  "nivel"
]
```

### fillable

```json
[
  "dictamen_id",
  "criterio_id",
  "valor",
  "nivel",
  "puntaje",
  "comentario"
]
```

### casts

```json
{
  "puntaje": "integer"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### hidden

```json
[]
```

Relaciones declaradas: `evaluation`, `teacher`, `attempt`, `criterion`. Véase su definición exacta en la referencia del modelo.


## Feedback

### table

```json
"feedback"
```

### fillable

```json
[
  "deliverable_id",
  "comentario",
  "comentado_por",
  "estado"
]
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### casts

```json
[]
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `deliverable`, `commentedBy`. Véase su definición exacta en la referencia del modelo.


## Grafo

### table

```json
"grafos"
```

### fillable

```json
[
  "project_id",
  "datos",
  "descripcion"
]
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### casts

```json
[]
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `project`. Véase su definición exacta en la referencia del modelo.


## EvaluationRoomProjectPivot

### incrementing

```json
false
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### casts

```json
[]
```

### appends

```json
[]
```

### hidden

```json
[]
```

### fillable

```json
[]
```


## ProjectMemberPivot

### appends

```json
[
  "rol_asesor"
]
```

### incrementing

```json
false
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### casts

```json
[]
```

### hidden

```json
[]
```

### fillable

```json
[]
```


## Project

### table

```json
"proyectos"
```

### legacyAliases

```json
{
  "title": "titulo",
  "description": "descripcion",
  "created_by": "creado_por",
  "created_at": "creado_en",
  "updated_at": "actualizado_en",
  "subject_group_id": "grupo_id",
  "proposal_status": "estado",
  "proposal_reviewed_by": "revisado_por",
  "proposal_review_comment": "comentario_revision",
  "proposal_reviewed_at": "revisado_en",
  "revision_allowed_until": "revision_permitida_hasta"
}
```

### legacyVirtualColumns

```json
[
  "is_thesis",
  "is_proposal",
  "semestre",
  "year",
  "authors",
  "company_name",
  "company_giro",
  "company_contact_name",
  "company_contact_position",
  "company_address",
  "company_rfc"
]
```

### appends

```json
[
  "title",
  "description",
  "created_by",
  "created_at",
  "updated_at",
  "subject_group_id",
  "is_thesis",
  "is_proposal",
  "proposal_status",
  "proposal_reviewed_by",
  "proposal_review_comment",
  "proposal_reviewed_at",
  "revision_allowed_until",
  "semestre",
  "year",
  "authors",
  "company_name",
  "company_giro",
  "company_contact_name",
  "company_contact_position",
  "company_address",
  "company_rfc"
]
```

### fillable

```json
[
  "career_id",
  "title",
  "description",
  "created_by",
  "activo",
  "tipo",
  "modalidad",
  "is_thesis",
  "is_proposal",
  "subject_group_id",
  "empresa_id",
  "proposal_status",
  "proposal_reviewed_by",
  "proposal_review_comment",
  "proposal_reviewed_at",
  "revision_allowed_until"
]
```

### casts

```json
{
  "activo": "boolean",
  "es_tesis": "boolean",
  "es_propuesta": "boolean",
  "creado_en": "datetime",
  "actualizado_en": "datetime",
  "grupo_id": "integer",
  "revisado_en": "datetime",
  "revision_permitida_hasta": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### hidden

```json
[]
```

Relaciones declaradas: `creator`, `advisors`, `proposalReviewer`, `subjectGroup`, `asignaturas`, `deliverables`, `deliveries`, `repositoryDocuments`, `evaluations`, `avances`, `feedbacks`, `grafos`, `students`, `onlyAdvisors`, `empresa`. Véase su definición exacta en la referencia del modelo.


## ProjectRegistrationWindow

### table

```json
"ventanas_registro_proyectos"
```

### legacyAliases

```json
{
  "subject_group_id": "grupo_academico_id",
  "starts_at": "inicia_en",
  "ends_at": "termina_en",
  "notes": "notas",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### appends

```json
[
  "subject_group_id",
  "starts_at",
  "ends_at",
  "notes"
]
```

### hidden

```json
[
  "grupo_academico_id",
  "inicia_en",
  "termina_en",
  "notas",
  "creado_en",
  "actualizado_en"
]
```

### fillable

```json
[
  "subject_group_id",
  "starts_at",
  "ends_at",
  "activo",
  "notes"
]
```

### casts

```json
{
  "inicia_en": "datetime",
  "termina_en": "datetime",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

Relaciones declaradas: `subjectGroup`. Véase su definición exacta en la referencia del modelo.


## ProposalReviewException

### table

```json
"excepciones_revision_propuesta"
```

### legacyAliases

```json
{
  "subject_group_id": "grupo_academico_id",
  "project_id": "proyecto_id",
  "teacher_id": "docente_id",
  "student_id": "estudiante_id",
  "notes": "motivo",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### fillable

```json
[
  "asignatura_id",
  "subject_group_id",
  "teacher_id",
  "student_id",
  "notes",
  "activo"
]
```

### appends

```json
[
  "subject_group_id",
  "teacher_id",
  "student_id",
  "notes"
]
```

### hidden

```json
[
  "grupo_academico_id",
  "docente_id",
  "estudiante_id",
  "motivo"
]
```

### casts

```json
{
  "asignatura_id": "integer",
  "grupo_academico_id": "integer",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

Relaciones declaradas: `asignatura`, `subjectGroup`, `teacher`, `student`. Véase su definición exacta en la referencia del modelo.


## RepositoryDocument

### table

```json
"documentos"
```

### legacyAliases

```json
{
  "project_id": "proyecto_id",
  "nombre": "titulo",
  "document_category": "categoria",
  "visibility": "visibilidad",
  "published_at": "publicado_en",
  "published_by": "publicado_por",
  "uploaded_by": "subido_por",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### legacyVirtualColumns

```json
[
  "autores",
  "archivo_path",
  "archivo_tipo"
]
```

### appends

```json
[
  "project_id",
  "nombre",
  "document_category",
  "visibility",
  "published_at",
  "published_by",
  "uploaded_by",
  "created_at",
  "updated_at",
  "autores",
  "archivo_path",
  "archivo_tipo",
  "file_available"
]
```

### fillable

```json
[
  "carrera_id",
  "project_id",
  "nombre",
  "descripcion",
  "estado",
  "autores",
  "document_category",
  "visibility",
  "published_at",
  "published_by",
  "uploaded_by",
  "activo"
]
```

### casts

```json
{
  "activo": "boolean",
  "publicado_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### hidden

```json
[]
```

Relaciones declaradas: `tags`, `uploader`, `publisher`, `project`, `authorRecords`, `releaseStatuses`, `latestVersion`, `versions`, `delivery`. Véase su definición exacta en la referencia del modelo.


## RepositoryDocumentAuthor

### table

```json
"documentos_repositorio_autores"
```

### fillable

```json
[
  "documento_repositorio_id",
  "usuario_id",
  "nombre_autor"
]
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### casts

```json
[]
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `document`, `user`. Véase su definición exacta en la referencia del modelo.


## Rubric

### table

```json
"rubricas"
```

### fillable

```json
[
  "carrera_id",
  "nombre",
  "etapa",
  "semestre",
  "activa"
]
```

### casts

```json
{
  "semestre": "integer",
  "activa": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `rooms`. Véase su definición exacta en la referencia del modelo.


## RubricCriterion

### table

```json
"criterios_rubrica"
```

### legacyAliases

```json
{
  "project_id": "proyecto_id",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### fillable

```json
[
  "rubrica_id",
  "semestre",
  "project_id",
  "clave",
  "pregunta",
  "orden",
  "activo"
]
```

### casts

```json
{
  "semestre": "integer",
  "project_id": "integer",
  "orden": "integer",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `project`, `rubric`. Véase su definición exacta en la referencia del modelo.


## SemesterPresentationException

### table

```json
"autorizaciones_excepcionales"
```

### fillable

```json
[
  "proyecto_id",
  "usuario_id",
  "tipo",
  "valor",
  "motivo",
  "vigente_hasta",
  "autorizada_por",
  "activa"
]
```

### appends

```json
[
  "periodo_id",
  "semestre_presentacion",
  "activo"
]
```

### casts

```json
{
  "proyecto_id": "integer",
  "activa": "boolean",
  "vigente_hasta": "datetime",
  "creada_en": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### hidden

```json
[]
```

Relaciones declaradas: `period`, `project`, `student`. Véase su definición exacta en la referencia del modelo.


## SubjectGroup

### table

```json
"grupos_academicos"
```

### legacyAliases

```json
{
  "subject_group_id": "id",
  "grupo": "clave_grupo",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### fillable

```json
[
  "carrera_id",
  "nombre",
  "semestre",
  "grupo",
  "periodo_id",
  "registro_proyectos_desde",
  "registro_proyectos_hasta",
  "activo"
]
```

### appends

```json
[
  "periodo"
]
```

### casts

```json
{
  "semestre": "integer",
  "activo": "boolean",
  "registro_proyectos_desde": "datetime",
  "registro_proyectos_hasta": "datetime"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### hidden

```json
[]
```

Relaciones declaradas: `academicPeriod`, `asignaturas`, `registrationWindows`, `teacherAssignments`, `projects`. Véase su definición exacta en la referencia del modelo.


## SystemSetting

### table

```json
"configuraciones_sistema"
```

### primaryKey

```json
"clave"
```

### incrementing

```json
false
```

### keyType

```json
"string"
```

### legacyAliases

```json
{
  "key": "clave",
  "value": "valor",
  "type": "tipo",
  "description": "descripcion",
  "created_at": "creada_en",
  "updated_at": "actualizada_en"
}
```

### fillable

```json
[
  "key",
  "value",
  "type",
  "description"
]
```

### casts

```json
{
  "valor": "array",
  "valor_booleano": "boolean",
  "valor_decimal": "decimal:6"
}
```

### appends

```json
[]
```

### hidden

```json
[]
```


## TeacherGroupAssignment

### table

```json
"asignaciones_docentes_grupos"
```

### legacyAliases

```json
{
  "subject_group_id": "grupo_academico_id",
  "teacher_id": "docente_id",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### fillable

```json
[
  "subject_group_id",
  "asignatura_id",
  "teacher_id",
  "labor",
  "activo"
]
```

### casts

```json
{
  "activo": "boolean",
  "asignatura_id": "integer"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `subjectGroup`, `asignatura`, `teacher`. Véase su definición exacta en la referencia del modelo.


## User

### table

```json
"usuarios"
```

### incrementing

```json
false
```

### keyType

```json
"string"
```

### legacyAliases

```json
{
  "apa": "apellido_paterno",
  "ama": "apellido_materno",
  "email": "correo",
  "password": "contrasena",
  "photo_path": "foto_ruta",
  "profile_completed_at": "perfil_completado_en",
  "created_at": "creado_en",
  "updated_at": "actualizado_en"
}
```

### legacyVirtualColumns

```json
[
  "semestre",
  "grupo",
  "telefonos"
]
```

### fillable

```json
[
  "id",
  "nombres",
  "apellido_paterno",
  "apellido_materno",
  "correo",
  "contrasena",
  "telefono",
  "curp",
  "direccion",
  "foto_ruta",
  "perfil_completado_en",
  "perfil_id",
  "activo",
  "apa",
  "ama",
  "email",
  "password",
  "photo_path",
  "profile_completed_at"
]
```

### hidden

```json
[
  "contrasena",
  "password",
  "remember_token"
]
```

### casts

```json
{
  "activo": "boolean",
  "email_verified_at": "datetime",
  "contrasena": "hashed",
  "creado_en": "datetime",
  "actualizado_en": "datetime",
  "perfil_id": "integer",
  "semestre": "integer",
  "perfil_completado_en": "datetime"
}
```

### appends

```json
[
  "apa",
  "ama",
  "email",
  "photo_path",
  "profile_completed_at",
  "semestre",
  "grupo",
  "telefonos"
]
```

### primaryKey

```json
"id"
```

Relaciones declaradas: `projectsAsAdvisor`, `teacherGroupAssignments`, `projectsCreated`, `deliveries`, `deliverables`, `feedbacks`, `phoneNumbers`, `careers`, `careerMemberships`. Véase su definición exacta en la referencia del modelo.


## UserCareer

### table

```json
"usuario_carrera"
```

### fillable

```json
[
  "usuario_id",
  "carrera_id",
  "perfil_id",
  "es_principal",
  "activo",
  "asignado_por"
]
```

### casts

```json
{
  "carrera_id": "integer",
  "perfil_id": "integer",
  "es_principal": "boolean",
  "activo": "boolean"
}
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `user`, `career`. Véase su definición exacta en la referencia del modelo.


## UserPhone

### table

```json
"usuarios_telefonos"
```

### fillable

```json
[
  "usuario_id",
  "telefono",
  "tipo"
]
```

### primaryKey

```json
"id"
```

### keyType

```json
"int"
```

### incrementing

```json
true
```

### casts

```json
[]
```

### appends

```json
[]
```

### hidden

```json
[]
```

Relaciones declaradas: `user`. Véase su definición exacta en la referencia del modelo.

## Migraciones: historial completo

62 archivos de migración. Su presencia no acredita aplicación en un servidor. No ejecutar el conjunto histórico indiscriminadamente sobre una importación normalizada.

### 0001_01_01_000000_create_users_table.php

Fuente: [0001_01_01_000000_create_users_table.php](../../database/migrations/0001_01_01_000000_create_users_table.php). Objetos mencionados: `password_reset_tokens`, `sessions`, `users`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // Matrícula/Nómina
            $table->string('password', 255);
            $table->string('nombres', 200);
            $table->string('apa', 100)->nullable(); // Apellido paterno
            $table->string('ama', 100)->nullable(); // Apellido materno
            $table->text('direccion')->nullable();
            $table->string('telefonos', 200)->nullable();
            $table->string('curp', 20)->nullable()->unique();
            $table->string('email', 255)->nullable()->unique();
            $table->tinyInteger('perfil_id')->default(3); // 1=Admin, 2=Teacher, 3=Student
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->index('perfil_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

</details>

### 0001_01_01_000001_create_cache_table.php

Fuente: [0001_01_01_000001_create_cache_table.php](../../database/migrations/0001_01_01_000001_create_cache_table.php). Objetos mencionados: `cache`, `cache_locks`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
```

</details>

### 0001_01_01_000002_create_jobs_table.php

Fuente: [0001_01_01_000002_create_jobs_table.php](../../database/migrations/0001_01_01_000002_create_jobs_table.php). Objetos mencionados: `failed_jobs`, `job_batches`, `jobs`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
```

</details>

### 2026_05_06_200819_create_asignaturas_table.php

Fuente: [2026_05_06_200819_create_asignaturas_table.php](../../database/migrations/2026_05_06_200819_create_asignaturas_table.php). Objetos mencionados: `asignaturas`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->integer('numero_creditos')->nullable();
            $table->string('codigo', 20)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaturas');
    }
};
```

</details>

### 2026_05_06_200819_create_projects_table.php

Fuente: [2026_05_06_200819_create_projects_table.php](../../database/migrations/2026_05_06_200819_create_projects_table.php). Objetos mencionados: `projects`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->index('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

</details>

### 2026_05_06_200820_create_competencias_table.php

Fuente: [2026_05_06_200820_create_competencias_table.php](../../database/migrations/2026_05_06_200820_create_competencias_table.php). Objetos mencionados: `competencias`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('asignatura_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onDelete('set null');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencias');
    }
};
```

</details>

### 2026_05_06_200820_create_deliverables_table.php

Fuente: [2026_05_06_200820_create_deliverables_table.php](../../database/migrations/2026_05_06_200820_create_deliverables_table.php). Objetos mencionados: `deliverables`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('competencia_id')->nullable();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->text('autores')->nullable();
            $table->enum('tipo_documento', ['reporte', 'video', 'presentacion', 'codigo', 'documento', 'otro'])->default('documento');
            $table->string('rama_asociada', 100)->nullable();
            $table->enum('estado', ['pendiente', 'enviado', 'revisado', 'aprobado'])->default('pendiente');
            $table->string('archivo_path')->nullable();
            $table->string('submitted_by', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('competencia_id')->references('id')->on('competencias')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'estado', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
```

</details>

### 2026_05_06_200821_create_document_tags_table.php

Fuente: [2026_05_06_200821_create_document_tags_table.php](../../database/migrations/2026_05_06_200821_create_document_tags_table.php). Objetos mencionados: `document_tags`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_tags', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255)->unique();
            $table->string('color', 7)->default('#1B396A');
            $table->text('descripcion')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_tags');
    }
};
```

</details>

### 2026_05_06_200821_create_document_versions_table.php

Fuente: [2026_05_06_200821_create_document_versions_table.php](../../database/migrations/2026_05_06_200821_create_document_versions_table.php). Objetos mencionados: `document_versions`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deliverable_id');
            $table->integer('version_number');
            $table->text('descripcion')->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('uploaded_by', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('deliverable_id')->references('id')->on('deliverables')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
```

</details>

### 2026_05_06_200822_create_avances_table.php

Fuente: [2026_05_06_200822_create_avances_table.php](../../database/migrations/2026_05_06_200822_create_avances_table.php). Objetos mencionados: `avances`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->text('descripcion')->nullable();
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->string('reportado_por', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('reportado_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avances');
    }
};
```

</details>

### 2026_05_06_200822_create_feedback_table.php

Fuente: [2026_05_06_200822_create_feedback_table.php](../../database/migrations/2026_05_06_200822_create_feedback_table.php). Objetos mencionados: `feedback`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deliverable_id');
            $table->text('comentario')->nullable();
            $table->string('comentado_por', 10)->nullable();
            $table->enum('estado', ['pendiente', 'revisado'])->default('pendiente');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('deliverable_id')->references('id')->on('deliverables')->onDelete('cascade');
            $table->foreign('comentado_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
```

</details>

### 2026_05_06_200823_create_grafos_table.php

Fuente: [2026_05_06_200823_create_grafos_table.php](../../database/migrations/2026_05_06_200823_create_grafos_table.php). Objetos mencionados: `grafos`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grafos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->text('datos')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grafos');
    }
};
```

</details>

### 2026_05_06_201456_create_project_user_table.php

Fuente: [2026_05_06_201456_create_project_user_table.php](../../database/migrations/2026_05_06_201456_create_project_user_table.php). Objetos mencionados: `project_user`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('user_id', 10);
            $table->enum('rol_asesor', ['primario', 'secundario'])->default('secundario');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
```

</details>

### 2026_05_06_201457_create_project_asignatura_table.php

Fuente: [2026_05_06_201457_create_project_asignatura_table.php](../../database/migrations/2026_05_06_201457_create_project_asignatura_table.php). Objetos mencionados: `project_asignatura`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_asignatura', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('asignatura_id');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onDelete('cascade');
            $table->unique(['project_id', 'asignatura_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_asignatura');
    }
};
```

</details>

### 2026_05_06_201458_create_deliverable_document_tag_table.php

Fuente: [2026_05_06_201458_create_deliverable_document_tag_table.php](../../database/migrations/2026_05_06_201458_create_deliverable_document_tag_table.php). Objetos mencionados: `deliverable_document_tag`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deliverable_document_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deliverable_id');
            $table->unsignedBigInteger('document_tag_id');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('deliverable_id')->references('id')->on('deliverables')->onDelete('cascade');
            $table->foreign('document_tag_id')->references('id')->on('document_tags')->onDelete('cascade');
            $table->unique(['deliverable_id', 'document_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverable_document_tag');
    }
};
```

</details>

### 2026_05_07_000001_add_missing_fields_to_projects.php

Fuente: [2026_05_07_000001_add_missing_fields_to_projects.php](../../database/migrations/2026_05_07_000001_add_missing_fields_to_projects.php). Objetos mencionados: `projects`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Agregar campos faltantes documentados
            if (!Schema::hasColumn('projects', 'year')) {
                $table->integer('year')->nullable()->after('description');
            }
            
            if (!Schema::hasColumn('projects', 'file_path')) {
                $table->string('file_path', 255)->nullable()->after('year');
            }
            
            if (!Schema::hasColumn('projects', 'authors')) {
                $table->text('authors')->nullable()->after('file_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'year')) {
                $table->dropColumn('year');
            }
            if (Schema::hasColumn('projects', 'file_path')) {
                $table->dropColumn('file_path');
            }
            if (Schema::hasColumn('projects', 'authors')) {
                $table->dropColumn('authors');
            }
        });
    }
};
```

</details>

### 2026_05_07_000002_add_date_range_to_competencias.php

Fuente: [2026_05_07_000002_add_date_range_to_competencias.php](../../database/migrations/2026_05_07_000002_add_date_range_to_competencias.php). Objetos mencionados: `competencias`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('competencias', function (Blueprint $table) {
            // Agregar campos de fechas para validación de rango
            if (!Schema::hasColumn('competencias', 'fecha_inicio')) {
                $table->date('fecha_inicio')->nullable()->after('descripcion');
            }
            
            if (!Schema::hasColumn('competencias', 'fecha_fin')) {
                $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            }
            
            // Índices para búsquedas de rango
            if (!Schema::hasColumn('competencias', 'fecha_inicio')) {
                $table->index(['fecha_inicio', 'fecha_fin']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competencias', function (Blueprint $table) {
            if (Schema::hasColumn('competencias', 'fecha_inicio')) {
                $table->dropIndex(['fecha_inicio', 'fecha_fin']);
                $table->dropColumn(['fecha_inicio', 'fecha_fin']);
            }
        });
    }
};
```

</details>

### 2026_05_07_000003_add_grading_fields_to_deliverables.php

Fuente: [2026_05_07_000003_add_grading_fields_to_deliverables.php](../../database/migrations/2026_05_07_000003_add_grading_fields_to_deliverables.php). Objetos mencionados: `deliverables`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            // Agregar campos de calificación
            if (!Schema::hasColumn('deliverables', 'calificacion')) {
                $table->decimal('calificacion', 3, 1)->nullable()->after('archivo_path');
                $table->comment('Calificación de 0-100');
            }
            
            if (!Schema::hasColumn('deliverables', 'fecha_calificacion')) {
                $table->dateTime('fecha_calificacion')->nullable()->after('calificacion');
            }
            
            // Agregar campo para identificar quién calificó
            if (!Schema::hasColumn('deliverables', 'calificado_por')) {
                $table->string('calificado_por', 10)->nullable()->after('fecha_calificacion');
                $table->foreign('calificado_por')->references('id')->on('users')->onDelete('set null');
            }
            
            // Índice para búsquedas de estado
            $table->index(['estado', 'calificacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            if (Schema::hasColumn('deliverables', 'calificado_por')) {
                $table->dropForeign(['calificado_por']);
                $table->dropColumn('calificado_por');
            }
            if (Schema::hasColumn('deliverables', 'fecha_calificacion')) {
                $table->dropColumn('fecha_calificacion');
            }
            if (Schema::hasColumn('deliverables', 'calificacion')) {
                $table->dropColumn('calificacion');
            }
            if (Schema::hasIndex('deliverables', 'deliverables_estado_calificacion_index')) {
                $table->dropIndex('deliverables_estado_calificacion_index');
            }
        });
    }
};
```

</details>

### 2026_05_07_add_activo_column.php

Fuente: [2026_05_07_add_activo_column.php](../../database/migrations/2026_05_07_add_activo_column.php). Objetos mencionados: `asignaturas`, `competencias`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar columna 'activo' a competencias si no existe
        if (Schema::hasTable('competencias') && !Schema::hasColumn('competencias', 'activo')) {
            Schema::table('competencias', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('asignatura_id');
                $table->index('activo');
            });
        }

        // Agregar columna 'activo' a asignaturas si no existe
        if (Schema::hasTable('asignaturas') && !Schema::hasColumn('asignaturas', 'activo')) {
            Schema::table('asignaturas', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('codigo');
                $table->index('activo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo eliminar si existen
        if (Schema::hasTable('competencias') && Schema::hasColumn('competencias', 'activo')) {
            Schema::table('competencias', function (Blueprint $table) {
                $table->dropIndex(['activo']);
                $table->dropColumn('activo');
            });
        }

        if (Schema::hasTable('asignaturas') && Schema::hasColumn('asignaturas', 'activo')) {
            Schema::table('asignaturas', function (Blueprint $table) {
                $table->dropIndex(['activo']);
                $table->dropColumn('activo');
            });
        }
    }
};
```

</details>

### 2026_05_08_000001_create_evaluations_table.php

Fuente: [2026_05_08_000001_create_evaluations_table.php](../../database/migrations/2026_05_08_000001_create_evaluations_table.php). Objetos mencionados: `evaluations`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->tinyInteger('semestre');
            $table->enum('etapa', ['propuesta', 'avance', 'titulacion']);
            $table->string('sala', 50)->nullable();
            $table->dateTime('fecha_exposicion')->nullable();
            $table->enum('estado', ['programada', 'en_evaluacion', 'finalizada'])->default('programada');
            $table->enum('resultado', ['pendiente', 'viable', 'no_viable'])->default('pendiente');
            $table->string('created_by', 10)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'semestre', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
```

</details>

### 2026_05_08_000002_create_evaluation_scores_table.php

Fuente: [2026_05_08_000002_create_evaluation_scores_table.php](../../database/migrations/2026_05_08_000002_create_evaluation_scores_table.php). Objetos mencionados: `evaluation_scores`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->string('teacher_id', 10);
            $table->string('criterio', 80);
            $table->enum('nivel', ['nada', 'poco', 'bastante', 'mucho']);
            $table->unsignedTinyInteger('puntaje');
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('evaluation_id')->references('id')->on('evaluations')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['evaluation_id', 'teacher_id', 'criterio'], 'evaluation_teacher_criterio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
```

</details>

### 2026_05_09_000001_create_rubric_criteria_table.php

Fuente: [2026_05_09_000001_create_rubric_criteria_table.php](../../database/migrations/2026_05_09_000001_create_rubric_criteria_table.php). Objetos mencionados: `rubric_criteria`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('semestre');
            $table->string('clave', 80);
            $table->string('pregunta', 255);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['semestre', 'clave']);
        });

        $defaults = [
            5 => [
                'Planteamiento del problema',
                'Justificacion e impacto',
                'Metodologia propuesta',
                'Viabilidad de desarrollo',
                'Exposicion y defensa de la propuesta',
            ],
            6 => [
                'Avance tecnico respecto a las materias',
                'Cumplimiento de entregables',
                'Aplicacion de retroalimentacion previa',
                'Calidad de documentacion',
                'Exposicion y defensa del avance',
            ],
            7 => [
                'Integracion funcional del proyecto',
                'Profundidad tecnica del avance',
                'Validacion de resultados parciales',
                'Calidad de documentacion',
                'Exposicion y defensa del avance',
            ],
            8 => [
                'Madurez tecnica del proyecto',
                'Evidencia de funcionamiento',
                'Impacto y viabilidad para titulacion',
                'Documentacion final',
                'Exposicion y defensa para titulacion',
            ],
        ];

        foreach ($defaults as $semester => $questions) {
            foreach ($questions as $index => $question) {
                DB::table('rubric_criteria')->insert([
                    'semestre' => $semester,
                    'clave' => Str::slug($question, '_'),
                    'pregunta' => $question,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
```

</details>

### 2026_05_09_000002_add_semestre_fields_to_projects.php

Fuente: [2026_05_09_000002_add_semestre_fields_to_projects.php](../../database/migrations/2026_05_09_000002_add_semestre_fields_to_projects.php). Objetos mencionados: `projects`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'semestre')) {
                $table->unsignedTinyInteger('semestre')->nullable()->after('description');
            }
            if (!Schema::hasColumn('projects', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('semestre');
            }
            if (!Schema::hasColumn('projects', 'authors')) {
                $table->text('authors')->nullable()->after('year');
            }
            if (!Schema::hasColumn('projects', 'file_path')) {
                $table->string('file_path')->nullable()->after('authors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'file_path')) $table->dropColumn('file_path');
            if (Schema::hasColumn('projects', 'authors')) $table->dropColumn('authors');
            if (Schema::hasColumn('projects', 'year')) $table->dropColumn('year');
            if (Schema::hasColumn('projects', 'semestre')) $table->dropColumn('semestre');
        });
    }
};
```

</details>

### 2026_05_09_000003_create_subject_groups_table.php

Fuente: [2026_05_09_000003_create_subject_groups_table.php](../../database/migrations/2026_05_09_000003_create_subject_groups_table.php). Objetos mencionados: `projects`, `subject_group_asignatura`, `subject_groups`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subject_groups')) {
            Schema::create('subject_groups', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->unsignedTinyInteger('semestre');
                $table->string('periodo')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['semestre', 'activo']);
            });
        }

        if (!Schema::hasTable('subject_group_asignatura')) {
            Schema::create('subject_group_asignatura', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
                $table->foreignId('asignatura_id')->constrained('asignaturas')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['subject_group_id', 'asignatura_id'], 'subject_group_asignatura_unique');
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'subject_group_id')) {
                $table->foreignId('subject_group_id')->nullable()->after('semestre')->constrained('subject_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'subject_group_id')) {
                $table->dropConstrainedForeignId('subject_group_id');
            }
        });

        Schema::dropIfExists('subject_group_asignatura');
        Schema::dropIfExists('subject_groups');
    }
};
```

</details>

### 2026_05_09_000004_add_company_fields_to_projects.php

Fuente: [2026_05_09_000004_add_company_fields_to_projects.php](../../database/migrations/2026_05_09_000004_add_company_fields_to_projects.php). Objetos mencionados: `projects`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'company_name')) {
                $table->string('company_name')->nullable()->after('authors');
            }
            if (!Schema::hasColumn('projects', 'company_contact_name')) {
                $table->string('company_contact_name')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('projects', 'company_contact_position')) {
                $table->string('company_contact_position')->nullable()->after('company_contact_name');
            }
            if (!Schema::hasColumn('projects', 'company_address')) {
                $table->text('company_address')->nullable()->after('company_contact_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'company_address')) {
                $table->dropColumn('company_address');
            }
            if (Schema::hasColumn('projects', 'company_contact_position')) {
                $table->dropColumn('company_contact_position');
            }
            if (Schema::hasColumn('projects', 'company_contact_name')) {
                $table->dropColumn('company_contact_name');
            }
            if (Schema::hasColumn('projects', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });
    }
};
```

</details>

### 2026_05_10_000001_add_project_proposal_workflow.php

Fuente: [2026_05_10_000001_add_project_proposal_workflow.php](../../database/migrations/2026_05_10_000001_add_project_proposal_workflow.php). Objetos mencionados: `project_registration_windows`, `projects`, `teacher_group_assignments`, `users`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'semestre')) {
                $table->unsignedTinyInteger('semestre')->nullable()->after('perfil_id');
            }
            if (!Schema::hasColumn('users', 'grupo')) {
                $table->string('grupo', 20)->nullable()->after('semestre');
            }
            if (!Schema::hasColumn('users', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('telefonos');
            }
            if (!Schema::hasColumn('users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable()->after('photo_path');
            }
        });

        Schema::create('project_registration_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('activo')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_group_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
            $table->string('teacher_id', 10);
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('labor', 120)->default('Revision de propuesta');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['subject_group_id', 'teacher_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'proposal_status')) {
                $table->string('proposal_status', 30)->default('pendiente')->after('activo');
            }
            if (!Schema::hasColumn('projects', 'proposal_reviewed_by')) {
                $table->string('proposal_reviewed_by', 10)->nullable()->after('proposal_status');
                $table->foreign('proposal_reviewed_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'proposal_review_comment')) {
                $table->text('proposal_review_comment')->nullable()->after('proposal_reviewed_by');
            }
            if (!Schema::hasColumn('projects', 'proposal_reviewed_at')) {
                $table->timestamp('proposal_reviewed_at')->nullable()->after('proposal_review_comment');
            }
            if (!Schema::hasColumn('projects', 'revision_allowed_until')) {
                $table->dateTime('revision_allowed_until')->nullable()->after('proposal_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach (['revision_allowed_until', 'proposal_reviewed_at', 'proposal_review_comment', 'proposal_reviewed_by', 'proposal_status'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('teacher_group_assignments');
        Schema::dropIfExists('project_registration_windows');

        Schema::table('users', function (Blueprint $table) {
            foreach (['profile_completed_at', 'photo_path', 'grupo', 'semestre'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
```

</details>

### 2026_05_10_000002_add_asignatura_to_teacher_group_assignments.php

Fuente: [2026_05_10_000002_add_asignatura_to_teacher_group_assignments.php](../../database/migrations/2026_05_10_000002_add_asignatura_to_teacher_group_assignments.php). Objetos mencionados: `teacher_group_assignments`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_group_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_group_assignments', 'asignatura_id')) {
                $table->foreignId('asignatura_id')->nullable()->after('subject_group_id')->constrained('asignaturas')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_group_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_group_assignments', 'asignatura_id')) {
                $table->dropConstrainedForeignId('asignatura_id');
            }
        });
    }
};
```

</details>

### 2026_05_12_000001_create_system_settings_table.php

Fuente: [2026_05_12_000001_create_system_settings_table.php](../../database/migrations/2026_05_12_000001_create_system_settings_table.php). Objetos mencionados: `system_settings`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
```

</details>

### 2026_05_12_000002_add_group_code_to_subject_groups.php

Fuente: [2026_05_12_000002_add_group_code_to_subject_groups.php](../../database/migrations/2026_05_12_000002_add_group_code_to_subject_groups.php). Objetos mencionados: `subject_groups`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('subject_groups', 'grupo')) {
                $table->string('grupo', 20)->nullable()->after('semestre');
                $table->index(['semestre', 'grupo', 'activo']);
            }
        });

        DB::table('subject_groups')
            ->whereNull('grupo')
            ->orderBy('id')
            ->get()
            ->each(function ($group) {
                preg_match('/\b([A-Z])\b/i', (string) $group->nombre, $matches);
                DB::table('subject_groups')
                    ->where('id', $group->id)
                    ->update(['grupo' => strtoupper($matches[1] ?? 'A')]);
            });
    }

    public function down(): void
    {
        Schema::table('subject_groups', function (Blueprint $table) {
            if (Schema::hasColumn('subject_groups', 'grupo')) {
                $table->dropIndex(['semestre', 'grupo', 'activo']);
                $table->dropColumn('grupo');
            }
        });
    }
};
```

</details>

### 2026_05_12_000003_create_evaluation_rooms.php

Fuente: [2026_05_12_000003_create_evaluation_rooms.php](../../database/migrations/2026_05_12_000003_create_evaluation_rooms.php). Objetos mencionados: `evaluation_attempts`, `evaluation_room_project`, `evaluation_room_teacher`, `evaluation_rooms`, `evaluations`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('salon', 120)->nullable();
            $table->unsignedTinyInteger('semestre');
            $table->dateTime('fecha_evaluacion')->nullable();
            $table->unsignedSmallInteger('teacher_evaluation_minutes')->default(15);
            $table->unsignedSmallInteger('project_presentation_minutes')->default(20);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['semestre', 'activo']);
        });

        Schema::create('evaluation_room_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_room_id')->constrained('evaluation_rooms')->cascadeOnDelete();
            $table->string('teacher_id', 10);
            $table->timestamps();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['evaluation_room_id', 'teacher_id'], 'room_teacher_unique');
        });

        Schema::create('evaluation_room_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_room_id')->constrained('evaluation_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['evaluation_room_id', 'project_id'], 'room_project_unique');
        });

        Schema::create('evaluation_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->string('teacher_id', 10);
            $table->unsignedTinyInteger('attempts_count')->default(0);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['evaluation_id', 'teacher_id'], 'evaluation_attempt_teacher_unique');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'evaluation_room_id')) {
                $table->foreignId('evaluation_room_id')->nullable()->after('project_id')->constrained('evaluation_rooms')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'evaluation_room_id')) {
                $table->dropConstrainedForeignId('evaluation_room_id');
            }
        });
        Schema::dropIfExists('evaluation_attempts');
        Schema::dropIfExists('evaluation_room_project');
        Schema::dropIfExists('evaluation_room_teacher');
        Schema::dropIfExists('evaluation_rooms');
    }
};
```

</details>

### 2026_05_13_000001_create_repository_documents_table.php

Fuente: [2026_05_13_000001_create_repository_documents_table.php](../../database/migrations/2026_05_13_000001_create_repository_documents_table.php). Objetos mencionados: `repository_document_tag`, `repository_documents`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_documents', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->text('autores')->nullable();
            $table->string('archivo_path');
            $table->string('archivo_tipo', 20);
            $table->string('uploaded_by', 10)->nullable();
            $table->timestamps();
            $table->boolean('activo')->default(true);

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['activo', 'created_at']);
        });

        Schema::create('repository_document_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repository_document_id');
            $table->unsignedBigInteger('document_tag_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('repository_document_id')->references('id')->on('repository_documents')->onDelete('cascade');
            $table->foreign('document_tag_id')->references('id')->on('document_tags')->onDelete('cascade');
            $table->unique(['repository_document_id', 'document_tag_id'], 'repo_document_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_document_tag');
        Schema::dropIfExists('repository_documents');
    }
};
```

</details>

### 2026_05_14_000001_add_cp3_fields.php

Fuente: [2026_05_14_000001_add_cp3_fields.php](../../database/migrations/2026_05_14_000001_add_cp3_fields.php). Objetos mencionados: `evaluations`, `projects`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'company_giro')) {
                $table->string('company_giro')->nullable()->after('company_name');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'apto_titulacion')) {
                $table->boolean('apto_titulacion')->nullable()->after('resultado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'apto_titulacion')) {
                $table->dropColumn('apto_titulacion');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'company_giro')) {
                $table->dropColumn('company_giro');
            }
        });
    }
};
```

</details>

### 2026_05_14_000002_add_performance_indexes.php

Fuente: [2026_05_14_000002_add_performance_indexes.php](../../database/migrations/2026_05_14_000002_add_performance_indexes.php). Objetos mencionados: `evaluation_room_project`, `evaluation_room_teacher`, `evaluation_rooms`, `project_user`, `projects`, `users`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['activo', 'perfil_id'], 'users_activo_perfil_idx');
            $table->index(['perfil_id', 'semestre', 'grupo', 'activo'], 'users_student_group_idx');
            $table->index(['activo', 'perfil_id', 'nombres'], 'users_listing_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index(['activo', 'semestre', 'created_at'], 'projects_active_semester_created_idx');
            $table->index(['subject_group_id', 'activo'], 'projects_subject_active_idx');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->index(['user_id', 'rol_asesor', 'project_id'], 'project_user_user_role_project_idx');
            $table->index(['project_id', 'rol_asesor', 'user_id'], 'project_user_project_role_user_idx');
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            $table->index(['activo', 'fecha_evaluacion'], 'rooms_active_date_idx');
            $table->index(['activo', 'nombre'], 'rooms_active_name_idx');
        });

        Schema::table('evaluation_room_teacher', function (Blueprint $table) {
            $table->index(['teacher_id', 'evaluation_room_id'], 'room_teacher_teacher_room_idx');
        });

        Schema::table('evaluation_room_project', function (Blueprint $table) {
            $table->index(['project_id', 'evaluation_room_id'], 'room_project_project_room_idx');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_room_project', function (Blueprint $table) {
            $table->dropIndex('room_project_project_room_idx');
        });

        Schema::table('evaluation_room_teacher', function (Blueprint $table) {
            $table->dropIndex('room_teacher_teacher_room_idx');
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_active_date_idx');
            $table->dropIndex('rooms_active_name_idx');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropIndex('project_user_user_role_project_idx');
            $table->dropIndex('project_user_project_role_user_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_active_semester_created_idx');
            $table->dropIndex('projects_subject_active_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_activo_perfil_idx');
            $table->dropIndex('users_student_group_idx');
            $table->dropIndex('users_listing_idx');
        });
    }
};
```

</details>

### 2026_05_14_000003_make_deliverable_project_nullable.php

Fuente: [2026_05_14_000003_make_deliverable_project_nullable.php](../../database/migrations/2026_05_14_000003_make_deliverable_project_nullable.php). Objetos mencionados: `deliverables`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }
};
```

</details>

### 2026_05_16_000001_extend_proposal_and_evaluation_workflows.php

Fuente: [2026_05_16_000001_extend_proposal_and_evaluation_workflows.php](../../database/migrations/2026_05_16_000001_extend_proposal_and_evaluation_workflows.php). Objetos mencionados: `evaluation_attempts`, `evaluation_room_project`, `evaluation_rooms`, `evaluations`, `proposal_review_exceptions`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_review_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->cascadeOnDelete();
            $table->foreignId('subject_group_id')->nullable()->constrained('subject_groups')->nullOnDelete();
            $table->string('teacher_id', 10);
            $table->string('student_id', 10);
            $table->text('notes')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['asignatura_id', 'teacher_id', 'student_id'], 'proposal_exception_unique');
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_rooms', 'responsible_teacher_id')) {
                $table->string('responsible_teacher_id', 10)->nullable()->after('semestre');
                $table->foreign('responsible_teacher_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('evaluation_rooms', 'sequence_locked')) {
                $table->boolean('sequence_locked')->default(false)->after('max_attempts');
            }
            if (!Schema::hasColumn('evaluation_rooms', 'current_order')) {
                $table->unsignedSmallInteger('current_order')->nullable()->after('sequence_locked');
            }
            if (!Schema::hasColumn('evaluation_rooms', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('current_order');
            }
        });

        Schema::table('evaluation_room_project', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_room_project', 'presentation_order')) {
                $table->unsignedSmallInteger('presentation_order')->default(0)->after('project_id');
            }
            if (!Schema::hasColumn('evaluation_room_project', 'status')) {
                $table->string('status', 30)->default('pendiente')->after('presentation_order');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'presentation_order')) {
                $table->unsignedSmallInteger('presentation_order')->default(0)->after('fecha_exposicion');
            }
            if (!Schema::hasColumn('evaluations', 'sequence_status')) {
                $table->string('sequence_status', 30)->default('pendiente')->after('presentation_order');
            }
            if (!Schema::hasColumn('evaluations', 'room_feedback')) {
                $table->text('room_feedback')->nullable()->after('resultado');
            }
            if (!Schema::hasColumn('evaluations', 'feedback_by')) {
                $table->string('feedback_by', 10)->nullable()->after('room_feedback');
                $table->foreign('feedback_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('evaluations', 'feedback_at')) {
                $table->timestamp('feedback_at')->nullable()->after('feedback_by');
            }
            if (!Schema::hasColumn('evaluations', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('feedback_at');
            }
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_attempts', 'general_comment')) {
                $table->text('general_comment')->nullable()->after('attempts_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_attempts', 'general_comment')) {
                $table->dropColumn('general_comment');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            foreach (['finalized_at', 'feedback_at', 'feedback_by', 'room_feedback', 'sequence_status', 'presentation_order'] as $column) {
                if (Schema::hasColumn('evaluations', $column)) {
                    if ($column === 'feedback_by') {
                        $table->dropForeign(['feedback_by']);
                    }
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('evaluation_room_project', function (Blueprint $table) {
            foreach (['status', 'presentation_order'] as $column) {
                if (Schema::hasColumn('evaluation_room_project', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            foreach (['completed_at', 'current_order', 'sequence_locked', 'responsible_teacher_id'] as $column) {
                if (Schema::hasColumn('evaluation_rooms', $column)) {
                    if ($column === 'responsible_teacher_id') {
                        $table->dropForeign(['responsible_teacher_id']);
                    }
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('proposal_review_exceptions');
    }
};
```

</details>

### 2026_05_19_000001_add_categoria_to_deliverables_table.php

Fuente: [2026_05_19_000001_add_categoria_to_deliverables_table.php](../../database/migrations/2026_05_19_000001_add_categoria_to_deliverables_table.php). Objetos mencionados: `deliverables`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverables', 'categoria')) {
                $table->string('categoria', 60)->default('materia')->after('competencia_id');
                $table->index(['project_id', 'categoria'], 'deliverables_project_categoria_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            if (Schema::hasColumn('deliverables', 'categoria')) {
                $table->dropIndex('deliverables_project_categoria_idx');
                $table->dropColumn('categoria');
            }
        });
    }
};
```

</details>

### 2026_05_19_000001_make_project_user_role_nullable.php

Fuente: [2026_05_19_000001_make_project_user_role_nullable.php](../../database/migrations/2026_05_19_000001_make_project_user_role_nullable.php). Objetos mencionados: `project_user`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE project_user SET rol_asesor = 'secundario' WHERE rol_asesor IS NULL");
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NOT NULL DEFAULT 'secundario'");
        }
    }
};
```

</details>

### 2026_05_19_000002_add_realtime_performance_indexes.php

Fuente: [2026_05_19_000002_add_realtime_performance_indexes.php](../../database/migrations/2026_05_19_000002_add_realtime_performance_indexes.php). Objetos mencionados: `deliverables`, `evaluation_attempts`, `evaluations`, `repository_document_tag`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->index(['evaluation_room_id', 'presentation_order', 'sequence_status'], 'evaluations_room_order_status_idx');
            $table->index(['estado', 'updated_at'], 'evaluations_estado_updated_idx');
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->index(['evaluation_id', 'last_submitted_at'], 'evaluation_attempts_eval_submitted_idx');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->index(['competencia_id', 'estado', 'activo'], 'deliverables_competence_status_idx');
            $table->index(['submitted_by', 'activo'], 'deliverables_submitter_active_idx');
        });

        Schema::table('repository_document_tag', function (Blueprint $table) {
            $table->index(['document_tag_id', 'repository_document_id'], 'repo_tag_document_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repository_document_tag', function (Blueprint $table) {
            $table->dropIndex('repo_tag_document_idx');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropIndex('deliverables_submitter_active_idx');
            $table->dropIndex('deliverables_competence_status_idx');
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->dropIndex('evaluation_attempts_eval_submitted_idx');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_estado_updated_idx');
            $table->dropIndex('evaluations_room_order_status_idx');
        });
    }
};
```

</details>

### 2026_05_19_000002_update_evaluation_score_levels.php

Fuente: [2026_05_19_000002_update_evaluation_score_levels.php](../../database/migrations/2026_05_19_000002_update_evaluation_score_levels.php). Objetos mencionados: `evaluation_scores`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }

        // Keep historical rubric answers as they were captured. New evaluations may
        // use the agreement scale, but old answers must remain readable.
    }

    public function down(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }
    }
};
```

</details>

### 2026_05_21_000003_restore_legacy_rubric_score_levels.php

Fuente: [2026_05_21_000003_restore_legacy_rubric_score_levels.php](../../database/migrations/2026_05_21_000003_restore_legacy_rubric_score_levels.php). Objetos mencionados: `evaluation_scores`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }

        $legacyCutoff = now();

        DB::table('evaluation_scores')
            ->where('created_at', '<', $legacyCutoff)
            ->where('nivel', 'totalmente_de_acuerdo')
            ->where('puntaje', 4)
            ->update(['nivel' => 'mucho', 'puntaje' => 3]);

        DB::table('evaluation_scores')
            ->where('created_at', '<', $legacyCutoff)
            ->where('nivel', 'de_acuerdo')
            ->where('puntaje', 3)
            ->update(['nivel' => 'bastante', 'puntaje' => 2]);

        DB::table('evaluation_scores')
            ->where('created_at', '<', $legacyCutoff)
            ->where('nivel', 'en_desacuerdo')
            ->where('puntaje', 1)
            ->update(['nivel' => 'poco']);

        DB::table('evaluation_scores')
            ->where('created_at', '<', $legacyCutoff)
            ->where('nivel', 'totalmente_en_desacuerdo')
            ->where('puntaje', 0)
            ->update(['nivel' => 'nada']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }
    }
};
```

</details>

### 2026_05_21_000004_restore_existing_scores_to_legacy_scale.php

Fuente: [2026_05_21_000004_restore_existing_scores_to_legacy_scale.php](../../database/migrations/2026_05_21_000004_restore_existing_scores_to_legacy_scale.php). Objetos mencionados: `evaluation_scores`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }

        $legacyCutoff = now();

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'totalmente_de_acuerdo')
            ->where('puntaje', 4)
            ->update(['nivel' => 'mucho', 'puntaje' => 3]);

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'de_acuerdo')
            ->where('puntaje', 3)
            ->update(['nivel' => 'bastante', 'puntaje' => 2]);

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'en_desacuerdo')
            ->where('puntaje', 1)
            ->update(['nivel' => 'poco']);

        DB::table('evaluation_scores')
            ->where('created_at', '<=', $legacyCutoff)
            ->where('nivel', 'totalmente_en_desacuerdo')
            ->where('puntaje', 0)
            ->update(['nivel' => 'nada']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('evaluation_scores')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evaluation_scores MODIFY nivel ENUM('nada','poco','bastante','mucho','totalmente_de_acuerdo','de_acuerdo','neutral','en_desacuerdo','totalmente_en_desacuerdo') NOT NULL");
        }
    }
};
```

</details>

### 2026_05_21_000005_add_project_scope_to_rubric_criteria.php

Fuente: [2026_05_21_000005_add_project_scope_to_rubric_criteria.php](../../database/migrations/2026_05_21_000005_add_project_scope_to_rubric_criteria.php). Objetos mencionados: `rubric_criteria`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('semestre');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->index(['semestre', 'project_id', 'activo'], 'rubric_criteria_sem_project_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex('rubric_criteria_sem_project_active_idx');
            $table->dropColumn('project_id');
        });
    }
};
```

</details>

### 2026_05_25_000001_extend_repository_documents_visibility.php

Fuente: [2026_05_25_000001_extend_repository_documents_visibility.php](../../database/migrations/2026_05_25_000001_extend_repository_documents_visibility.php). Objetos mencionados: `repository_documents`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('repository_documents', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('repository_documents', 'document_category')) {
                $table->string('document_category', 60)->default('repository')->after('archivo_tipo');
            }
            if (!Schema::hasColumn('repository_documents', 'visibility')) {
                $table->string('visibility', 20)->default('public')->after('document_category');
            }
            if (!Schema::hasColumn('repository_documents', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('visibility');
            }
            if (!Schema::hasColumn('repository_documents', 'published_by')) {
                $table->string('published_by', 10)->nullable()->after('published_at');
            }
        });

        Schema::table('repository_documents', function (Blueprint $table) {
            if (
                Schema::hasColumn('repository_documents', 'document_category')
                && Schema::hasColumn('repository_documents', 'visibility')
                && Schema::hasColumn('repository_documents', 'activo')
                && !$this->indexExists('repository_documents', 'repo_docs_category_visibility_idx')
            ) {
                $table->index(['document_category', 'visibility', 'activo'], 'repo_docs_category_visibility_idx');
            }
        });

        Schema::table('repository_documents', function (Blueprint $table) {
            if (
                Schema::hasColumn('repository_documents', 'project_id')
                && !$this->foreignKeyExists('repository_documents', 'repository_documents_project_id_foreign')
            ) {
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            }
            if (
                Schema::hasColumn('repository_documents', 'published_by')
                && !$this->foreignKeyExists('repository_documents', 'repository_documents_published_by_foreign')
            ) {
                $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            if (Schema::hasColumn('repository_documents', 'published_by')) {
                $table->dropForeign(['published_by']);
            }
            if (Schema::hasColumn('repository_documents', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropIndex('repo_docs_category_visibility_idx');
            }
        });

        Schema::table('repository_documents', function (Blueprint $table) {
            foreach (['published_by', 'published_at', 'visibility', 'document_category', 'project_id'] as $column) {
                if (Schema::hasColumn('repository_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index]
        ));
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return !empty(DB::select(
            'select 1 from information_schema.table_constraints where constraint_schema = ? and table_name = ? and constraint_name = ? and constraint_type = ? limit 1',
            [$database, $table, $constraint, 'FOREIGN KEY']
        ));
    }
};
```

</details>

### 2026_05_25_000002_add_archive_fields_to_evaluations.php

Fuente: [2026_05_25_000002_add_archive_fields_to_evaluations.php](../../database/migrations/2026_05_25_000002_add_archive_fields_to_evaluations.php). Objetos mencionados: `evaluations`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('finalized_at');
            }
            if (!Schema::hasColumn('evaluations', 'archived_by')) {
                $table->string('archived_by', 10)->nullable()->after('archived_at');
                $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
            }
            if (
                Schema::hasColumn('evaluations', 'archived_at')
                && !$this->indexExists('evaluations', 'evaluations_archived_at_idx')
            ) {
                $table->index('archived_at', 'evaluations_archived_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'archived_by')) {
                $table->dropForeign(['archived_by']);
                $table->dropColumn('archived_by');
            }
            if (Schema::hasColumn('evaluations', 'archived_at')) {
                if ($this->indexExists('evaluations', 'evaluations_archived_at_idx')) {
                    $table->dropIndex('evaluations_archived_at_idx');
                }
                $table->dropColumn('archived_at');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index]
        ));
    }
};
```

</details>

### 2026_05_25_000003_add_end_time_to_evaluation_rooms.php

Fuente: [2026_05_25_000003_add_end_time_to_evaluation_rooms.php](../../database/migrations/2026_05_25_000003_add_end_time_to_evaluation_rooms.php). Objetos mencionados: `evaluation_rooms`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_rooms', 'fecha_fin_evaluacion')) {
                $table->dateTime('fecha_fin_evaluacion')->nullable()->after('fecha_evaluacion');
            }
        });

        if (Schema::hasColumn('evaluation_rooms', 'fecha_fin_evaluacion')) {
            DB::statement("
                UPDATE evaluation_rooms
                SET fecha_fin_evaluacion = DATE_ADD(fecha_evaluacion, INTERVAL 1 HOUR)
                WHERE fecha_evaluacion IS NOT NULL
                  AND fecha_fin_evaluacion IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_rooms', 'fecha_fin_evaluacion')) {
                $table->dropColumn('fecha_fin_evaluacion');
            }
        });
    }
};
```

</details>

### 2026_05_26_000001_add_titulation_vote_to_evaluation_attempts.php

Fuente: [2026_05_26_000001_add_titulation_vote_to_evaluation_attempts.php](../../database/migrations/2026_05_26_000001_add_titulation_vote_to_evaluation_attempts.php). Objetos mencionados: `evaluation_attempts`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_attempts', 'apto_titulacion')) {
                $column = $table->boolean('apto_titulacion')->nullable();
                if (Schema::hasColumn('evaluation_attempts', 'general_comment')) {
                    $column->after('general_comment');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_attempts', 'apto_titulacion')) {
                $table->dropColumn('apto_titulacion');
            }
        });
    }
};
```

</details>

### 2026_06_01_000001_update_project_user_thesis_roles.php

Fuente: [2026_06_01_000001_update_project_user_thesis_roles.php](../../database/migrations/2026_06_01_000001_update_project_user_thesis_roles.php). Objetos mencionados: `project_user`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2','primario','secundario') NULL DEFAULT NULL");
        DB::table('project_user')->where('rol_asesor', 'primario')->update(['rol_asesor' => 'asesor']);
        DB::table('project_user')->where('rol_asesor', 'secundario')->update(['rol_asesor' => 'revisor_1']);
        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_user') || !Schema::hasColumn('project_user', 'rol_asesor')) {
            return;
        }

        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2','primario','secundario') NULL DEFAULT NULL");
        DB::table('project_user')->where('rol_asesor', 'asesor')->update(['rol_asesor' => 'primario']);
        DB::table('project_user')->where('rol_asesor', 'revisor_1')->update(['rol_asesor' => 'secundario']);
        DB::table('project_user')->where('rol_asesor', 'revisor_2')->update(['rol_asesor' => null]);
        DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NULL DEFAULT NULL");
    }
};
```

</details>

### 2026_06_01_000002_add_thesis_flag_and_restore_advisor_roles.php

Fuente: [2026_06_01_000002_add_thesis_flag_and_restore_advisor_roles.php](../../database/migrations/2026_06_01_000002_add_thesis_flag_and_restore_advisor_roles.php). Objetos mencionados: `project_user`, `projects`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'is_thesis')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->boolean('is_thesis')->default(false)->after('activo')->index();
            });
        }

        if (Schema::hasTable('project_user') && Schema::hasColumn('project_user', 'rol_asesor')) {
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario','asesor','revisor_1','revisor_2') NULL DEFAULT NULL");
            DB::table('project_user')->where('rol_asesor', 'asesor')->update(['rol_asesor' => 'primario']);
            DB::table('project_user')->where('rol_asesor', 'revisor_1')->update(['rol_asesor' => 'secundario']);
            DB::table('project_user')->where('rol_asesor', 'revisor_2')->update(['rol_asesor' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'is_thesis')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('is_thesis');
            });
        }

        if (Schema::hasTable('project_user') && Schema::hasColumn('project_user', 'rol_asesor')) {
            DB::statement("ALTER TABLE project_user MODIFY rol_asesor ENUM('primario','secundario') NULL DEFAULT NULL");
        }
    }
};
```

</details>

### 2026_06_04_000001_normalize_domain_table_names.php

Fuente: [2026_06_04_000001_normalize_domain_table_names.php](../../database/migrations/2026_06_04_000001_normalize_domain_table_names.php). Objetos mencionados: .

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users' => 'usuarios',
        'projects' => 'proyectos',
        'project_user' => 'proyectos_integrantes',
        'project_asignatura' => 'proyectos_asignaturas',
        'avances' => 'avances_proyecto',
        'deliverables' => 'entregables_proyecto',
        'document_tags' => 'etiquetas_documentos',
        'deliverable_document_tag' => 'entregables_etiquetas',
        'document_versions' => 'versiones_documentos',
        'repository_documents' => 'documentos_repositorio',
        'repository_document_tag' => 'documentos_repositorio_etiquetas',
        'subject_groups' => 'grupos_academicos',
        'subject_group_asignatura' => 'grupos_asignaturas',
        'teacher_group_assignments' => 'asignaciones_docentes_grupos',
        'project_registration_windows' => 'ventanas_registro_proyectos',
        'evaluation_rooms' => 'salas_evaluacion',
        'evaluation_room_project' => 'salas_evaluacion_proyectos',
        'evaluation_room_teacher' => 'salas_evaluacion_docentes',
        'evaluations' => 'evaluaciones',
        'evaluation_attempts' => 'intentos_evaluacion',
        'evaluation_scores' => 'respuestas_evaluacion',
        'rubric_criteria' => 'criterios_rubrica',
        'proposal_review_exceptions' => 'excepciones_revision_propuesta',
        'system_settings' => 'configuraciones_sistema',
    ];

    public function up(): void
    {
        foreach ($this->tables as $current => $normalized) {
            if (Schema::hasTable($current) && ! Schema::hasTable($normalized)) {
                Schema::rename($current, $normalized);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables, true) as $current => $normalized) {
            if (Schema::hasTable($normalized) && ! Schema::hasTable($current)) {
                Schema::rename($normalized, $current);
            }
        }
    }
};
```

</details>

### 2026_06_09_000001_restore_evaluation_room_operational_columns.php

Fuente: [2026_06_09_000001_restore_evaluation_room_operational_columns.php](../../database/migrations/2026_06_09_000001_restore_evaluation_room_operational_columns.php). Objetos mencionados: `salas_evaluacion`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salas_evaluacion', function (Blueprint $table) {
            if (!Schema::hasColumn('salas_evaluacion', 'salon')) {
                $table->string('salon', 120)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'fecha_fin_evaluacion')) {
                $table->dateTime('fecha_fin_evaluacion')->nullable()->after('fecha_evaluacion');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'teacher_evaluation_minutes')) {
                $table->unsignedSmallInteger('teacher_evaluation_minutes')->default(15)->after('fecha_fin_evaluacion');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'project_presentation_minutes')) {
                $table->unsignedSmallInteger('project_presentation_minutes')->default(20)->after('teacher_evaluation_minutes');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'max_attempts')) {
                $table->unsignedTinyInteger('max_attempts')->default(1)->after('project_presentation_minutes');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'sequence_locked')) {
                $table->boolean('sequence_locked')->default(false)->after('max_attempts');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'current_order')) {
                $table->unsignedSmallInteger('current_order')->nullable()->after('sequence_locked');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('current_order');
            }
        });

        DB::table('salas_evaluacion')
            ->whereNotNull('fecha_evaluacion')
            ->whereNull('fecha_fin_evaluacion')
            ->update([
                'fecha_fin_evaluacion' => DB::raw('DATE_ADD(fecha_evaluacion, INTERVAL 1 HOUR)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('salas_evaluacion', function (Blueprint $table) {
            foreach ([
                'completed_at',
                'current_order',
                'sequence_locked',
                'max_attempts',
                'project_presentation_minutes',
                'teacher_evaluation_minutes',
                'fecha_fin_evaluacion',
                'salon',
            ] as $column) {
                if (Schema::hasColumn('salas_evaluacion', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
```

</details>

### 2026_06_09_000002_create_evaluation_document_releases.php

Fuente: [2026_06_09_000002_create_evaluation_document_releases.php](../../database/migrations/2026_06_09_000002_create_evaluation_document_releases.php). Objetos mencionados: `liberaciones_documentos_evaluacion`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('liberaciones_documentos_evaluacion')) {
            Schema::create('liberaciones_documentos_evaluacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('documento_repositorio_id');
                $table->string('alumno_id', 10);
                $table->boolean('liberado')->default(false);
                $table->string('revisado_por', 10)->nullable();
                $table->timestamp('revisado_en')->nullable();
                $table->timestamps();
                $table->unique(
                    ['documento_repositorio_id', 'alumno_id'],
                    'uq_liberacion_documento_alumno'
                );
            });
        }

        Schema::table('liberaciones_documentos_evaluacion', function (Blueprint $table) {
            if (!$this->constraintExists('fk_liberacion_documento')) {
                $table->foreign('documento_repositorio_id', 'fk_liberacion_documento')
                    ->references('id')
                    ->on('documentos_repositorio')
                    ->cascadeOnDelete();
            }
            if (!$this->constraintExists('fk_liberacion_alumno')) {
                $table->foreign('alumno_id', 'fk_liberacion_alumno')
                    ->references('id')
                    ->on('usuarios')
                    ->cascadeOnDelete();
            }
            if (!$this->constraintExists('fk_liberacion_revisor')) {
                $table->foreign('revisado_por', 'fk_liberacion_revisor')
                    ->references('id')
                    ->on('usuarios')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liberaciones_documentos_evaluacion');
    }

    private function constraintExists(string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'liberaciones_documentos_evaluacion')
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }
};
```

</details>

### 2026_06_09_000003_restore_project_registration_windows.php

Fuente: [2026_06_09_000003_restore_project_registration_windows.php](../../database/migrations/2026_06_09_000003_restore_project_registration_windows.php). Objetos mencionados: `project_registration_windows`, `ventanas_registro_proyectos`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_registration_windows') && !Schema::hasTable('ventanas_registro_proyectos')) {
            Schema::rename('project_registration_windows', 'ventanas_registro_proyectos');
        }

        if (!Schema::hasTable('ventanas_registro_proyectos')) {
            Schema::create('ventanas_registro_proyectos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grupo_academico_id')
                    ->constrained('grupos_academicos')
                    ->cascadeOnDelete();
                $table->dateTime('inicia_en');
                $table->dateTime('termina_en');
                $table->boolean('activo')->default(true);
                $table->text('notas')->nullable();
                $table->timestamp('creado_en')->nullable();
                $table->timestamp('actualizado_en')->nullable();
                $table->index(['grupo_academico_id', 'activo', 'inicia_en', 'termina_en'], 'idx_ventanas_grupo_estado_fechas');
            });

            return;
        }

        Schema::table('ventanas_registro_proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('ventanas_registro_proyectos', 'subject_group_id')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'grupo_academico_id')) {
                $table->renameColumn('subject_group_id', 'grupo_academico_id');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'starts_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'inicia_en')) {
                $table->renameColumn('starts_at', 'inicia_en');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'ends_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'termina_en')) {
                $table->renameColumn('ends_at', 'termina_en');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'notes')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'notas')) {
                $table->renameColumn('notes', 'notas');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'created_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'creado_en')) {
                $table->renameColumn('created_at', 'creado_en');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'updated_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'actualizado_en')) {
                $table->renameColumn('updated_at', 'actualizado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanas_registro_proyectos');
    }
};
```

</details>

### 2026_06_09_000004_complete_proposal_workflow.php

Fuente: [2026_06_09_000004_complete_proposal_workflow.php](../../database/migrations/2026_06_09_000004_complete_proposal_workflow.php). Objetos mencionados: `excepciones_revision_propuesta`, `proyectos`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excepciones_revision_propuesta', function (Blueprint $table) {
            if (!Schema::hasColumn('excepciones_revision_propuesta', 'asignatura_id')) {
                $table->foreignId('asignatura_id')->nullable()->after('grupo_academico_id')
                    ->constrained('asignaturas')->nullOnDelete();
            }
            if (!Schema::hasColumn('excepciones_revision_propuesta', 'estudiante_id')) {
                $table->string('estudiante_id', 10)->nullable()->after('docente_id');
                $table->foreign('estudiante_id')->references('id')->on('usuarios')->cascadeOnDelete();
            }
        });

        DB::statement('ALTER TABLE excepciones_revision_propuesta MODIFY docente_id VARCHAR(10) NULL');

        Schema::table('proyectos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyectos', 'es_propuesta')) {
                $table->boolean('es_propuesta')->default(false)->after('es_tesis');
                $table->index(['es_propuesta', 'estado_propuesta', 'activo'], 'idx_proyectos_flujo_propuesta');
            }
        });

        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobada','rechazada','aprobado','rechazado','requiere_cambios') NOT NULL DEFAULT 'pendiente'");
        DB::table('proyectos')->where('estado_propuesta', 'aprobada')->update(['estado_propuesta' => 'aprobado']);
        DB::table('proyectos')->where('estado_propuesta', 'rechazada')->update(['estado_propuesta' => 'rechazado']);
        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobado','rechazado','requiere_cambios') NOT NULL DEFAULT 'pendiente'");

        $defaultSubjectId = DB::table('asignaturas')
            ->where('nombre', 'Fundamentos de Ingeniería de Software')
            ->value('id');

        if ($defaultSubjectId) {
            $groupIds = DB::table('grupos_academicos')
                ->where('semestre', 5)
                ->where('activo', true)
                ->pluck('id');

            foreach ($groupIds as $groupId) {
                DB::table('grupos_asignaturas')->updateOrInsert(
                    ['grupo_academico_id' => $groupId, 'asignatura_id' => $defaultSubjectId],
                    ['creado_en' => now(), 'actualizado_en' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobada','rechazada','aprobado','rechazado','requiere_cambios') NOT NULL DEFAULT 'pendiente'");
        DB::table('proyectos')->where('estado_propuesta', 'aprobado')->update(['estado_propuesta' => 'aprobada']);
        DB::table('proyectos')->where('estado_propuesta', 'rechazado')->update(['estado_propuesta' => 'rechazada']);
        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobada','rechazada','requiere_cambios') NOT NULL DEFAULT 'pendiente'");

        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'es_propuesta')) {
                $table->dropIndex('idx_proyectos_flujo_propuesta');
                $table->dropColumn('es_propuesta');
            }
        });

        DB::statement('ALTER TABLE excepciones_revision_propuesta MODIFY docente_id VARCHAR(10) NOT NULL');

        Schema::table('excepciones_revision_propuesta', function (Blueprint $table) {
            if (Schema::hasColumn('excepciones_revision_propuesta', 'estudiante_id')) {
                $table->dropForeign(['estudiante_id']);
                $table->dropColumn('estudiante_id');
            }
            if (Schema::hasColumn('excepciones_revision_propuesta', 'asignatura_id')) {
                $table->dropForeign(['asignatura_id']);
                $table->dropColumn('asignatura_id');
            }
        });

    }
};
```

</details>

### 2026_06_09_000005_restore_user_academic_group.php

Fuente: [2026_06_09_000005_restore_user_academic_group.php](../../database/migrations/2026_06_09_000005_restore_user_academic_group.php). Objetos mencionados: `usuarios`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'semestre')) {
                $table->unsignedTinyInteger('semestre')->nullable()->after('perfil_id');
            }
            if (!Schema::hasColumn('usuarios', 'grupo')) {
                $table->string('grupo', 20)->nullable()->after('semestre');
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->index(['perfil_id', 'semestre', 'grupo', 'activo'], 'idx_usuarios_carga_academica');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex('idx_usuarios_carga_academica');
            $table->dropColumn(['semestre', 'grupo']);
        });
    }
};
```

</details>

### 2026_06_10_000001_classify_student_projects_as_proposals.php

Fuente: [2026_06_10_000001_classify_student_projects_as_proposals.php](../../database/migrations/2026_06_10_000001_classify_student_projects_as_proposals.php). Objetos mencionados: .

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE proyectos p
            INNER JOIN usuarios u ON u.id = p.creado_por
            SET p.es_propuesta = 1
            WHERE u.perfil_id = 3
        ");

        DB::statement("
            UPDATE proyectos p
            LEFT JOIN usuarios u ON u.id = p.creado_por
            SET p.es_propuesta = 0,
                p.estado_propuesta = 'aprobado',
                p.revisado_por = NULL,
                p.comentario_revision = NULL,
                p.revisado_en = NULL,
                p.revision_permitida_hasta = NULL
            WHERE u.id IS NULL OR u.perfil_id <> 3
        ");
    }

    public function down(): void
    {
        // La clasificacion anterior no puede reconstruirse de forma confiable.
    }
};
```

</details>

### 2026_06_10_000002_create_activity_notifications.php

Fuente: [2026_06_10_000002_create_activity_notifications.php](../../database/migrations/2026_06_10_000002_create_activity_notifications.php). Objetos mencionados: `notificaciones_actividad`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notificaciones_actividad')) {
            Schema::create('notificaciones_actividad', function (Blueprint $table) {
                $table->id();
                $table->string('usuario_id', 10);
                $table->string('actor_id', 10)->nullable();
                $table->string('tipo', 50);
                $table->string('titulo', 180);
                $table->text('mensaje');
                $table->string('url', 500);
                $table->timestamp('leida_en')->nullable();
                $table->timestamp('creada_en')->useCurrent();

                $table->index(['usuario_id', 'leida_en', 'creada_en'], 'idx_notificaciones_usuario_estado');
                $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
                $table->foreign('actor_id')->references('id')->on('usuarios')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_actividad');
    }
};
```

</details>

### 2026_06_11_000001_create_semester_management.php

Fuente: [2026_06_11_000001_create_semester_management.php](../../database/migrations/2026_06_11_000001_create_semester_management.php). Objetos mencionados: `excepciones_presentacion_semestre`, `periodos_academicos`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('periodos_academicos', 'promocion_automatica')) {
            Schema::table('periodos_academicos', function (Blueprint $table) {
                $table->boolean('promocion_automatica')->default(false)->after('activo');
            });
        }

        if (!Schema::hasColumn('periodos_academicos', 'promocion_aplicada_en')) {
            Schema::table('periodos_academicos', function (Blueprint $table) {
                $table->timestamp('promocion_aplicada_en')->nullable()->after('promocion_automatica');
            });
        }

        if (!Schema::hasTable('excepciones_presentacion_semestre')) {
            Schema::create('excepciones_presentacion_semestre', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('proyecto_id')->nullable();
                $table->string('usuario_id', 10)->nullable();
                $table->unsignedTinyInteger('semestre_presentacion');
                $table->string('motivo', 500)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamp('creado_en')->nullable();
                $table->timestamp('actualizado_en')->nullable();

                $table->index(['periodo_id', 'semestre_presentacion', 'activo'], 'idx_excepcion_periodo_semestre');
                $table->foreign('periodo_id')->references('id')->on('periodos_academicos')->cascadeOnDelete();
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
                $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones_presentacion_semestre');

        Schema::table('periodos_academicos', function (Blueprint $table) {
            $table->dropColumn(['promocion_automatica', 'promocion_aplicada_en']);
        });
    }
};
```

</details>

### 2026_06_11_000002_normalize_active_academic_period.php

Fuente: [2026_06_11_000002_normalize_active_academic_period.php](../../database/migrations/2026_06_11_000002_normalize_active_academic_period.php). Objetos mencionados: .

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('configuraciones_sistema')
            ->where('clave', 'active_academic_period')
            ->value('valor');
        $decoded = is_string($setting) ? json_decode($setting, true) : $setting;
        $periodName = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        $periodId = $periodName
            ? DB::table('periodos_academicos')->where('nombre', $periodName)->value('id')
            : null;
        $periodId ??= DB::table('periodos_academicos')->orderByDesc('id')->value('id');

        DB::table('periodos_academicos')->update(['activo' => false]);
        if ($periodId) {
            DB::table('periodos_academicos')->where('id', $periodId)->update(['activo' => true]);
        }
    }

    public function down(): void
    {
        // No se reactivan periodos historicos al revertir una normalizacion.
    }
};
```

</details>

### 2026_06_12_000001_add_api_performance_indexes.php

Fuente: [2026_06_12_000001_add_api_performance_indexes.php](../../database/migrations/2026_06_12_000001_add_api_performance_indexes.php). Objetos mencionados: .

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex(
            'excepciones_presentacion_semestre',
            ['periodo_id', 'activo', 'proyecto_id'],
            'idx_excepcion_periodo_proyecto'
        );
        $this->addIndex(
            'excepciones_presentacion_semestre',
            ['periodo_id', 'activo', 'usuario_id', 'actualizado_en'],
            'idx_excepcion_periodo_usuario'
        );
        $this->addIndex(
            'proyectos_integrantes',
            ['usuario_id', 'rol', 'proyecto_id'],
            'idx_integrante_usuario_rol_proyecto'
        );
        $this->addIndex(
            'proyectos',
            ['activo', 'titulo'],
            'idx_proyectos_activo_titulo'
        );
    }

    public function down(): void
    {
        $this->dropIndex('excepciones_presentacion_semestre', 'idx_excepcion_periodo_proyecto');
        $this->dropIndex('excepciones_presentacion_semestre', 'idx_excepcion_periodo_usuario');
        $this->dropIndex('proyectos_integrantes', 'idx_integrante_usuario_rol_proyecto');
        $this->dropIndex('proyectos', 'idx_proyectos_activo_titulo');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if (!Schema::hasTable($table) || !$this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index) => ($index['name'] ?? null) === $name
        );
    }
};
```

</details>

### 2026_09_02_000001_add_shared_academic_workflows.php

Fuente: [2026_09_02_000001_add_shared_academic_workflows.php](../../database/migrations/2026_09_02_000001_add_shared_academic_workflows.php). Objetos mencionados: `curso_estudiantes`, `cursos`, `empresas`, `proyectos`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('rfc', 13)->nullable()->after('nombre');
            $table->enum('estado_validacion', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente')->after('direccion');
            $table->string('solicitada_por', 20)->nullable()->after('estado_validacion');
            $table->string('validada_por', 20)->nullable()->after('solicitada_por');
            $table->text('comentario_validacion')->nullable()->after('validada_por');
            $table->dateTime('validada_en')->nullable()->after('comentario_validacion');
            $table->dateTime('creada_en')->useCurrent()->after('validada_en');
            $table->dateTime('actualizada_en')->nullable()->after('creada_en');
            $table->unique('rfc', 'uq_empresas_rfc');
            $table->index(['estado_validacion', 'nombre'], 'idx_empresas_estado_nombre');
        });

        DB::table('empresas')->whereNull('rfc')->update(['estado_validacion' => 'aprobada']);

        Schema::table('proyectos', function (Blueprint $table) {
            $table->enum('modalidad', ['dual', 'proyecto_integrador', 'caso_integrador'])
                ->default('proyecto_integrador')->after('tipo');
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->boolean('es_seguimiento_proyecto')->default(false)->after('activo');
            $table->string('clave_autoregistro')->nullable()->after('es_seguimiento_proyecto');
            $table->dateTime('clave_actualizada_en')->nullable()->after('clave_autoregistro');
            $table->string('clave_actualizada_por', 20)->nullable()->after('clave_actualizada_en');
        });

        Schema::create('curso_estudiantes', function (Blueprint $table) {
            $table->unsignedBigInteger('curso_id');
            $table->string('estudiante_id', 20);
            $table->dateTime('inscrito_en')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->primary(['curso_id', 'estudiante_id']);
            $table->index(['estudiante_id', 'activo'], 'idx_curso_estudiantes_usuario');
            $table->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_estudiantes');
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['es_seguimiento_proyecto', 'clave_autoregistro', 'clave_actualizada_en', 'clave_actualizada_por']);
        });
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn('modalidad'));
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique('uq_empresas_rfc');
            $table->dropIndex('idx_empresas_estado_nombre');
            $table->dropColumn(['rfc', 'estado_validacion', 'solicitada_por', 'validada_por', 'comentario_validacion', 'validada_en', 'creada_en', 'actualizada_en']);
        });
    }
};
```

</details>

### 2026_09_10_000001_add_storage_metadata_to_documento_versiones.php

Fuente: [2026_09_10_000001_add_storage_metadata_to_documento_versiones.php](../../database/migrations/2026_09_10_000001_add_storage_metadata_to_documento_versiones.php). Objetos mencionados: `documento_versiones`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documento_versiones')) {
            return;
        }

        Schema::table('documento_versiones', function (Blueprint $table): void {
            if (! Schema::hasColumn('documento_versiones', 'disco')) {
                $table->string('disco', 32)->nullable()->after('ruta_archivo');
            }
            if (! Schema::hasColumn('documento_versiones', 'checksum_sha256')) {
                $table->char('checksum_sha256', 64)->nullable()->after('tamano_bytes');
            }
            if (! Schema::hasColumn('documento_versiones', 'nombre_original')) {
                $table->string('nombre_original')->nullable()->after('nombre_archivo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documento_versiones')) {
            return;
        }

        $columns = collect(['disco', 'checksum_sha256', 'nombre_original'])
            ->filter(fn (string $column) => Schema::hasColumn('documento_versiones', $column))
            ->all();

        if ($columns) {
            Schema::table('documento_versiones', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
```

</details>

### 2026_10_13_000001_consolidate_academic_workflows.php

Fuente: [2026_10_13_000001_consolidate_academic_workflows.php](../../database/migrations/2026_10_13_000001_consolidate_academic_workflows.php). Objetos mencionados: `autorizaciones_academicas`, `avisos_academicos`, `bitacora_academica`, `carrera_periodos`, `cursos`, `dictamenes_docentes`, `documentos`, `ejecuciones_academicas`, `entrega_participantes`, `evaluaciones`, `liberaciones_documentos_evaluacion`, `matriculas_academicas`, `movimientos_academicos`, `operaciones_academicas`, `proyecto_periodos`, `recursos_agenda`, `residencia_requisitos`, `residencia_versiones`, `residencias`, `salas_evaluacion`, `salones_institucionales`.

<details>
<summary>Declaración completa de migración (incluye up/down)</summary>

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrera_periodos', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->string('estado', 20)->default('preparacion'); $t->boolean('conciliado')->default(false);
            $t->timestamps(); $t->unique(['carrera_id', 'periodo_id']);
        });
        Schema::create('matriculas_academicas', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->string('estudiante_id', 20); $t->unsignedBigInteger('grupo_id');
            $t->string('estado', 20)->default('activa'); $t->unsignedInteger('version')->default(1);
            $t->timestamps(); $t->unique(['carrera_id', 'periodo_id', 'estudiante_id'], 'uq_matricula_periodo');
            $t->index(['estudiante_id', 'estado']);
        });
        Schema::create('proyecto_periodos', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->unsignedBigInteger('proyecto_id'); $t->unsignedBigInteger('grupo_id'); $t->timestamps();
            $t->unique(['proyecto_id', 'periodo_id']);
        });
        Schema::create('operaciones_academicas', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->unsignedSmallInteger('carrera_id');
            $t->unsignedBigInteger('periodo_id'); $t->string('tipo', 40); $t->string('actor_id', 20);
            $t->string('huella', 64); $t->json('resultado'); $t->timestamps();
        });
        Schema::create('movimientos_academicos', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('origen_id'); $t->unsignedBigInteger('destino_id');
            $t->string('estudiante_id', 20); $t->uuid('operacion_id'); $t->string('accion', 20); $t->text('motivo'); $t->timestamps();
            $t->unique(['carrera_id', 'origen_id', 'destino_id', 'estudiante_id'], 'uq_movimiento_alumno');
        });
        Schema::create('autorizaciones_academicas', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->unsignedBigInteger('proyecto_id')->nullable(); $t->string('estudiante_id', 20)->nullable();
            $t->unsignedBigInteger('curso_id')->nullable(); $t->unsignedBigInteger('evaluacion_id')->nullable();
            $t->string('tipo', 40); $t->unsignedTinyInteger('semestre_destino')->nullable();
            $t->string('estado', 20)->default('solicitada'); $t->text('motivo');
            $t->string('solicitada_por', 20); $t->string('resuelta_por', 20)->nullable();
            $t->text('resolucion')->nullable(); $t->dateTime('vigente_hasta');
            $t->dateTime('consumida_en')->nullable(); $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->index(['carrera_id', 'periodo_id', 'estado'], 'idx_autorizacion_carrera_periodo');
        });
        Schema::create('bitacora_academica', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id')->nullable(); $t->string('actor_id', 20)->nullable();
            $t->string('accion', 80); $t->string('entidad', 80); $t->string('entidad_id', 80);
            $t->json('antes')->nullable(); $t->json('despues')->nullable(); $t->timestamp('creado_en')->useCurrent();
        });
        Schema::create('salones_institucionales', function (Blueprint $t) {
            $t->id(); $t->string('nombre', 120); $t->string('clave', 120)->unique(); $t->boolean('activo')->default(true);
        });
        Schema::create('recursos_agenda', function (Blueprint $t) { $t->string('id', 80)->primary(); });
        Schema::create('entrega_participantes', function (Blueprint $t) {
            $t->unsignedBigInteger('entrega_id'); $t->string('estudiante_id', 20);
            $t->primary(['entrega_id', 'estudiante_id']);
        });
        Schema::create('residencias', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->string('estudiante_id', 20); $t->unsignedBigInteger('proyecto_id');
            $t->unsignedBigInteger('empresa_id'); $t->string('asesor_id', 20);
            $t->string('origen', 30); $t->text('cambios_propuestos'); $t->string('estado', 20)->default('abierta');
            $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->unique(['estudiante_id', 'periodo_id']);
        });
        Schema::create('residencia_requisitos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('residencia_id'); $t->string('nombre', 180);
            $t->boolean('obligatorio')->default(true); $t->dateTime('fecha_limite')->nullable(); $t->timestamps();
        });
        Schema::create('residencia_versiones', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('requisito_id'); $t->unsignedInteger('numero');
            $t->string('disco', 50); $t->string('ruta', 500); $t->string('nombre', 255);
            $t->string('mime', 120); $t->unsignedBigInteger('bytes'); $t->string('sha256', 64);
            $t->string('estado', 25)->default('pendiente'); $t->text('observaciones')->nullable();
            $t->string('revisada_por', 20)->nullable(); $t->timestamps(); $t->unique(['requisito_id', 'numero']);
        });
        Schema::create('avisos_academicos', function (Blueprint $t) { $t->string('clave', 180)->primary(); $t->timestamp('created_at'); });
        Schema::create('ejecuciones_academicas', function (Blueprint $t) {
            $t->id(); $t->string('tarea', 80); $t->string('estado', 20); $t->text('detalle')->nullable(); $t->timestamps();
        });
        Schema::table('cursos', function (Blueprint $t) {
            $t->dateTime('inscripcion_desde')->nullable(); $t->dateTime('inscripcion_hasta')->nullable();
        });
        Schema::table('salas_evaluacion', function (Blueprint $t) {
            foreach (['responsable_id', 'temporizador_actualizado_por', 'horario_actualizado_por'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->string($name, 20)->nullable();
            }
            foreach (['completada_en', 'temporizador_iniciado_en', 'temporizador_finaliza_en', 'evaluacion_fuera_horario_hasta', 'horario_actualizado_en'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->dateTime($name)->nullable();
            }
            foreach (['secuencia_bloqueada', 'permite_evaluacion_fuera_horario'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->boolean($name)->default(false);
            }
            foreach (['orden_actual', 'secuencia_version', 'temporizador_orden', 'temporizador_duracion_segundos', 'temporizador_restante_segundos'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->unsignedInteger($name)->default(0);
            }
            if (!Schema::hasColumn('salas_evaluacion', 'temporizador_estado')) $t->string('temporizador_estado', 20)->default('idle');
            if (!Schema::hasColumn('salas_evaluacion', 'motivo_evaluacion_fuera_horario')) $t->text('motivo_evaluacion_fuera_horario')->nullable();
            $t->unsignedBigInteger('periodo_id')->nullable(); $t->unsignedBigInteger('salon_id')->nullable();
        });
        Schema::table('documentos', fn (Blueprint $t) => $t->string('categoria_detalle', 60)->nullable());
        Schema::table('dictamenes_docentes', function (Blueprint $t) {
            if (!Schema::hasColumn('dictamenes_docentes', 'apto_titulacion')) $t->boolean('apto_titulacion')->nullable();
        });
        if (!Schema::hasTable('liberaciones_documentos_evaluacion')) Schema::create('liberaciones_documentos_evaluacion', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('documento_repositorio_id'); $t->string('alumno_id', 20); $t->boolean('liberado')->default(false);
            $t->string('revisado_por', 20)->nullable(); $t->dateTime('revisado_en')->nullable(); $t->timestamps();
            $t->unique(['documento_repositorio_id', 'alumno_id'], 'uq_liberacion_documento_alumno');
            $t->foreign('documento_repositorio_id', 'fk_liberacion_documento_v2')->references('id')->on('documentos')->restrictOnDelete();
        });
        Schema::table('evaluaciones', function (Blueprint $t) {
            $t->json('criterios_snapshot')->nullable(); $t->string('modo_puntaje', 20)->nullable();
            $t->unsignedBigInteger('autorizacion_id')->nullable();
            $t->unsignedBigInteger('presentacion_autorizacion_id')->nullable();
        });
        // Restrict deletes: academic history must not disappear through cascades.
        foreach ([
            'carrera_periodos' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos'],
            'matriculas_academicas' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'estudiante_id' => 'usuarios', 'grupo_id' => 'grupos_academicos'],
            'proyecto_periodos' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'proyecto_id' => 'proyectos', 'grupo_id' => 'grupos_academicos'],
            'autorizaciones_academicas' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'proyecto_id' => 'proyectos', 'estudiante_id' => 'usuarios', 'curso_id' => 'cursos', 'evaluacion_id' => 'evaluaciones'],
            'movimientos_academicos' => ['carrera_id' => 'carreras', 'origen_id' => 'periodos_academicos', 'destino_id' => 'periodos_academicos', 'estudiante_id' => 'usuarios'],
            'salas_evaluacion' => ['periodo_id' => 'periodos_academicos', 'salon_id' => 'salones_institucionales'],
            'evaluaciones' => ['autorizacion_id' => 'autorizaciones_academicas', 'presentacion_autorizacion_id' => 'autorizaciones_academicas'],
            'entrega_participantes' => ['entrega_id' => 'entregas', 'estudiante_id' => 'usuarios'],
            'residencias' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'proyecto_id' => 'proyectos', 'estudiante_id' => 'usuarios', 'asesor_id' => 'usuarios', 'empresa_id' => 'empresas'],
            'residencia_requisitos' => ['residencia_id' => 'residencias'],
            'residencia_versiones' => ['requisito_id' => 'residencia_requisitos'],
        ] as $table => $references) {
            Schema::table($table, function (Blueprint $t) use ($references) {
                foreach ($references as $column => $target) $t->foreign($column)->references('id')->on($target)->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La consolidación conserva historial: restaurar un respaldo verificado, no eliminar tablas.');
    }
};
```

</details>

## SQL complementario

Estos scripts son independientes de `artisan migrate`; su aplicación depende del despliegue. Se referencian sin copiar INSERT con datos de ejemplo.

| Archivo | Objetos DDL identificados |
|---|---|
| [2026_06_01_add_thesis_flag_and_restore_advisor_roles.sql](../../database/sql/2026_06_01_add_thesis_flag_and_restore_advisor_roles.sql) | `project_user`, `projects` |
| [2026_06_01_update_project_user_thesis_roles.sql](../../database/sql/2026_06_01_update_project_user_thesis_roles.sql) | `project_user` |
| [2026_07_28_add_backup_retention_fields.sql](../../database/sql/2026_07_28_add_backup_retention_fields.sql) | `respaldos_base_datos` |
| [2026_07_28_create_backup_restore_verifications.sql](../../database/sql/2026_07_28_create_backup_restore_verifications.sql) | `verificaciones_restauracion` |
| [2026_07_28_create_career_modules_and_indicators.sql](../../database/sql/2026_07_28_create_career_modules_and_indicators.sql) | `indicadores_carrera`, `registros_modulo_carrera` |
| [2026_07_28_create_database_backup_history.sql](../../database/sql/2026_07_28_create_database_backup_history.sql) | `respaldos_base_datos` |
| [2026_07_28_create_integrity_run_history.sql](../../database/sql/2026_07_28_create_integrity_run_history.sql) | `ejecuciones_integridad` |
| [2026_07_28_create_multicareer_audit_log.sql](../../database/sql/2026_07_28_create_multicareer_audit_log.sql) | `auditoria_carreras` |
| [2026_07_28_create_multicareer_foundation.sql](../../database/sql/2026_07_28_create_multicareer_foundation.sql) | `asignaturas`, `carrera_modulos`, `carreras`, `configuraciones_carrera`, `documentos`, `etiquetas`, `grupos_academicos`, `proyectos`, `rubricas`, `usuario_carrera` |
| [2026_07_28_create_operational_alerts.sql](../../database/sql/2026_07_28_create_operational_alerts.sql) | `alertas_operativas` |
| [2026_07_28_harden_multicareer_operational_isolation.sql](../../database/sql/2026_07_28_harden_multicareer_operational_isolation.sql) | `cursos`, `entregables`, `evaluaciones`, `notificaciones`, `salas_evaluacion` |
| [2026_07_28_scope_rubrics_by_career.sql](../../database/sql/2026_07_28_scope_rubrics_by_career.sql) | `rubricas` |
| [2026_07_28_seed_career_specific_settings.sql](../../database/sql/2026_07_28_seed_career_specific_settings.sql) |  |
| [2026_07_29_create_continuity_measurements.sql](../../database/sql/2026_07_29_create_continuity_measurements.sql) | `mediciones_continuidad` |
| [2026_07_29_create_continuity_policy.sql](../../database/sql/2026_07_29_create_continuity_policy.sql) | `politica_continuidad` |
| [2026_08_18_add_career_management_profiles.sql](../../database/sql/2026_08_18_add_career_management_profiles.sql) |  |
| [2026_08_19_create_power_bi_semantic_layer.sql](../../database/sql/2026_08_19_create_power_bi_semantic_layer.sql) | `bi_control_calidad`, `bi_dim_asignatura`, `bi_dim_carrera`, `bi_dim_empresa`, `bi_dim_grupo`, `bi_dim_perfil`, `bi_dim_periodo`, `bi_dim_proyecto`, `bi_dim_sala_evaluacion`, `bi_dim_usuario`, `bi_fact_curso`, `bi_fact_dictamen_docente`, `bi_fact_documento`, `bi_fact_entrega`, `bi_fact_entregable`, `bi_fact_evaluacion`, `bi_fact_grupo_estudiante`, `bi_fact_proyecto_integrante`, `bi_fact_respuesta_evaluacion`, `bi_fact_usuario_carrera`, `bi_modelo_relaciones` |
| [2026_08_19_harden_evaluation_room_turns.sql](../../database/sql/2026_08_19_harden_evaluation_room_turns.sql) | `salas_evaluacion` |
| [2026_08_19_harden_rubric_relations.sql](../../database/sql/2026_08_19_harden_rubric_relations.sql) | `criterios_rubrica` |
| [2026_08_19_manage_evaluation_room_schedules.sql](../../database/sql/2026_08_19_manage_evaluation_room_schedules.sql) | `salas_evaluacion` |
| [2026_08_19_validate_power_bi_model.sql](../../database/sql/2026_08_19_validate_power_bi_model.sql) |  |
| [seed_repository_test_data.sql](../../database/sql/seed_repository_test_data.sql) |  |
