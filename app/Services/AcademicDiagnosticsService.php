<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only preflight; never repairs or selects an academic source automatically. */
class AcademicDiagnosticsService
{
    public function report(): array
    {
        $baseline = [
            'usuarios' => ['id', 'perfil_id', 'activo'], 'usuario_carrera' => ['usuario_id', 'carrera_id', 'perfil_id', 'activo'],
            'carreras' => ['id', 'activa'], 'periodos_academicos' => ['id', 'nombre', 'fecha_inicio', 'fecha_fin'],
            'grupos_academicos' => ['id', 'carrera_id', 'periodo_id', 'semestre', 'clave_grupo'],
            'grupo_estudiantes' => ['grupo_id', 'estudiante_id', 'activo'],
            'cursos' => ['id', 'grupo_id', 'carrera_id', 'es_seguimiento_proyecto', 'clave_autoregistro'],
            'curso_estudiantes' => ['curso_id', 'estudiante_id', 'activo'], 'curso_docentes' => ['curso_id', 'docente_id', 'activo'],
            'proyectos' => ['id', 'grupo_id', 'carrera_id', 'modalidad'], 'documentos' => ['id', 'categoria', 'proyecto_id'],
            'documento_versiones' => ['id', 'documento_id', 'numero_version', 'disco', 'checksum_sha256'],
            'salas_evaluacion' => ['id', 'carrera_id', 'rubrica_id', 'inicia_en', 'termina_en'],
            'evaluaciones' => ['id', 'sala_id', 'proyecto_id'], 'dictamenes_docentes' => ['id', 'evaluacion_id', 'docente_id'],
            'respuestas_evaluacion' => ['dictamen_id', 'criterio_id', 'valor', 'puntaje'],
        ];
        $patch = [
            'carrera_periodos' => ['estado', 'conciliado'], 'matriculas_academicas' => ['grupo_id', 'version'],
            'proyecto_periodos' => ['grupo_id', 'periodo_id'], 'operaciones_academicas' => ['huella', 'resultado'],
            'movimientos_academicos' => ['origen_id', 'destino_id', 'estudiante_id'],
            'autorizaciones_academicas' => ['periodo_id', 'version', 'consumida_en'], 'bitacora_academica' => ['antes', 'despues'],
            'salones_institucionales' => ['clave'], 'recursos_agenda' => ['id'], 'entrega_participantes' => ['entrega_id', 'estudiante_id'],
            'residencias' => ['proyecto_id', 'estudiante_id', 'version'], 'residencia_versiones' => ['numero', 'ruta', 'sha256'],
            'ejecuciones_academicas' => ['estado'], 'avisos_academicos' => ['clave'],
            'evaluaciones' => ['autorizacion_id', 'presentacion_autorizacion_id', 'criterios_snapshot', 'modo_puntaje'],
            'salas_evaluacion' => ['periodo_id', 'salon_id'], 'documentos' => ['categoria_detalle'],
            'liberaciones_documentos_evaluacion' => ['documento_repositorio_id', 'alumno_id'],
        ];
        $missing = function (array $tables): array {
            $result = [];
            foreach ($tables as $table => $columns) {
                if (!Schema::hasTable($table)) { $result[] = $table; continue; }
                foreach ($columns as $column) if (!Schema::hasColumn($table, $column)) $result[] = $table.'.'.$column;
            }
            return $result;
        };
        $baselineMissing = $missing($baseline); $patchMissing = $missing($patch);
        $counts = [];
        foreach (['usuarios', 'proyectos', 'curso_estudiantes', 'entregas', 'documentos', 'documento_versiones', 'evaluaciones', 'dictamenes_docentes', 'respuestas_evaluacion'] as $table) {
            if (Schema::hasTable($table)) $counts[$table] = DB::table($table)->count();
        }
        return [
            'engine' => DB::connection()->getDriverName(), 'baseline_ready' => !$baselineMissing,
            'missing_baseline' => $baselineMissing, 'missing_consolidation' => $patchMissing, 'counts' => $counts,
            'careers_pending_reconciliation' => Schema::hasTable('carrera_periodos') ? DB::table('carrera_periodos')->where('conciliado', false)->count() : null,
            'rooms_pending_reconciliation' => Schema::hasColumns('salas_evaluacion', ['periodo_id', 'salon_id']) ? DB::table('salas_evaluacion')->whereNull('periodo_id')->orWhereNull('salon_id')->count() : null,
            'last_scheduler_run' => Schema::hasTable('ejecuciones_academicas') ? DB::table('ejecuciones_academicas')->orderByDesc('id')->first() : null,
        ];
    }
}
