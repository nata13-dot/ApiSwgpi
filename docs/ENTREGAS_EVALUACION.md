# Entregas para evaluación

Requisitos para que un proyecto presente en sala, según la etapa que se evalúa:

| Etapa | Hoja de liberación | Presentación | Enlace de Drive | Documento de reporte |
|---|---|---|---|---|
| 5° Propuesta | PDF/DOC/DOCX | PPTX, hasta 100 MB, sin video | – | – |
| 6° y 7° Avance | PDF/DOC/DOCX | PPTX, hasta 100 MB, sin video | Obligatorio | Avance (PDF/DOC/DOCX) |
| 8° Titulación | PDF/DOC/DOCX | PPTX, hasta 100 MB, sin video | Obligatorio | Final (PDF/DOC/DOCX) |

- **Cuándo se califica un proyecto:** cuando tiene todo lo que le aplica, cada entrega está **aprobada por un encargado** y al menos un integrante está liberado.
- **Revisión:**
  - Revisan las entregas y liberan alumnos el administrador general, la jefatura, la asistencia de jefatura y la coordinación de proyectos (ésta, no en tesis).
  - Cada entrega queda **Aprobada** o **Con observaciones**; las observaciones llevan un comentario obligatorio que ve el equipo.
  - Si el equipo reemplaza una entrega, su revisión se borra y vuelve a quedar pendiente.
  - Los docentes sólo consultan las entregas de los proyectos que asesoran.
  - La migración es `2026_10_29_000002_evaluation_deliverable_reviews` (tabla `revisiones_entregas_evaluacion`).
- **Integrantes no liberados:** no presentan, y el reporte de la evaluación los marca como "no presentó: sin liberación".
- **Periodo:** sólo cuentan las entregas hechas dentro del periodo de la sala.
- **Presentación con video:** si el PPTX trae video incrustado (`ppt/media/*.mp4`, `.mov` y similares), se rechaza. El video va en la carpeta de Drive.
- **Enlace:** debe empezar con `https://drive.google.com` o `https://docs.google.com`. Hay uno por proyecto y periodo, en la tabla `enlaces_evaluacion`.
- **Dónde está la lógica:** `App\Services\EvaluationRequirementsService`. La migración es `2026_10_29_000001_evaluation_deliverables` (aplícala con `--path`).

## Límites de subida en el servidor

Los valores por omisión de PHP (2 MB por archivo y 8 MB por petición) y de Nginx (1 MB) rechazan la presentación antes de que llegue a Laravel. En el servidor de la API:

```ini
; /etc/php/8.2/fpm/pool.d/<pool de la API>.conf  (o php.ini de FPM)
php_admin_value[upload_max_filesize] = 110M
php_admin_value[post_max_size] = 120M
php_admin_value[max_input_time] = 300
```

```nginx
# server { } de la API
client_max_body_size 120m;
client_body_timeout 300s;
fastcgi_read_timeout 300s;
```

Después: `systemctl restart php8.2-fpm && nginx -t && systemctl reload nginx`.

**Cloudflare:** el plan gratuito y el Pro aceptan peticiones de hasta 100 MB. Una presentación de casi 100 MB más el resto del formulario puede pasar de ese tope, y Cloudflare responde 413 antes de llegar al servidor. Si pasa seguido, baja `PRESENTATION_MAX_MB` a 95 en `EvaluationRequirementsService`.
