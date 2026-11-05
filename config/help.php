<?php

/*
 * Contenido de la guía de inicio y del asistente de ayuda, común para la app y la web.
 * El asistente no usa inteligencia artificial ni envía datos fuera del sistema: sólo compara la pregunta con estos temas.
 *
 * audience: all | student | teacher | manager (jefatura, asistencia, coordinación y admin) | academic (sin coordinación)
 *           | admin (jefatura y admin) | general (administración general).
 * app / web: pantalla en cada front; web admite un mapa por audiencia cuando la página cambia según el perfil.
 * module: módulo de carrera del que depende; si está deshabilitado el tema no se ofrece.
 */
return [
    'tour' => [
        'intro' => [
            'student' => 'Aquí registras tu propuesta, subes entregas y consultas tus evaluaciones. Te muestro dónde está cada cosa.',
            'teacher' => 'Aquí revisas propuestas, das seguimiento a tus proyectos y evalúas en sala. Te muestro dónde está cada cosa.',
            'manager' => 'Aquí administras personas, proyectos y evaluaciones de tu carrera. Te muestro dónde está cada cosa.',
            'general' => 'Aquí supervisas todas las carreras y la continuidad del sistema. Te muestro dónde está cada cosa.',
        ],
        'menu' => [
            'student' => 'Tu camino: 1) Registrar propuesta, 2) Mis entregables, 3) Evidencias para la sala. Todo está en este menú.',
            'teacher' => 'Tus secciones principales son Mis proyectos, Revisar propuestas, Evaluaciones y Entregables.',
            'manager' => 'Está agrupado por tema: Personas, Proyectos y tesis, Académico y Sistema. Sólo ves lo que tu perfil puede usar.',
            'general' => 'Arriba está lo institucional (panel, carreras, operaciones y respaldos); abajo, lo de la carrera que tengas activa.',
        ],
        'steps' => [
            ['target' => 'search', 'title' => 'Encuentra lo que sea', 'text' => 'Busca pantallas y también registros: proyectos, entregables, documentos y más. En computadora, abre con Ctrl + K.'],
            ['target' => 'career', 'audience' => 'general', 'title' => 'Carrera activa', 'text' => 'Elige con qué carrera trabajas. Lo que ves en el menú corresponde a esa carrera.'],
            ['target' => 'notifications', 'title' => 'Avisos y actividad', 'text' => 'Aquí llegan avisos, fechas próximas y cambios en tus proyectos. Decide qué recibir desde Mi perfil.'],
            ['target' => 'account', 'title' => 'Tu cuenta', 'text' => 'Consulta tu perfil, cambia tu contraseña o cierra sesión.'],
            ['target' => 'help', 'title' => '¿Dudas?', 'text' => 'Pregúntale aquí al asistente cómo hacer algo, o repite esta guía cuando quieras.'],
        ],
    ],

    'topics' => [
        // ---------------------------------------- Todos
        ['id' => 'password', 'audience' => 'all', 'question' => '¿Cómo cambio mi contraseña?', 'keywords' => ['contraseña', 'clave', 'password', 'acceso'], 'app' => '/profile', 'web' => '/pages/profile.php',
            'steps' => ['Abre Mi perfil.', 'En «Cambiar contraseña», escribe la actual y la nueva dos veces.', 'Guarda los cambios. En la app, tendrás que volver a iniciar sesión.']],
        ['id' => 'photo', 'audience' => 'all', 'question' => '¿Cómo cambio mi foto o mi teléfono?', 'keywords' => ['foto', 'imagen', 'telefono', 'datos', 'perfil', 'especialidad'], 'app' => '/profile', 'web' => '/pages/profile.php',
            'steps' => ['Abre Mi perfil.', 'Toca tu foto para elegir otra (JPG, PNG o WEBP, hasta 2 MB) o edita tu teléfono.', 'Guarda los cambios. El nombre y el correo sólo los actualiza la jefatura.']],
        ['id' => 'notifications', 'audience' => 'all', 'question' => '¿Cómo elijo qué notificaciones recibir?', 'keywords' => ['notificacion', 'aviso', 'recordatorio', 'campana', 'silencio', 'horario', 'alerta'], 'app' => '/profile', 'web' => '/pages/profile.php',
            'steps' => ['Abre Mi perfil y ve a «Avisos».', 'Activa o desactiva cada aviso opcional. Los que requieren tu atención, como calificaciones o cambios de sala, siempre llegan.', 'De 22:00 a 7:00 sólo llegan cambios urgentes.']],
        ['id' => 'search', 'audience' => 'all', 'question' => '¿Cómo busco algo en el sistema?', 'keywords' => ['buscar', 'busqueda', 'encontrar', 'localizar', 'donde'],
            'steps' => ['Toca la lupa de la barra superior (en computadora, Ctrl + K).', 'Escribe al menos dos letras; no importan los acentos ni el orden de las palabras.', 'Elige el resultado para abrirlo. Sólo aparece lo que tu perfil puede consultar.']],
        ['id' => 'repository', 'audience' => 'all', 'module' => 'repositorio', 'question' => '¿Dónde consulto documentos y tesis?', 'keywords' => ['repositorio', 'documento', 'tesis', 'biblioteca', 'descargar', 'archivo'], 'app' => '/repository', 'web' => '/pages/repositorio.php',
            'steps' => ['En el menú, abre Sistema › Repositorio.', 'Busca por título, autor, descripción o etiqueta, o filtra por categoría.', 'Abre el documento para verlo o descargarlo.']],
        ['id' => 'empty-file', 'audience' => 'all', 'question' => '¿Por qué no se sube mi archivo?', 'keywords' => ['subir', 'archivo', 'vacio', 'error', 'formato', 'tamaño', 'pesado'],
            'steps' => ['Revisa que el archivo tenga contenido: el sistema rechaza los archivos vacíos.', 'Confirma que el formato sea uno de los permitidos en esa pantalla.', 'Revisa el tamaño; si excede el máximo, reduce el archivo y vuelve a intentarlo.']],

        // ---------------------------------------- Estudiante
        ['id' => 'proposal', 'audience' => 'student', 'module' => 'proyectos', 'question' => '¿Cómo registro mi propuesta de proyecto?', 'keywords' => ['propuesta', 'proyecto', 'registrar', 'empresa', 'objetivo', 'tesis'], 'app' => '/proposals', 'web' => '/pages/student/proposal-register.php',
            'steps' => ['En el menú, abre Proyectos y tesis › Registrar propuesta. El registro debe estar abierto para tu carrera.', 'Si lo desarrollas solo, responde que no a «¿Desarrollas tu proyecto junto con un compañero?».', 'Captura el nombre, la descripción, los objetivos y los datos de la empresa.', 'Envíala y espera la validación. Te avisaremos del resultado.']],
        ['id' => 'proposal-join', 'audience' => 'student', 'module' => 'proyectos', 'question' => '¿Cómo me uno al proyecto de un compañero?', 'keywords' => ['compañero', 'unirme', 'equipo', 'solicitud', 'integrante'], 'app' => '/proposals', 'web' => '/pages/student/proposal-register.php',
            'steps' => ['Abre Proyectos y tesis › Registrar propuesta.', 'Responde que sí a «¿Desarrollas tu proyecto junto con un compañero?».', 'Busca su propuesta entre las de tu grupo y toca «Solicitar unirme».', 'Un integrante debe aceptar tu solicitud.']],
        ['id' => 'proposal-fix', 'audience' => 'student', 'module' => 'proyectos', 'question' => '¿Qué hago si me piden cambios en mi propuesta?', 'keywords' => ['cambios', 'corregir', 'rechazada', 'observaciones', 'propuesta'], 'app' => '/proposals', 'web' => '/pages/student/proposal-register.php',
            'steps' => ['Abre Proyectos y tesis › Registrar propuesta.', 'Lee las observaciones en «Corregir propuesta».', 'Haz los cambios y envíala de nuevo antes de la fecha indicada.']],
        ['id' => 'enroll', 'audience' => 'student', 'question' => '¿Cómo me inscribo a una materia con clave?', 'keywords' => ['inscribir', 'inscripcion', 'materia', 'curso', 'clave', 'seguimiento'], 'app' => '/academic-workflows', 'web' => '/pages/academic-workflows.php',
            'steps' => ['Abre Seguimiento académico.', 'Busca tu materia y escribe la clave que te dio tu docente en «Inscribirme».', 'Una vez inscrito verás sus entregables y recibirás recordatorios.']],
        ['id' => 'deliverable-upload', 'audience' => 'student', 'module' => 'entregables', 'question' => '¿Cómo subo un entregable?', 'keywords' => ['entregable', 'entrega', 'subir', 'tarea', 'archivo', 'calificacion'], 'app' => '/deliverables', 'web' => '/pages/student/my-deliverables.php',
            'steps' => ['Abre Académico › Mis entregables. Si está vacío, primero inscríbete a la materia.', 'Elige el entregable y toca «Subir o actualizar archivo».', 'Selecciona tu archivo. Ahí mismo verás su calificación cuando esté revisado.']],
        ['id' => 'evidence', 'audience' => 'student', 'module' => 'evaluaciones', 'question' => '¿Dónde subo mi presentación y mi hoja de liberación?', 'keywords' => ['presentacion', 'diapositivas', 'liberacion', 'evidencia', 'drive', 'avance', 'video'], 'app' => '/evaluation-documents', 'web' => '/pages/evaluation-documents.php',
            'steps' => ['Abre Proyectos y tesis › Evidencias.', 'Sube cada documento en el apartado de tu proyecto.', 'La presentación no debe llevar video: súbelo aparte o agrega su enlace de Drive.']],
        ['id' => 'schedule', 'audience' => 'student', 'module' => 'evaluaciones', 'question' => '¿Cuándo y dónde presento mi proyecto?', 'keywords' => ['presentar', 'presento', 'exponer', 'expongo', 'sala', 'turno', 'horario', 'exposicion', 'evaluacion', 'fecha'], 'app' => '/dashboard', 'web' => '/pages/student/dashboard.php',
            'steps' => ['Abre Inicio: ahí aparece tu sala, salón, fecha y turno cuando los programan.', 'También recibirás un aviso cuando te asignen sala o cambie el horario, y otro cuando se acerque tu turno.']],

        // ---------------------------------------- Docente
        ['id' => 'review-proposals', 'audience' => 'teacher', 'module' => 'proyectos', 'question' => '¿Cómo reviso las propuestas?', 'keywords' => ['revisar', 'propuesta', 'aprobar', 'rechazar', 'validar', 'cambios'], 'app' => '/proposals', 'web' => '/pages/teacher/proposal-review.php',
            'steps' => ['Abre Proyectos y tesis › Revisar propuestas.', 'Elige «Aprobar», «Solicitar cambios» o «Rechazar».', 'Si aparece «No validas propuestas», la jefatura aún no te agrega como docente validador.']],
        ['id' => 'my-projects', 'audience' => 'teacher', 'module' => 'proyectos', 'question' => '¿Dónde veo los proyectos que asesoro?', 'keywords' => ['proyecto', 'asesor', 'asesoria', 'tesis', 'mis'], 'app' => '/projects', 'web' => '/pages/teacher/my-projects.php',
            'steps' => ['Abre Proyectos y tesis › Mis proyectos.', 'Busca por título, alumno o empresa y abre el proyecto para ver su detalle.']],
        ['id' => 'grade-deliverable', 'audience' => 'teacher', 'module' => 'entregables', 'question' => '¿Cómo califico un entregable?', 'keywords' => ['calificar', 'entregable', 'entrega', 'revisar', 'nota'], 'app' => '/deliverables', 'web' => '/pages/teacher/my-deliverables.php',
            'steps' => ['Abre Académico › Entregables.', 'Busca la entrega y toca «Calificar».', 'Escribe una calificación de 0 a 100 y toca «Guardar calificación».']],
        ['id' => 'open-enrollment', 'audience' => 'teacher', 'question' => '¿Cómo abro la inscripción de mi materia?', 'keywords' => ['inscripcion', 'clave', 'autorregistro', 'materia', 'curso', 'abrir', 'alumnos'], 'app' => '/academic-workflows', 'web' => '/pages/academic-workflows.php',
            'steps' => ['Abre Seguimiento académico y elige tu materia.', 'En «Abrir inscripción / renovar clave», define la clave y las fechas de apertura y cierre. La apertura no puede ser anterior al momento actual.', 'Comparte la clave con tu grupo.']],
        ['id' => 'evaluate-room', 'audience' => 'teacher', 'module' => 'evaluaciones', 'question' => '¿Cómo evalúo en una sala?', 'keywords' => ['evaluar', 'sala', 'rubrica', 'calificar', 'turno', 'presentacion'], 'app' => '/evaluations', 'web' => '/pages/admin/evaluations.php',
            'steps' => ['Abre Proyectos y tesis › Evaluaciones a la hora de tu sala.', 'Elige el proyecto en turno y llena la rúbrica.', 'Consulta horarios y compañeros en Salas y turnos.']],
        ['id' => 'review-evidence', 'audience' => 'teacher', 'module' => 'evaluaciones', 'question' => '¿Cómo reviso las evidencias de mis alumnos?', 'keywords' => ['evidencia', 'presentacion', 'liberacion', 'aprobar', 'observaciones'], 'app' => '/evaluation-documents', 'web' => '/pages/evaluation-documents.php',
            'steps' => ['Abre Proyectos y tesis › Evidencias.', 'Descarga el documento del proyecto.', 'Marca «Aprobar» o «Con observaciones». En su caso, guarda la liberación.']],

        // ---------------------------------------- Gestión de carrera
        ['id' => 'open-self-registration', 'audience' => 'manager', 'module' => 'proyectos', 'question' => '¿Cómo abro el autoregistro de alumnos?', 'keywords' => ['autoregistro', 'crear cuenta', 'registro', 'alumnos', 'nuevos', 'abrir'], 'app' => '/proposals', 'web' => '/pages/admin/proposal-config.php',
            'steps' => ['Abre Proyectos y tesis › Propuestas.', 'En «Autoregistro de alumnos», elige la fecha de cierre y toca «Abrir».', 'Sólo se abre de julio a diciembre; mientras esté abierto, el inicio de sesión muestra «Crear cuenta».']],
        ['id' => 'approve-accounts', 'audience' => 'academic', 'module' => 'usuarios', 'question' => '¿Cómo apruebo las cuentas de alumnos nuevos?', 'keywords' => ['solicitud', 'cuenta', 'aprobar', 'autoregistro', 'alumnos', 'nuevos'], 'app' => '/registration-requests', 'web' => '/pages/admin/registration-requests.php',
            'steps' => ['Abre Personas › Solicitudes de cuenta.', 'Revisa los datos de cada alumno.', 'Toca «Aprobar» para crear la cuenta o «Rechazar» para descartarla.']],
        ['id' => 'new-student-groups', 'audience' => 'manager', 'module' => 'proyectos', 'question' => '¿Cómo defino los grupos de alumnos nuevos?', 'keywords' => ['grupo', 'grupos', 'quinto', '5', 'nuevos', 'a', 'b', 'c'], 'app' => '/proposals', 'web' => '/pages/admin/proposal-config.php',
            'steps' => ['Abre Proyectos y tesis › Propuestas.', 'En «Grupos de 5° para alumnos nuevos», escribe los grupos disponibles.', 'Toca «Guardar grupos».']],
        ['id' => 'open-proposals', 'audience' => 'manager', 'module' => 'proyectos', 'question' => '¿Cómo abro el registro de propuestas?', 'keywords' => ['propuesta', 'registro', 'ventana', 'abrir', 'cerrar', 'fecha'], 'app' => '/proposals', 'web' => '/pages/admin/proposal-config.php',
            'steps' => ['Abre Proyectos y tesis › Propuestas.', 'En «Registro de propuestas», elige hasta cuándo y toca «Abrir».', 'Para terminar antes, toca «Cerrar ahora».']],
        ['id' => 'validators', 'audience' => 'manager', 'module' => 'proyectos', 'question' => '¿Quién valida las propuestas?', 'keywords' => ['validador', 'validar', 'propuesta', 'docente', 'revisar'], 'app' => '/proposals', 'web' => '/pages/admin/proposal-config.php',
            'steps' => ['Las validan la administración, la jefatura, la asistencia, la coordinación y los docentes elegidos.', 'Elige a los docentes en Proyectos y tesis › Propuestas › «Docentes validadores».', 'Toca «Guardar validadores».']],
        ['id' => 'users', 'audience' => 'manager', 'module' => 'usuarios', 'question' => '¿Cómo agrego o importo usuarios?', 'keywords' => ['usuario', 'alumno', 'docente', 'crear', 'alta', 'excel', 'importar'], 'app' => '/users', 'web' => '/pages/admin/users.php',
            'steps' => ['Abre Personas › Usuarios.', 'Usa «Nuevo usuario» para uno solo o «Excel» para importar varios con la plantilla.', 'La contraseña inicial es su matrícula; el sistema pide cambiarla al entrar.']],
        ['id' => 'advisors', 'audience' => 'manager', 'question' => '¿Cómo asigno asesores?', 'keywords' => ['asesor', 'asesoria', 'asignar', 'revisor', 'carga'], 'app' => '/advisors', 'web' => '/pages/admin/advisors.php',
            'steps' => ['Abre Personas › Asesores.', 'Elige el proyecto y el docente.', 'El docente recibe un aviso cuando lo asignas.']],
        ['id' => 'create-room', 'audience' => 'manager', 'module' => 'evaluaciones', 'question' => '¿Cómo programo una sala de evaluación?', 'keywords' => ['sala', 'turno', 'horario', 'programar', 'crear', 'evaluacion', 'reprogramar'], 'app' => '/evaluation-rooms', 'web' => '/pages/admin/evaluation-rooms.php',
            'steps' => ['Abre Proyectos y tesis › Salas y turnos y toca «Crear sala».', 'Elige salón, inicio y fin. El inicio no puede ser anterior al momento actual.', 'Agrega docentes y proyectos, y ordena los turnos.', 'Para reprogramar, usa el botón Horario de la sala; requiere autorización.']],
        ['id' => 'create-deliverable', 'audience' => 'academic', 'module' => 'entregables', 'question' => '¿Cómo creo un entregable con fecha límite?', 'keywords' => ['entregable', 'crear', 'nuevo', 'fecha limite', 'recordatorio'], 'app' => '/deliverables', 'web' => '/pages/admin/deliverables.php',
            'steps' => ['Abre Académico › Entregables y toca «Nuevo entregable».', 'Captura el nombre, la materia y la fecha límite.', 'Los alumnos inscritos reciben recordatorios 7, 3 y 1 día antes.']],
        ['id' => 'periods', 'audience' => 'academic', 'module' => 'academico', 'question' => '¿Cómo abro un periodo y promuevo a los alumnos?', 'keywords' => ['periodo', 'semestre', 'promocion', 'promover', 'abrir', 'conciliacion', 'cierre', 'irregulares'], 'app' => '/semesters', 'web' => '/pages/admin/semesters.php',
            'steps' => ['Abre Académico › Semestres y periodos.', 'Revisa la vista previa de la apertura automática: alumnos promovidos y por resolver.', 'Confirma con «Abrir periodo ahora» y resuelve los casos pendientes.']],
        ['id' => 'notices', 'audience' => 'admin', 'module' => 'configuracion', 'question' => '¿Cómo publico un aviso?', 'keywords' => ['aviso', 'anuncio', 'comunicado', 'publicar', 'mensaje'], 'app' => '/notices', 'web' => '/pages/admin/notices.php',
            'steps' => ['Abre Sistema › Avisos.', 'Escribe el mensaje y elige a quién va dirigido.', 'Si le pones fechas, el inicio no puede ser anterior a hoy.']],

        // ---------------------------------------- Administración general
        ['id' => 'switch-career', 'audience' => 'general', 'question' => '¿Cómo cambio de carrera o veo el sistema como otro nivel?', 'keywords' => ['carrera', 'cambiar', 'nivel', 'simular', 'jefatura', 'vista'],
            'steps' => ['Usa el selector de carrera de la barra superior.', 'Con el selector de nivel, ves la carrera como otro perfil en modo sólo lectura.', 'Para salir, toca «Volver a administrador general».']],
        ['id' => 'backups', 'audience' => 'general', 'question' => '¿Cómo genero o descargo un respaldo?', 'keywords' => ['respaldo', 'backup', 'base de datos', 'descargar', 'restaurar'], 'app' => '/backups', 'web' => '/pages/admin/database-backups.php',
            'steps' => ['Abre Seguridad y continuidad › Respaldos.', 'Genera uno nuevo o descarga uno existente.', 'Si Operaciones marca alerta de respaldo, genera uno cuanto antes.']],
        ['id' => 'sent-emails', 'audience' => 'general', 'question' => '¿Cómo sé a quién se le envió un correo?', 'keywords' => ['correo', 'email', 'enviado', 'destinatario', 'mail'], 'app' => '/sent-emails', 'web' => '/pages/admin/sent-emails.php',
            'steps' => ['Abre Seguridad y continuidad › Correos enviados.', 'Consulta el destinatario y el tipo de correo. El contenido no se guarda.', 'El registro se conserva 90 días.']],
        ['id' => 'operations', 'audience' => 'general', 'question' => '¿Dónde reviso las alertas del sistema?', 'keywords' => ['alerta', 'operacion', 'problema', 'error', 'continuidad', 'estado'], 'app' => '/operations', 'web' => '/pages/admin/operations.php',
            'steps' => ['Abre Operaciones.', 'Revisa las alertas críticas primero.', 'También aparecen en la campana, en la pestaña «Operación».']],
    ],
];
