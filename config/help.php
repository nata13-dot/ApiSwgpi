<?php

/*
 * Guía de inicio, común para la app y la web. Los temas del asistente están en resources/help.
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
];
