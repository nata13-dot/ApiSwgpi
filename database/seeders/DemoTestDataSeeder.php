<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Project;
use App\Models\ProjectRegistrationWindow;
use App\Models\SubjectGroup;
use App\Models\TeacherGroupAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Prueba2026!';
        $now = now();

        User::updateOrCreate(
            ['id' => '0000000001'],
            [
                'nombres' => 'Administrador',
                'apa' => 'Sistema',
                'ama' => 'Principal',
                'email' => 'admin@sistema.com',
                'password' => Hash::make($password),
                'perfil_id' => 1,
                'activo' => true,
                'created_at' => $now,
            ]
        );

        $subjects = [
            ['clave' => 'SWGPI-FIS', 'nombre' => 'Fundamentos de Ingenieria de Software', 'descripcion' => 'Materia base para enfoque, alcance y nombre de propuestas.'],
            ['clave' => 'SWGPI-BD', 'nombre' => 'Base de Datos', 'descripcion' => 'Modelado y persistencia de datos.'],
            ['clave' => 'SWGPI-WEB', 'nombre' => 'Programacion Web', 'descripcion' => 'Construccion de aplicaciones web.'],
            ['clave' => 'SWGPI-GP', 'nombre' => 'Gestion de Proyectos de Software', 'descripcion' => 'Planeacion, seguimiento y control de proyectos.'],
        ];

        foreach ($subjects as $subject) {
            Asignatura::updateOrCreate(['clave' => $subject['clave']], $subject);
        }

        $fundamentos = Asignatura::where('clave', 'SWGPI-FIS')->first();
        $bd = Asignatura::where('clave', 'SWGPI-BD')->first();
        $web = Asignatura::where('clave', 'SWGPI-WEB')->first();
        $gestion = Asignatura::where('clave', 'SWGPI-GP')->first();

        $groups = [
            ['nombre' => '5to A - Propuestas 2026', 'semestre' => 5, 'periodo' => '2026-1', 'asignaturas' => [$fundamentos->id, $bd->id, $web->id]],
            ['nombre' => '5to B - Propuestas 2026', 'semestre' => 5, 'periodo' => '2026-1', 'asignaturas' => [$fundamentos->id, $gestion->id]],
            ['nombre' => '6to A - Desarrollo 2026', 'semestre' => 6, 'periodo' => '2026-2', 'asignaturas' => [$gestion->id, $web->id]],
        ];

        $groupModels = [];
        foreach ($groups as $item) {
            $group = SubjectGroup::updateOrCreate(
                ['nombre' => $item['nombre']],
                ['semestre' => $item['semestre'], 'periodo' => $item['periodo'], 'activo' => true]
            );
            $group->asignaturas()->sync($item['asignaturas']);
            $groupModels[$item['nombre']] = $group;
        }

        $teachers = [
            ['id' => 'D260001', 'nombres' => 'Alejandro', 'apa' => 'Ramos', 'ama' => 'Lopez', 'email' => 'docente01.demo@sgpi.test'],
            ['id' => 'D260002', 'nombres' => 'Beatriz', 'apa' => 'Mendez', 'ama' => 'Soto', 'email' => 'docente02.demo@sgpi.test'],
            ['id' => 'D260003', 'nombres' => 'Carlos', 'apa' => 'Herrera', 'ama' => 'Diaz', 'email' => 'docente03.demo@sgpi.test'],
            ['id' => 'D260004', 'nombres' => 'Daniela', 'apa' => 'Cruz', 'ama' => 'Vega', 'email' => 'docente04.demo@sgpi.test'],
            ['id' => 'D260005', 'nombres' => 'Eduardo', 'apa' => 'Salinas', 'ama' => 'Mora', 'email' => 'docente05.demo@sgpi.test'],
            ['id' => 'D260006', 'nombres' => 'Fernanda', 'apa' => 'Castillo', 'ama' => 'Reyes', 'email' => 'docente06.demo@sgpi.test'],
            ['id' => 'D260007', 'nombres' => 'Gabriel', 'apa' => 'Ortega', 'ama' => 'Nava', 'email' => 'docente07.demo@sgpi.test'],
            ['id' => 'D260008', 'nombres' => 'Helena', 'apa' => 'Paredes', 'ama' => 'Rios', 'email' => 'docente08.demo@sgpi.test'],
            ['id' => 'D260009', 'nombres' => 'Ivan', 'apa' => 'Campos', 'ama' => 'Silva', 'email' => 'docente09.demo@sgpi.test'],
            ['id' => 'D260010', 'nombres' => 'Julia', 'apa' => 'Navarro', 'ama' => 'Leon', 'email' => 'docente10.demo@sgpi.test'],
        ];

        foreach ($teachers as $teacher) {
            User::updateOrCreate(
                ['id' => $teacher['id']],
                array_merge($teacher, [
                    'password' => Hash::make($password),
                    'perfil_id' => 2,
                    'activo' => true,
                    'created_at' => $now,
                ])
            );
        }

        $students = [
            ['id' => 'S260001', 'nombres' => 'Ana', 'apa' => 'Garcia', 'ama' => 'Perez', 'email' => 'estudiante01.demo@sgpi.test', 'grupo' => 'A'],
            ['id' => 'S260002', 'nombres' => 'Bruno', 'apa' => 'Martinez', 'ama' => 'Ruiz', 'email' => 'estudiante02.demo@sgpi.test', 'grupo' => 'A'],
            ['id' => 'S260003', 'nombres' => 'Camila', 'apa' => 'Lopez', 'ama' => 'Torres', 'email' => 'estudiante03.demo@sgpi.test', 'grupo' => 'B'],
            ['id' => 'S260004', 'nombres' => 'Diego', 'apa' => 'Hernandez', 'ama' => 'Flores', 'email' => 'estudiante04.demo@sgpi.test', 'grupo' => 'B'],
            ['id' => 'S260005', 'nombres' => 'Elena', 'apa' => 'Sanchez', 'ama' => 'Morales', 'email' => 'estudiante05.demo@sgpi.test', 'grupo' => 'A'],
            ['id' => 'S260006', 'nombres' => 'Fabian', 'apa' => 'Ramirez', 'ama' => 'Cortes', 'email' => 'estudiante06.demo@sgpi.test', 'grupo' => 'A'],
            ['id' => 'S260007', 'nombres' => 'Grecia', 'apa' => 'Vargas', 'ama' => 'Medina', 'email' => 'estudiante07.demo@sgpi.test', 'grupo' => 'B'],
            ['id' => 'S260008', 'nombres' => 'Hugo', 'apa' => 'Jimenez', 'ama' => 'Aguilar', 'email' => 'estudiante08.demo@sgpi.test', 'grupo' => 'B'],
            ['id' => 'S260009', 'nombres' => 'Irene', 'apa' => 'Romero', 'ama' => 'Ponce', 'email' => 'estudiante09.demo@sgpi.test', 'grupo' => 'A'],
            ['id' => 'S260010', 'nombres' => 'Jorge', 'apa' => 'Fuentes', 'ama' => 'Luna', 'email' => 'estudiante10.demo@sgpi.test', 'grupo' => 'A'],
        ];

        foreach ($students as $student) {
            User::updateOrCreate(
                ['id' => $student['id']],
                array_merge($student, [
                    'password' => Hash::make($password),
                    'perfil_id' => 3,
                    'semestre' => 5,
                    'profile_completed_at' => $now,
                    'activo' => true,
                    'created_at' => $now,
                ])
            );
        }

        $proposalAssignments = [
            ['group' => '5to A - Propuestas 2026', 'teacher_id' => 'D260001'],
            ['group' => '5to A - Propuestas 2026', 'teacher_id' => 'D260002'],
            ['group' => '5to B - Propuestas 2026', 'teacher_id' => 'D260003'],
        ];

        foreach ($proposalAssignments as $item) {
            TeacherGroupAssignment::updateOrCreate(
                [
                    'subject_group_id' => $groupModels[$item['group']]->id,
                    'teacher_id' => $item['teacher_id'],
                ],
                [
                    'asignatura_id' => $fundamentos->id,
                    'labor' => 'Revision de propuesta: Fundamentos de Ingenieria de Software',
                    'activo' => true,
                ]
            );
        }

        foreach (['5to A - Propuestas 2026', '5to B - Propuestas 2026'] as $groupName) {
            ProjectRegistrationWindow::updateOrCreate(
                ['subject_group_id' => $groupModels[$groupName]->id, 'notes' => 'Ventana demo para registro de propuestas'],
                [
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addDays(15),
                    'activo' => true,
                ]
            );
        }

        $creator = '0000000001';
        $projects = [
            ['title' => 'Proyecto Demo 01 - Control de Inventario', 'group' => '5to A - Propuestas 2026', 'students' => ['S260001', 'S260002'], 'primary' => 'D260001', 'secondary' => 'D260002', 'company' => 'Abarrotes La Central'],
            ['title' => 'Proyecto Demo 02 - Seguimiento Academico', 'group' => '5to B - Propuestas 2026', 'students' => ['S260003', 'S260004'], 'primary' => 'D260003', 'secondary' => 'D260004', 'company' => 'Academia Texmelucan'],
            ['title' => 'Proyecto Demo 03 - Gestion de Talleres', 'group' => '6to A - Desarrollo 2026', 'students' => ['S260005', 'S260006'], 'primary' => 'D260005', 'secondary' => 'D260006', 'company' => 'Talleres Rivera'],
        ];

        foreach ($projects as $item) {
            $group = $groupModels[$item['group']];
            $studentNames = User::whereIn('id', $item['students'])
                ->get(['nombres', 'apa', 'ama'])
                ->map(fn (User $user) => trim("{$user->nombres} {$user->apa} {$user->ama}"))
                ->implode(', ');

            $project = Project::updateOrCreate(
                ['title' => $item['title']],
                [
                    'description' => 'Proyecto de prueba generado para validar flujos del SGPI.',
                    'semestre' => $group->semestre,
                    'subject_group_id' => $group->id,
                    'year' => 2026,
                    'authors' => $studentNames,
                    'company_name' => $item['company'],
                    'company_contact_name' => 'Responsable Demo',
                    'company_contact_position' => 'Gerente',
                    'company_address' => 'Direccion demo del beneficiario',
                    'proposal_status' => 'pendiente',
                    'created_by' => $creator,
                    'activo' => true,
                ]
            );
            $project->asignaturas()->sync($group->asignaturas()->pluck('asignaturas.id')->all());

            DB::table('project_user')->where('project_id', $project->id)->delete();

            foreach ($item['students'] as $studentId) {
                DB::table('project_user')->insert(['project_id' => $project->id, 'user_id' => $studentId, 'rol_asesor' => null]);
            }

            DB::table('project_user')->insert(['project_id' => $project->id, 'user_id' => $item['primary'], 'rol_asesor' => 'primario']);
            DB::table('project_user')->insert(['project_id' => $project->id, 'user_id' => $item['secondary'], 'rol_asesor' => 'secundario']);
        }
    }
}