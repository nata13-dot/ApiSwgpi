<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Support\CareerContext;
use Illuminate\Support\Facades\DB;

class CareerExportController extends Controller
{
    public function activeCareer()
    {
        $career = app(CareerContext::class)->career();
        abort_unless($career, 422, 'No existe una carrera activa.');

        $files = [
            'usuarios.csv' => [
                ['ID', 'Nombres', 'Apellido paterno', 'Apellido materno', 'Correo', 'Perfil', 'Activo'],
                DB::table('usuario_carrera as membership')
                    ->join('usuarios as user', 'user.id', '=', 'membership.usuario_id')
                    ->join('perfiles as profile', 'profile.id', '=', 'membership.perfil_id')
                    ->where('membership.carrera_id', $career->id)
                    ->orderBy('membership.perfil_id')->orderBy('user.nombres')
                    ->get(['user.id', 'user.nombres', 'user.apellido_paterno', 'user.apellido_materno', 'user.correo', 'profile.nombre as perfil', 'membership.activo'])
                    ->map(fn ($row) => (array) $row)->all(),
            ],
            'asignaturas.csv' => [
                ['Clave', 'Nombre', 'Descripción', 'Activa'],
                DB::table('asignaturas')->where('carrera_id', $career->id)->orderBy('clave')
                    ->get(['clave', 'nombre', 'descripcion', 'activo'])->map(fn ($row) => (array) $row)->all(),
            ],
            'grupos.csv' => [
                ['Nombre', 'Semestre', 'Grupo', 'Periodo', 'Asignaturas', 'Activo'],
                DB::table('grupos_academicos as group')
                    ->leftJoin('periodos_academicos as period', 'period.id', '=', 'group.periodo_id')
                    ->leftJoin('cursos as course', 'course.grupo_id', '=', 'group.id')
                    ->leftJoin('asignaturas as subject', 'subject.id', '=', 'course.asignatura_id')
                    ->where('group.carrera_id', $career->id)
                    ->groupBy('group.id', 'group.nombre', 'group.semestre', 'group.clave_grupo', 'period.nombre', 'group.activo')
                    ->orderBy('group.semestre')->orderBy('group.clave_grupo')
                    ->get([
                        'group.nombre', 'group.semestre', 'group.clave_grupo as grupo', 'period.nombre as periodo',
                        DB::raw("GROUP_CONCAT(subject.clave ORDER BY subject.clave SEPARATOR '|') as asignaturas"),
                        'group.activo',
                    ])->map(fn ($row) => (array) $row)->all(),
            ],
            'proyectos.csv' => [
                ['ID', 'Título', 'Tipo', 'Estado', 'Semestre', 'Grupo', 'Activo'],
                DB::table('proyectos as project')
                    ->leftJoin('grupos_academicos as group', 'group.id', '=', 'project.grupo_id')
                    ->where('project.carrera_id', $career->id)->orderBy('project.id')
                    ->get(['project.id', 'project.titulo', 'project.tipo', 'project.estado', 'group.semestre', 'group.clave_grupo as grupo', 'project.activo'])
                    ->map(fn ($row) => (array) $row)->all(),
            ],
            'indicadores.csv' => [
                ['Módulo', 'Clave', 'Nombre', 'Unidad', 'Valor actual', 'Meta', 'Activo'],
                DB::table('indicadores_carrera')->where('carrera_id', $career->id)->orderBy('modulo')->orderBy('nombre')
                    ->get(['modulo', 'clave', 'nombre', 'unidad', 'valor_actual', 'valor_meta', 'activo'])->map(fn ($row) => (array) $row)->all(),
            ],
            'modulos.csv' => [
                ['Módulo', 'Habilitado'],
                DB::table('carrera_modulos')->where('carrera_id', $career->id)->orderBy('modulo')
                    ->get(['modulo', 'habilitado'])->map(fn ($row) => (array) $row)->all(),
            ],
            'rubricas.csv' => [
                ['Semestre', 'Etapa', 'Rúbrica', 'Clave criterio', 'Pregunta', 'Orden', 'Activo'],
                DB::table('rubricas as rubric')
                    ->leftJoin('criterios_rubrica as criterion', 'criterion.rubrica_id', '=', 'rubric.id')
                    ->where('rubric.carrera_id', $career->id)
                    ->orderBy('rubric.semestre')->orderBy('criterion.orden')
                    ->get(['rubric.semestre', 'rubric.etapa', 'rubric.nombre as rubrica', 'criterion.clave', 'criterion.pregunta', 'criterion.orden', 'criterion.activo'])
                    ->map(fn ($row) => (array) $row)->all(),
            ],
        ];

        $temporary = tempnam(sys_get_temp_dir(), 'sgpi_export_');
        $zip = new \ZipArchive();
        abort_if($zip->open($temporary, \ZipArchive::OVERWRITE) !== true, 500, 'No se pudo generar la exportación.');
        foreach ($files as $name => [$headers, $rows]) {
            $zip->addFromString($name, $this->csv($headers, $rows));
        }
        $zip->addFromString('LEEME.txt', "Exportación SGPI\nCarrera: {$career->nombre} ({$career->clave})\nFecha: ".now()->toDateTimeString()."\nNo contiene contraseñas, tokens ni archivos privados.\n");
        $zip->close();

        return response()->download(
            $temporary,
            'sgpi_'.$career->clave.'_'.now()->format('Ymd_His').'.zip',
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    public function institutionalSummary()
    {
        $rows = Career::query()->withoutGlobalScopes()->orderBy('id')->get()->map(function (Career $career) {
            return [
                $career->clave,
                $career->nombre,
                DB::table('usuario_carrera')->where('carrera_id', $career->id)->where('activo', true)->count(),
                DB::table('usuario_carrera')->where('carrera_id', $career->id)->where('perfil_id', 1)->where('activo', true)->count(),
                DB::table('usuario_carrera')->where('carrera_id', $career->id)->where('perfil_id', 2)->where('activo', true)->count(),
                DB::table('usuario_carrera')->where('carrera_id', $career->id)->where('perfil_id', 3)->where('activo', true)->count(),
                DB::table('asignaturas')->where('carrera_id', $career->id)->where('activo', true)->count(),
                DB::table('grupos_academicos')->where('carrera_id', $career->id)->where('activo', true)->count(),
                DB::table('proyectos')->where('carrera_id', $career->id)->where('activo', true)->count(),
                DB::table('evaluaciones')->where('carrera_id', $career->id)->count(),
                DB::table('documentos')->where('carrera_id', $career->id)->count(),
            ];
        })->all();

        $content = $this->csv(
            ['Clave', 'Carrera', 'Personas', 'Administradores', 'Docentes', 'Estudiantes', 'Asignaturas', 'Grupos', 'Proyectos', 'Evaluaciones', 'Documentos'],
            $rows
        );

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="resumen_carreras_'.now()->format('Ymd_His').'.csv"',
        ]);
    }

    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row));
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
