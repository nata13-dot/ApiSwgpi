    # PROMPT PARA COPILOT - MODIFICACIONES FRONTEND SWGPI

    ---

    ## 📋 CONTEXTO DEL PROYECTO

    Estoy trabajando en el sistema SWGPI (Sistema de Gestión de Proyectos Integradores) - una API Laravel 13.7 que consume un frontend en PHP vanilla con Bootstrap 5.3.7.

    Recientemente se implementaron 3 nuevos endpoints en el backend:
    - POST `/api/deliverables/{id}/calificar` - Calificar entregas
    - POST `/api/deliverables/{id}/upload` - Subir archivos
    - GET `/api/deliverables/{id}/download` - Descargar archivos

    También se agregaron campos nuevos a la BD:
    - **projects**: year (INT), file_path (VARCHAR), authors (TEXT)
    - **deliverables**: calificacion (DECIMAL), fecha_calificacion (DATETIME), calificado_por (VARCHAR)
    - **competencias**: fecha_inicio (DATE), fecha_fin (DATE)

    ---

    ## 🎯 TAREAS A REALIZAR

    ### 1️⃣ CREAR FORMULARIO DE CALIFICACIÓN

    **Ubicación**: `Cliente/revision_entregables.php`

    **Requisitos**:
    - Modal o formulario para calificar entregas
    - Campo input para calificación (tipo number, min=0, max=100)
    - Validación en cliente: calificación entre 0-100
    - Botón "Calificar"
    - Mostrar resultado (calificación guardada, quién calificó, cuándo)

    **Consumir endpoint**:
    ```
    POST /api/deliverables/{id}/calificar
    Headers: Authorization: Bearer {token}
    Body: { calificacion: 85 }
    ```

    **Respuesta esperada**:
    ```json
    {
    "message": "Entregable calificado exitosamente",
    "deliverable": {
        "id": 1,
        "nombre": "Proyecto Final",
        "calificacion": 85,
        "fecha_calificacion": "2026-05-07 14:30:00",
        "calificado_por": "admin123"
    }
    }
    ```

    ---

    ### 2️⃣ AGREGAR DESCARGA DE ARCHIVOS

    **Ubicación**: `Cliente/entregables_view.php` y `Cliente/estudiante_view.php`

    **Requisitos**:
    - Botón "Descargar" en tabla de entregas
    - Solo mostrar si existe archivo (archivo_path no null)
    - Validar acceso según perfil:
    - Admin: puede descargar todas
    - Docente: solo de sus proyectos
    - Estudiante: solo las suyas
    - Manejar errores (archivo no existe, sin acceso)

    **Consumir endpoint**:
    ```
    GET /api/deliverables/{id}/download
    Headers: Authorization: Bearer {token}
    ```

    **Respuesta**: Archivo binario para descargar

    **JavaScript ejemplo**:
    ```javascript
    function descargarEntregable(deliverable_id) {
        const token = localStorage.getItem('token');
        fetch(`/api/deliverables/${deliverable_id}/download`, {
            headers: { 'Authorization': `Bearer ${token}` }
        })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `entregable_${deliverable_id}.pdf`;
            a.click();
        })
        .catch(error => alert('Error: ' + error.message));
    }
    ```

    ---

    ### 3️⃣ AGREGAR UPLOAD DE ARCHIVOS

    **Ubicación**: `Cliente/entregables_view.php`

    **Requisitos**:
    - Formulario con input file
    - Solo para propietario del entregable o admin
    - Validar MIME type (permitidos: pdf, doc, docx, xls, xlsx, zip)
    - Máximo 50MB
    - Mostrar progreso de carga
    - Mostrar URL del archivo subido

    **Consumir endpoint**:
    ```
    POST /api/deliverables/{id}/upload
    Headers: Authorization: Bearer {token}
            Content-Type: multipart/form-data
    Body: FormData con archivo en "archivo"
    ```

    **Respuesta esperada**:
    ```json
    {
    "message": "Archivo subido exitosamente",
    "deliverable": {
        "id": 1,
        "archivo_path": "entregas/1_student123_1715087400.pdf",
        "estado": "enviado"
    },
    "archivo_url": "http://localhost/storage/entregas/1_student123_1715087400.pdf"
    }
    ```

    **JavaScript ejemplo**:
    ```javascript
    document.getElementById('formUpload').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const file = document.getElementById('fileInput').files[0];
        
        // Validar MIME
        const tiposPermitidos = ['application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!tiposPermitidos.includes(file.type)) {
            alert('Tipo de archivo no permitido');
            return;
        }
        
        // Validar tamaño
        if (file.size > 50 * 1024 * 1024) {
            alert('Archivo muy grande (máximo 50MB)');
            return;
        }
        
        formData.append('archivo', file);
        
        const token = localStorage.getItem('token');
        fetch(`/api/deliverables/${deliverable_id}/upload`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.message) {
                alert('Archivo subido: ' + data.archivo_url);
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        });
    });
    ```

    ---

    ### 4️⃣ VALIDAR RANGOS DE FECHAS

    **Ubicación**: `Cliente/entregables_view.php` al crear entregable

    **Requisitos**:
    - Si tiene competencia_id, validar fecha de entregable
    - Fecha debe estar entre fecha_inicio y fecha_fin de competencia
    - Mostrar error si está fuera de rango
    - Mostrar rango permitido en tooltip/ayuda

    **Lógica**:
    ```javascript
    async function validarFechaEntregable(competencia_id, fecha_limite) {
        const response = await fetch(`/api/competencias/${competencia_id}`);
        const competencia = await response.json();
        
        const fecha = new Date(fecha_limite);
        const inicio = new Date(competencia.fecha_inicio);
        const fin = new Date(competencia.fecha_fin);
        
        if (fecha < inicio || fecha > fin) {
            alert(`La fecha debe estar entre ${competencia.fecha_inicio} y ${competencia.fecha_fin}`);
            return false;
        }
        return true;
    }
    ```

    ---

    ### 5️⃣ FILTRAR POR PERFIL

    **Ubicación**: `Cliente/projects_view.php` y `Cliente/admin_view.php`

    **Requisitos**:
    - Admin (perfil_id=1): Ver TODOS los proyectos
    - Docente (perfil_id=2): Ver solo proyectos donde es asesor
    - Estudiante (perfil_id=3): Ver solo proyectos asignados

    **Lógica (en JavaScript)**:
    ```javascript
    async function cargarProyectos() {
        const token = localStorage.getItem('token');
        const userResponse = await fetch('/api/auth/me', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const user = await userResponse.json();
        
        const response = await fetch('/api/projects', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const proyectos = await response.json();
        
        let proyectosFiltrados = proyectos.data;
        
        // Docente: filtrar por asesor
        if (user.user.perfil_id === 2) {
            proyectosFiltrados = proyectos.data.filter(p => 
                p.advisors.some(a => a.id === user.user.id)
            );
        }
        
        // Estudiante: filtrar por miembro
        if (user.user.perfil_id === 3) {
            proyectosFiltrados = proyectos.data.filter(p =>
                p.students.some(s => s.id === user.user.id)
            );
        }
        
        mostrarProyectos(proyectosFiltrados);
    }
    ```

    ---

    ### 6️⃣ MOSTRAR CAMPOS NUEVOS

    **Ubicación**: Todas las tablas/modales donde se muestren datos

    **Agregar columnas/campos**:

    **En tabla de Proyectos**:
    - Año (year)
    - Autores (authors)
    - Archivo (file_path con link descargable)

    **En tabla de Entregas**:
    - Calificación (calificacion)
    - Fecha de calificación (fecha_calificacion)
    - Calificado por (calificado_por - nombre del docente)

    **En tabla de Competencias**:
    - Fecha inicio (fecha_inicio)
    - Fecha fin (fecha_fin)
    - Badge con "En rango" o "Vencido"

    ---

    ### 7️⃣ AGREGAR VALIDACIONES EN validations.php

    **Ubicación**: `Cliente/validations.php`

    **Agregar funciones**:

    ```php
    /**
     * Validar rango de fechas de competencia
     */
    function validar_fecha_entregable_en_competencia($competencia_id, $fecha_entregable) {
        // Obtener competencia desde BD
        $competencia = obtener_competencia($competencia_id);
        
        if (!$competencia || !$competencia['fecha_inicio'] || !$competencia['fecha_fin']) {
            return true;
        }
        
        $fecha = strtotime($fecha_entregable);
        $inicio = strtotime($competencia['fecha_inicio']);
        $fin = strtotime($competencia['fecha_fin']);
        
        return $fecha >= $inicio && $fecha <= $fin;
    }

    /**
     * Validar que calificación esté en rango (0-100)
     */
    function validar_calificacion($calificacion) {
        $valor = (float)$calificacion;
        return $valor >= 0 && $valor <= 100;
    }

    /**
     * Validar acceso a entrega
     */
    function validar_acceso_entrega($entrega_id, $perfil_id, $user_id) {
        global $conexion;
        
        // Admin: acceso total
        if ($perfil_id == 1) return true;
        
        // Obtener proyecto del entregable
        $stmt = $conexion->prepare("
            SELECT project_id FROM deliverables WHERE id = ?
        ");
        $stmt->bind_param("i", $entrega_id);
        $stmt->execute();
        $entrega = $stmt->get_result()->fetch_assoc();
        
        if (!$entrega) return false;
        
        // Docente: solo sus proyectos (donde es asesor)
        if ($perfil_id == 2) {
            $stmt = $conexion->prepare("
                SELECT * FROM project_user 
                WHERE project_id = ? AND user_id = ? AND rol_asesor IS NOT NULL
            ");
            $stmt->bind_param("is", $entrega['project_id'], $user_id);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        }
        
        // Estudiante: solo proyectos donde es miembro
        if ($perfil_id == 3) {
            $stmt = $conexion->prepare("
                SELECT * FROM project_user 
                WHERE project_id = ? AND user_id = ? AND rol_asesor IS NULL
            ");
            $stmt->bind_param("is", $entrega['project_id'], $user_id);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        }
        
        return false;
    }
    ```

    ---

    ## 📝 NOTAS IMPORTANTES

    1. **Validación MIME types permitidos**:
    - application/pdf
    - application/msword
    - application/vnd.openxmlformats-officedocument.wordprocessingml.document
    - application/vnd.ms-excel
    - application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
    - application/zip

    2. **Tamaño máximo**: 50MB

    3. **Tokens JWT**: 
    - Guardar en `localStorage.getItem('token')`
    - Enviar en header: `Authorization: Bearer {token}`

    4. **Control de acceso**:
    - Admin (perfil_id=1): acceso a todo
    - Docente (perfil_id=2): solo sus proyectos
    - Estudiante (perfil_id=3): solo lo asignado

    5. **Errores comunes**:
    - 401: Token inválido o expirado
    - 403: Sin acceso (perfil insuficiente)
    - 404: Recurso no encontrado
    - 422: Validación fallida

    6. **Mensajes de éxito/error**:
    - Usar modales o alertas de Bootstrap
    - Mostrar respuesta JSON en consola para debugging

    ---

    ## ✅ CHECKLIST

    Al terminar, verificar:

    - [ ] Formulario de calificación funciona (POST /deliverables/{id}/calificar)
    - [ ] Descarga de archivos funciona (GET /deliverables/{id}/download)
    - [ ] Upload de archivos funciona (POST /deliverables/{id}/upload)
    - [ ] Validación de fechas de competencia
    - [ ] Filtrado por perfil en listados
    - [ ] Campos nuevos se muestran en tablas
    - [ ] Validaciones en Cliente/validations.php
    - [ ] Controles de acceso funcionan
    - [ ] Manejo de errores (404, 403, 422)

    ---

    ## 🔗 ENDPOINTS REFERENCIA

    ```
    BASE_URL: http://localhost/api

    AUTENTICACIÓN
    POST   /auth/login
    GET    /auth/me
    POST   /auth/logout

    PROYECTOS
    GET    /projects
    POST   /projects
    GET    /projects/{id}
    PUT    /projects/{id}
    DELETE /projects/{id}

    ENTREGAS (NUEVOS)
    POST   /deliverables/{id}/calificar      ← CALIFICAR
    POST   /deliverables/{id}/upload         ← SUBIR ARCHIVO
    GET    /deliverables/{id}/download       ← DESCARGAR ARCHIVO

    ENTREGAS (CRUD)
    GET    /deliverables
    POST   /deliverables
    GET    /deliverables/{id}
    PUT    /deliverables/{id}
    DELETE /deliverables/{id}

    COMPETENCIAS
    GET    /competencias
    POST   /competencias
    GET    /competencias/{id}
    PUT    /competencias/{id}
    DELETE /competencias/{id}

    ASIGNATURAS
    GET    /asignaturas
    POST   /asignaturas
    GET    /asignaturas/{id}
    PUT    /asignaturas/{id}
    DELETE /asignaturas/{id}

    USUARIOS
    GET    /users
    POST   /users
    GET    /users/{id}
    PUT    /users/{id}
    DELETE /users/{id}
    POST   /users/{id}/toggle-active
    GET    /users-inactive
    ```

    ---

    **Fin del prompt. Listo para copiar y pegar en Copilot.**
