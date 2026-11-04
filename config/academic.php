<?php

return [
    // Abre y cierra los periodos por fecha con la tarea programada. La vista previa y el botón funcionan aunque esté apagado.
    'period_automation' => (bool) env('ACADEMIC_PERIOD_AUTOMATION', false),
];
