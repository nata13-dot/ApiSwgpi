<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MulticareerIntegrityService
{
    public function report(): array
    {
        $checks = collect([
            $this->check('project_group', 'Proyectos y grupos', 'Proyectos asociados a un grupo de otra carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM proyectos p
                JOIN grupos_academicos g ON g.id = p.grupo_id
                WHERE p.carrera_id <> g.carrera_id
            SQL),
            $this->check('course_group', 'Cursos y grupos', 'Cursos cuya carrera no coincide con su grupo.', <<<'SQL'
                SELECT COUNT(*) total
                FROM cursos c
                JOIN grupos_academicos g ON g.id = c.grupo_id
                WHERE c.carrera_id <> g.carrera_id
            SQL),
            $this->check('course_subject', 'Cursos y asignaturas', 'Cursos que contienen asignaturas de otra carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM cursos c
                JOIN asignaturas a ON a.id = c.asignatura_id
                WHERE c.carrera_id <> a.carrera_id
            SQL),
            $this->check('evaluation_project', 'Evaluaciones y proyectos', 'Evaluaciones ligadas a proyectos de otra carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM evaluaciones e
                JOIN proyectos p ON p.id = e.proyecto_id
                WHERE e.carrera_id <> p.carrera_id
            SQL),
            $this->check('evaluation_room', 'Evaluaciones y salas', 'Evaluaciones asignadas a salas de otra carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM evaluaciones e
                JOIN salas_evaluacion s ON s.id = e.sala_id
                WHERE e.carrera_id <> s.carrera_id
            SQL),
            $this->check('document_project', 'Documentos y proyectos', 'Documentos cuyo proyecto pertenece a otra carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM documentos d
                JOIN proyectos p ON p.id = d.proyecto_id
                WHERE d.carrera_id <> p.carrera_id
            SQL),
            $this->check('rubric_project', 'Rúbricas personalizadas', 'Criterios personalizados ligados a proyectos de otra carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM criterios_rubrica criterion
                JOIN rubricas rubric ON rubric.id = criterion.rubrica_id
                JOIN proyectos project ON project.id = criterion.proyecto_id
                WHERE rubric.carrera_id <> project.carrera_id
            SQL),
            $this->check('student_group_membership', 'Estudiantes y grupos', 'Estudiantes activos en grupos sin membresía estudiantil en esa carrera.', <<<'SQL'
                SELECT COUNT(*) total
                FROM grupo_estudiantes enrollment
                JOIN grupos_academicos academic_group ON academic_group.id = enrollment.grupo_id
                LEFT JOIN usuario_carrera membership
                    ON membership.usuario_id = enrollment.estudiante_id
                    AND membership.carrera_id = academic_group.carrera_id
                    AND membership.perfil_id = 3
                    AND membership.activo = 1
                WHERE enrollment.activo = 1 AND membership.id IS NULL
            SQL),
            $this->check('project_member_membership', 'Integrantes y proyectos', 'Integrantes sin membresía activa en la carrera del proyecto.', <<<'SQL'
                SELECT COUNT(*) total
                FROM proyecto_integrantes member
                JOIN proyectos project ON project.id = member.proyecto_id
                JOIN usuarios user ON user.id = member.usuario_id
                LEFT JOIN usuario_carrera membership
                    ON membership.usuario_id = member.usuario_id
                    AND membership.carrera_id = project.carrera_id
                    AND membership.activo = 1
                WHERE user.activo = 1 AND membership.id IS NULL
            SQL),
            $this->check('duplicate_student_careers', 'Membresías estudiantiles', 'Estudiantes con más de una carrera activa.', <<<'SQL'
                SELECT COUNT(*) total FROM (
                    SELECT usuario_id
                    FROM usuario_carrera
                    WHERE perfil_id = 3 AND activo = 1
                    GROUP BY usuario_id
                    HAVING COUNT(*) > 1
                ) duplicates
            SQL),
            $this->check('career_settings', 'Configuración por carrera', 'Carreras que no tienen las cinco configuraciones funcionales mínimas.', <<<'SQL'
                SELECT COUNT(*) total FROM (
                    SELECT career.id
                    FROM carreras career
                    LEFT JOIN configuraciones_carrera setting ON setting.carrera_id = career.id
                    GROUP BY career.id
                    HAVING COUNT(setting.clave) < 5
                ) incomplete
            SQL),
        ]);

        return [
            'healthy' => $checks->every(fn ($check) => $check['count'] === 0),
            'violations' => $checks->sum('count'),
            'checks_total' => $checks->count(),
            'checks_passed' => $checks->where('count', 0)->count(),
            'generated_at' => now()->toIso8601String(),
            'checks' => $checks->values(),
        ];
    }

    public function store(array $report, string $source = 'manual', ?string $actorId = null): int
    {
        $source = in_array($source, ['manual', 'programado', 'consola'], true) ? $source : 'consola';

        return (int) DB::table('ejecuciones_integridad')->insertGetId([
            'ejecutado_por' => $actorId,
            'origen' => $source,
            'saludable' => $report['healthy'],
            'incidencias' => $report['violations'],
            'verificaciones_correctas' => $report['checks_passed'],
            'verificaciones_totales' => $report['checks_total'],
            'reporte' => json_encode($report['checks'], JSON_UNESCAPED_UNICODE),
            'creado_en' => now(),
        ]);
    }

    private function check(string $key, string $name, string $description, string $sql): array
    {
        $count = (int) (DB::selectOne($sql)->total ?? 0);

        return compact('key', 'name', 'description', 'count') + [
            'status' => $count === 0 ? 'ok' : 'warning',
        ];
    }
}
