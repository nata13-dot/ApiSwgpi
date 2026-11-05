<?php

/*
 * Avisos que cada persona puede apagar en Mi perfil. Lo que requiere una acción suya (calificaciones,
 * cambios de sala, solicitudes por atender) siempre llega y por eso no aparece aquí.
 * profiles: 2 docente, 3 alumno, 5 jefatura, 6 asistencia, 7 coordinación.
 */
return [
    'optional' => [
        'recordatorio_entrega' => ['label' => 'Recordatorios de entregas', 'hint' => '7, 3 y 1 días antes de la fecha límite, y si ya venció.', 'profiles' => [3]],
        'recordatorio_evaluacion' => ['label' => 'Recordatorios de evaluaciones', 'hint' => '7, 3 y 1 días antes de tu presentación o de tu sala.', 'profiles' => [2, 3, 5, 6, 7]],
        'resumen_revision' => ['label' => 'Resumen de trabajos por revisar', 'hint' => 'Cada lunes: entregas sin calificar y evaluaciones sin capturar.', 'profiles' => [2, 5, 6, 7]],
        'resumen_semanal' => ['label' => 'Resumen semanal de la carrera', 'hint' => 'Cada lunes: atrasos, solicitudes y salas próximas.', 'profiles' => [5, 6, 7]],
    ],

    // Cambios del mismo día: llegan aunque sea de noche.
    'urgent' => ['sala_reprogramada', 'turno_proximo'],

    'quiet_hours' => ['from' => 22, 'to' => 7],
];
