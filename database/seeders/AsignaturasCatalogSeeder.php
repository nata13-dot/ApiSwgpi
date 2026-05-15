<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsignaturasCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['clave' => 'AC001', 'nombre' => 'Calculo Diferencial'],
            ['clave' => 'AC002', 'nombre' => 'Calculo Integral'],
            ['clave' => 'AC003', 'nombre' => 'Algebra Lineal'],
            ['clave' => 'AC004', 'nombre' => 'Calculo Vectorial'],
            ['clave' => 'AC005', 'nombre' => 'Ecuaciones Diferenciales'],
            ['clave' => 'AC006', 'nombre' => 'Fundamentos de Investigacion'],
            ['clave' => 'AC007', 'nombre' => 'Taller de Etica'],
            ['clave' => 'AC008', 'nombre' => 'Desarrollo Sustentable'],
            ['clave' => 'AC009', 'nombre' => 'Taller de Investigacion I'],
            ['clave' => 'AC010', 'nombre' => 'Taller de Investigacion II'],
            ['clave' => null, 'nombre' => 'Administración de Base de Datos'],
            ['clave' => null, 'nombre' => 'Administración de redes'],
            ['clave' => 'AE008', 'nombre' => 'Contabilidad Financiera'],
            ['clave' => 'AE026', 'nombre' => 'Estructura de Datos'],
            ['clave' => 'AE031', 'nombre' => 'Fundamentos de Base de Datos'],
            ['clave' => 'AE034', 'nombre' => 'Fundamentos de Telecomunicaciones'],
            ['clave' => 'AE041', 'nombre' => 'Matematicas Discretas'],
            ['clave' => 'AE052', 'nombre' => 'Probabilidad y Estadistica'],
            ['clave' => 'AE055', 'nombre' => 'Programacion Web'],
            ['clave' => 'AE058', 'nombre' => 'Quimica'],
            ['clave' => 'AE061', 'nombre' => 'Sistemas Operativos I'],
            ['clave' => 'AE085', 'nombre' => 'Fundamentos de Programacion'],
            ['clave' => 'AE086', 'nombre' => 'Programacion Orientada a Objetos'],
            ['clave' => null, 'nombre' => 'Arquitectura de Computadoras'],
            ['clave' => null, 'nombre' => 'Conmutación y Enrutamiento en Redes de Datos'],
            ['clave' => null, 'nombre' => 'Cultura Empresarial'],
            ['clave' => null, 'nombre' => 'Especialidad Minería de datos y Aprendizaje automático'],
            ['clave' => null, 'nombre' => 'Especialidad Programación Móvil (OP)'],
            ['clave' => null, 'nombre' => 'Especialidad Tecnologías emergentes para control de API´S'],
            ['clave' => null, 'nombre' => 'Especialidad Tópicos selectos de desarrollo Web'],
            ['clave' => null, 'nombre' => 'Física General'],
            ['clave' => null, 'nombre' => 'Fundamentos de Ingeniería de Software'],
            ['clave' => null, 'nombre' => 'Gestión de Proyectos de Software'],
            ['clave' => null, 'nombre' => 'Graficación'],
            ['clave' => null, 'nombre' => 'Ingeniería de Software'],
            ['clave' => null, 'nombre' => 'Inteligencia Artificial'],
            ['clave' => null, 'nombre' => 'Investigación de operaciones'],
            ['clave' => null, 'nombre' => 'Lenguajes de Interfaz'],
            ['clave' => null, 'nombre' => 'Lenguajes y Autómatas I'],
            ['clave' => null, 'nombre' => 'Lenguajes y Autómatas II'],
            ['clave' => null, 'nombre' => 'Principios Eléctricos y Aplicaciones Digitales'],
            ['clave' => null, 'nombre' => 'Programación Lógica y Funcional'],
            ['clave' => null, 'nombre' => 'Redes de Computadoras'],
            ['clave' => 'SCC-1017', 'nombre' => 'Métodos numéricos'],
            ['clave' => 'SCD-1027', 'nombre' => 'Tópicos Avanzados de Programación'],
            ['clave' => null, 'nombre' => 'Simulación'],
            ['clave' => null, 'nombre' => 'Sistemas Programables'],
            ['clave' => null, 'nombre' => 'Taller de Administración'],
            ['clave' => null, 'nombre' => 'Taller de base de datos'],
            ['clave' => null, 'nombre' => 'Taller de Sistemas Operativos'],
        ];

        DB::table('subject_group_asignatura')->delete();
        DB::table('project_asignatura')->delete();
        DB::table('teacher_group_assignments')->update(['asignatura_id' => null]);
        DB::table('competencias')->delete();
        DB::table('asignaturas')->delete();
        DB::statement('ALTER TABLE asignaturas AUTO_INCREMENT = 1');

        $now = now();
        DB::table('asignaturas')->insert(array_map(fn ($subject) => [
            'clave' => $subject['clave'],
            'nombre' => $subject['nombre'],
            'descripcion' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $subjects));
    }
}
