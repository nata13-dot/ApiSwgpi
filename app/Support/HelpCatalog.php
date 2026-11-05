<?php

namespace App\Support;

/** Temas del asistente de ayuda, escritos a mano en resources/help. Una sola fuente para la app y la web. */
final class HelpCatalog
{
    public const CATEGORIES = [
        'situacion' => 'Mi situación',
        'acceso' => 'Entrar al sistema',
        'registro' => 'Crear mi cuenta',
        'proyecto' => 'Proyectos y propuestas',
        'entregables' => 'Entregables',
        'evaluacion' => 'Evaluaciones y salas',
        'seguimiento' => 'Residencias',
        'personas' => 'Personas',
        'academico' => 'Académico',
        'sistema' => 'Sistema',
        'institucional' => 'Institucional',
        'cuenta' => 'Mi cuenta y uso',
        'problemas' => 'Tengo un problema',
    ];

    private const FILES = ['guest', 'common', 'student', 'teacher', 'management', 'general'];

    /** @return array<string, array> Por identificador. */
    public static function topics(): array
    {
        static $topics = null;
        if ($topics === null) {
            $topics = [];
            foreach (self::FILES as $file) {
                foreach (require resource_path("help/{$file}.php") as $topic) {
                    $topics[$topic['id']] = $topic;
                }
            }
        }

        return $topics;
    }
}
