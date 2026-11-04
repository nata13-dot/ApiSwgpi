<?php

namespace App\Services;

use App\Support\CareerScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abre y cierra los periodos por fecha, sin conciliación manual. Enero–junio y julio–diciembre existen solos.
 * Al abrir, cada alumno con un grupo claro sube de semestre conservando su letra y sus proyectos activos
 * pasan con él; los casos dudosos no detienen a nadie: quedan en una lista corta para la jefatura.
 * Al llegar la fecha de fin el periodo concluye aunque haya pendientes, que se avisan y no se borran.
 * Nunca toca una matrícula ya existente ni reabre un periodo cerrado.
 */
class AcademicPeriodAutomationService
{
    /** Lo que haría hoy para la carrera, sin guardar nada. */
    public function plan(int $career, ?Carbon $today = null): array
    {
        $today ??= today();
        $target = $this->currentPeriod($today);
        $half = $this->halfDates($today);
        $start = $target ? Carbon::parse($target->fecha_inicio) : $half['start'];
        $state = $target ? DB::table('carrera_periodos')->where(['carrera_id' => $career, 'periodo_id' => $target->id])->first() : null;
        $allowed = $this->allowedSemesters($career, $start);
        $toClose = DB::table('carrera_periodos as cp')->join('periodos_academicos as p', 'p.id', '=', 'cp.periodo_id')
            ->where('cp.carrera_id', $career)->whereIn('cp.estado', ['abierto', 'en_cierre'])->whereDate('p.fecha_fin', '<', $today)
            ->get(['p.id', 'p.nombre']);

        $students = DB::table('usuario_carrera as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.carrera_id', $career)->where('m.perfil_id', 3)->where('m.activo', true)->where('u.activo', true)
            ->orderBy('u.apellido_paterno')->orderBy('u.nombres')->get(['u.id', 'u.nombres', 'u.apellido_paterno']);
        $history = $this->groupHistory($career, $students->pluck('id')->all());
        $enrolled = $target ? CareerScope::enrollments($career, $target->id)->pluck('m.estudiante_id')->flip() : collect();
        $existingGroups = $target ? DB::table('grupos_academicos')->where('carrera_id', $career)->where('periodo_id', $target->id)->where('activo', true)->get(['id', 'semestre', 'clave_grupo', 'nombre']) : collect();

        $moves = [];
        $unplaced = [];
        $already = 0;
        foreach ($students as $student) {
            if ($enrolled->has($student->id)) {
                $already++;
                continue;
            }
            $name = trim("{$student->nombres} {$student->apellido_paterno}");
            $decision = $this->decide($history[$student->id] ?? [], $target?->id, $allowed, $start);
            if (isset($decision['reason'])) {
                $unplaced[] = ['student_id' => $student->id, 'name' => $name, 'reason' => $decision['reason']];
            } else {
                $moves[] = ['student_id' => $student->id, 'name' => $name] + $decision;
            }
        }

        $newGroups = collect($moves)->map(fn ($move) => ['semestre' => $move['to'], 'clave' => $move['clave']])->unique(fn ($group) => "{$group['semestre']}|{$group['clave']}")
            ->reject(fn ($group) => $existingGroups->contains(fn ($row) => (int) $row->semestre === $group['semestre'] && $row->clave_grupo === $group['clave']))
            ->sortBy(fn ($group) => sprintf('%02d%s', $group['semestre'], $group['clave']))->values()->all();
        $open = in_array($state?->estado, ['abierto', 'en_cierre'], true);

        $plan = [
            'career' => $career,
            'today' => $today->toDateString(),
            'period' => $target ? ['id' => $target->id, 'nombre' => $target->nombre, 'exists' => true] : ['id' => null, 'nombre' => $half['name'], 'exists' => false],
            'state' => $state?->estado,
            'can_apply' => !$open && $state?->estado !== 'cerrado',
            'close' => $toClose->map(fn ($period) => ['id' => $period->id, 'nombre' => $period->nombre, 'pending' => $this->pendingAtClose($career, (int) $period->id)])->values()->all(),
            'new_groups' => $newGroups,
            'moves' => $moves,
            'unplaced' => $unplaced,
            'already_enrolled' => $already,
        ];

        return $plan + ['snapshot' => hash('sha256', json_encode($plan))];
    }

    /** Ejecuta el plan de la carrera. Devuelve el plan aplicado, o null si no había nada que abrir. */
    public function apply(int $career, ?string $actorId = null, ?string $expectedSnapshot = null, ?Carbon $today = null): ?array
    {
        $today ??= today();

        return DB::transaction(function () use ($career, $actorId, $expectedSnapshot, $today) {
            DB::table('carreras')->where('id', $career)->lockForUpdate()->first();
            $plan = $this->plan($career, $today);
            abort_if($expectedSnapshot && !hash_equals($plan['snapshot'], $expectedSnapshot), 409, 'Los datos cambiaron. Revisa de nuevo la vista previa.');
            if (!$plan['can_apply'] && !$plan['close']) {
                return null;
            }

            $this->closeFinished($career, $plan['close'], $actorId);
            if (!$plan['can_apply']) {
                AcademicWorkflowService::forgetPeriods();

                return $plan;
            }

            $periodId = $plan['period']['id'] ?? $this->createPeriod($today);
            $groups = $this->ensureGroups($career, $periodId, $plan['new_groups']);
            $operation = (string) Str::uuid();
            DB::table('operaciones_academicas')->insert([
                'id' => $operation, 'carrera_id' => $career, 'periodo_id' => $periodId, 'tipo' => 'apertura_automatica',
                'actor_id' => $actorId ?? $this->systemActor(), 'huella' => $plan['snapshot'], 'resultado' => json_encode(null),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($plan['moves'] as $move) {
                $group = $groups["{$move['to']}|{$move['clave']}"];
                DB::table('matriculas_academicas')->insert(['estudiante_id' => $move['student_id'], 'grupo_id' => $group, 'estado' => 'activa', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                if ((int) $move['source_period'] !== (int) $periodId) {
                    DB::table('movimientos_academicos')->insertOrIgnore([
                        'origen_id' => $move['source_period'], 'destino_id' => $periodId, 'estudiante_id' => $move['student_id'], 'operacion_id' => $operation,
                        'accion' => $move['from'] === 9 ? 'permanencia' : 'avance', 'motivo' => 'Promoción automática al abrir el periodo', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->carryProjects($move['student_id'], (int) $group, (int) $periodId);
            }
            $this->linkRoomsToPeriods($career);

            DB::table('carrera_periodos')->updateOrInsert(['carrera_id' => $career, 'periodo_id' => $periodId],
                ['estado' => 'abierto', 'conciliado' => true, 'created_at' => now(), 'updated_at' => now()]);
            $result = ['promoted' => count($plan['moves']), 'unplaced' => count($plan['unplaced']), 'new_groups' => count($plan['new_groups'])];
            DB::table('operaciones_academicas')->where('id', $operation)->update(['resultado' => json_encode($result), 'updated_at' => now()]);
            $this->log($career, $actorId, 'periodo.apertura_automatica', 'periodo', $periodId, null, $result);
            if ($plan['unplaced']) {
                $count = count($plan['unplaced']);
                $this->notifyManagers($career, 'alumnos_por_acomodar', 'Alumnos por acomodar', "{$count} alumno(s) quedaron sin grupo en {$plan['period']['nombre']}. Asígnalos en Académico → Semestres y periodos.");
            }
            AcademicWorkflowService::forgetPeriods();

            return $plan;
        }, 3);
    }

    /** Alumnos activos sin matrícula en el periodo abierto, con el motivo si se puede saber. */
    public function unplaced(int $career, int $periodId): array
    {
        $enrolled = CareerScope::enrollments($career, $periodId)->pluck('m.estudiante_id')->flip();
        $students = DB::table('usuario_carrera as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.carrera_id', $career)->where('m.perfil_id', 3)->where('m.activo', true)->where('u.activo', true)
            ->orderBy('u.apellido_paterno')->orderBy('u.nombres')->get(['u.id', 'u.nombres', 'u.apellido_paterno'])
            ->reject(fn ($student) => $enrolled->has($student->id))->values();
        $period = DB::table('periodos_academicos')->find($periodId);
        $start = Carbon::parse($period->fecha_inicio);
        $allowed = $this->allowedSemesters($career, $start);
        $history = $this->groupHistory($career, $students->pluck('id')->all());

        return $students->map(function ($student) use ($history, $periodId, $allowed, $start) {
            $decision = $this->decide($history[$student->id] ?? [], $periodId, $allowed, $start);

            return ['student_id' => $student->id, 'name' => trim("{$student->nombres} {$student->apellido_paterno}"), 'reason' => $decision['reason'] ?? 'Sin matrícula en este periodo',
                'suggestion' => isset($decision['reason']) ? null : "{$decision['to']}° {$decision['clave']}"];
        })->all();
    }

    private function decide(array $records, ?int $targetId, array $allowed, Carbon $start): array
    {
        if ($records === []) {
            return ['reason' => 'No tiene ningún grupo anterior'];
        }
        // Sólo cuenta el periodo más reciente: lo anterior ya es historia del alumno.
        $latest = max(array_column($records, 'starts'));
        // Sin grupo en todo un periodo no se sabe qué cursó: promoverlo sería adivinar.
        if (Carbon::parse($latest)->lt($start->copy()->subMonths(7))) {
            return ['reason' => 'Su último grupo es de hace más de un periodo'];
        }
        $current = array_filter($records, fn ($record) => $record['starts'] === $latest);
        // La matrícula es el dato vigente: un registro antiguo del mismo periodo no la contradice.
        $enrolled = array_filter($current, fn ($record) => $record['enrolled']);
        if ($enrolled !== []) {
            $current = $enrolled;
        }
        $results = [];
        foreach ($current as $record) {
            $semester = $record['semestre'];
            if (!$semester || str_starts_with((string) $record['clave'], 'SIN')) {
                $results["sin|{$record['group']}"] = ['reason' => "Su grupo «{$record['nombre']}» no indica semestre o letra"];
                continue;
            }
            if ((int) $record['period'] === $targetId && in_array($semester, $allowed, true)) {
                $to = $semester; // Ya está en un grupo válido de este periodo: sólo falta su matrícula.
            } elseif ($semester === 9) {
                $to = 9;
            } elseif (in_array($semester + 1, $allowed, true)) {
                $to = $semester + 1;
            } else {
                $results["no|{$record['group']}"] = ['reason' => "Su grupo «{$record['nombre']}» ({$semester}°) no corresponde a un semestre de este periodo"];
                continue;
            }
            $results["{$to}|{$record['clave']}"] = ['from' => $semester, 'to' => $to, 'clave' => $record['clave'], 'source_period' => (int) $record['period'], 'source_group' => $record['nombre']];
        }

        if (count($results) > 1) {
            return ['reason' => 'Aparece en grupos que no coinciden: '.implode(', ', array_unique(array_column($current, 'nombre')))];
        }

        return reset($results);
    }

    /** @return array<string, array<int, array>> grupos conocidos de cada alumno: matrículas y registros anteriores */
    private function groupHistory(int $career, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }
        $columns = ['g.id as group', 'g.nombre', 'g.semestre', 'g.clave_grupo as clave', 'g.periodo_id as period', 'p.fecha_inicio as starts'];
        $enrollments = DB::table('matriculas_academicas as e')->join('grupos_academicos as g', 'g.id', '=', 'e.grupo_id')->join('periodos_academicos as p', 'p.id', '=', 'g.periodo_id')
            ->where('g.carrera_id', $career)->where('e.estado', 'activa')->whereIn('e.estudiante_id', $studentIds)->get(array_merge($columns, ['e.estudiante_id']));
        $legacy = DB::table('grupo_estudiantes as e')->join('grupos_academicos as g', 'g.id', '=', 'e.grupo_id')->join('periodos_academicos as p', 'p.id', '=', 'g.periodo_id')
            ->where('g.carrera_id', $career)->where('e.activo', true)->whereIn('e.estudiante_id', $studentIds)->get(array_merge($columns, ['e.estudiante_id']));

        $history = [];
        foreach ([[$legacy, false], [$enrollments, true]] as [$rows, $enrolled]) {
            foreach ($rows as $row) {
                $history[$row->estudiante_id][$row->group] = ['group' => (int) $row->group, 'nombre' => $row->nombre, 'semestre' => $row->semestre === null ? null : (int) $row->semestre,
                    'clave' => $row->clave, 'period' => (int) $row->period, 'starts' => (string) $row->starts, 'enrolled' => $enrolled];
            }
        }

        return array_map('array_values', $history);
    }

    /** Semestres de la mitad del año: pares en enero–junio, impares en julio–diciembre. */
    private function allowedSemesters(int $career, Carbon $start): array
    {
        $semesters = app(SemesterManagementService::class)->careerSemesters($career);
        $parity = $start->month <= 6 ? 0 : 1;

        return array_values(array_filter($semesters, fn ($semester) => $semester % 2 === $parity));
    }

    private function currentPeriod(Carbon $today): ?object
    {
        return DB::table('periodos_academicos')->whereDate('fecha_inicio', '<=', $today)->whereDate('fecha_fin', '>=', $today)
            ->orderByDesc('activo')->orderByDesc('id')->first();
    }

    private function halfDates(Carbon $today): array
    {
        $first = $today->month <= 6;

        return [
            'name' => $today->year.($first ? '-1' : '-2'),
            'start' => Carbon::create($today->year, $first ? 1 : 7, 1),
            'end' => Carbon::create($today->year, $first ? 6 : 12, $first ? 30 : 31),
        ];
    }

    private function createPeriod(Carbon $today): int
    {
        $half = $this->halfDates($today);
        // Otra carrera pudo crearlo en esta misma vuelta.
        if ($existing = $this->currentPeriod($today)) {
            return (int) $existing->id;
        }

        return (int) DB::table('periodos_academicos')->insertGetId(['nombre' => $half['name'], 'fecha_inicio' => $half['start']->toDateString(), 'fecha_fin' => $half['end']->toDateString(), 'activo' => true]);
    }

    /** @return array<string, int> id del grupo por "semestre|letra" */
    private function ensureGroups(int $career, int $periodId, array $newGroups): array
    {
        foreach ($newGroups as $group) {
            DB::table('grupos_academicos')->insert(['carrera_id' => $career, 'periodo_id' => $periodId, 'nombre' => "{$group['semestre']}° {$group['clave']}", 'semestre' => $group['semestre'],
                'clave_grupo' => $group['clave'], 'activo' => true, 'creado_en' => now(), 'actualizado_en' => now()]);
        }

        return DB::table('grupos_academicos')->where('carrera_id', $career)->where('periodo_id', $periodId)->where('activo', true)->whereNotNull('semestre')->get(['id', 'semestre', 'clave_grupo'])
            ->mapWithKeys(fn ($group) => ["{$group->semestre}|{$group->clave_grupo}" => (int) $group->id])->all();
    }

    /** Los proyectos vivos del alumno también se trabajan en el periodo nuevo; su grupo original no se reescribe. */
    private function carryProjects(string $studentId, int $groupId, int $periodId): void
    {
        $projects = DB::table('proyecto_integrantes as i')->join('proyectos as p', 'p.id', '=', 'i.proyecto_id')
            ->where('i.usuario_id', $studentId)->whereIn('i.rol', ['lider', 'integrante'])->where('p.activo', true)
            ->whereNotIn('p.estado', ['finalizado', 'archivado', 'rechazado'])->pluck('p.id');
        foreach ($projects as $projectId) {
            $linked = DB::table('proyecto_periodos as pp')->join('grupos_academicos as g', 'g.id', '=', 'pp.grupo_id')
                ->where('pp.proyecto_id', $projectId)->where('g.periodo_id', $periodId)->exists();
            if (!$linked) {
                DB::table('proyecto_periodos')->insert(['proyecto_id' => $projectId, 'grupo_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** Salas antiguas sin periodo: se completa sólo cuando sus evaluaciones señalan uno, sin adivinar. */
    private function linkRoomsToPeriods(int $career): void
    {
        foreach (CareerScope::constrain(DB::table('salas_evaluacion'), 'salas_evaluacion', $career)->whereNull('periodo_id')->pluck('salas_evaluacion.id') as $roomId) {
            $periods = DB::table('evaluaciones as e')->join('proyectos as p', 'p.id', '=', 'e.proyecto_id')->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')
                ->where('e.sala_id', $roomId)->distinct()->pluck('g.periodo_id');
            if ($periods->count() === 1) {
                DB::table('salas_evaluacion')->where('id', $roomId)->update(['periodo_id' => $periods->first()]);
            }
        }
    }

    private function closeFinished(int $career, array $periods, ?string $actorId): void
    {
        foreach ($periods as $period) {
            DB::table('carrera_periodos')->where(['carrera_id' => $career, 'periodo_id' => $period['id']])->update(['estado' => 'cerrado', 'updated_at' => now()]);
            $this->log($career, $actorId, 'periodo.cierre_automatico', 'periodo', $period['id'], null, ['pending' => $period['pending']]);
            if ($period['pending']) {
                $this->notifyManagers($career, 'periodo_cerrado_con_pendientes', 'Periodo concluido con pendientes', "{$period['nombre']} concluyó por fecha con pendientes: ".implode(', ', $period['pending']).'. No se borraron y todavía puedes atenderlos.');
            }
        }
    }

    /** @return string[] lo que quedó sin resolver, para avisar a la jefatura */
    private function pendingAtClose(int $career, int $periodId): array
    {
        $rooms = CareerScope::constrain(DB::table('salas_evaluacion'), 'salas_evaluacion', $career)->where('salas_evaluacion.periodo_id', $periodId)->where('estado', '!=', 'finalizada')->count();
        $residencies = CareerScope::constrain(DB::table('residencias'), 'residencias', $career)->where('periodo_id', $periodId)->where('estado', '!=', 'cerrada')->count();
        $requests = CareerScope::constrainOptional(DB::table('autorizaciones_academicas'), 'autorizaciones_academicas', $career)->where('periodo_id', $periodId)->where('estado', 'solicitada')->count();
        $deliveries = DB::table('entregas as e')->join('entregables as d', 'd.id', '=', 'e.entregable_id')->join('cursos as c', 'c.id', '=', 'd.curso_id')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
            ->where('g.carrera_id', $career)->where('g.periodo_id', $periodId)->whereNull('e.calificacion')->count();

        return array_values(array_filter([
            $rooms ? "{$rooms} sala(s) sin finalizar" : null,
            $deliveries ? "{$deliveries} entrega(s) sin calificar" : null,
            $residencies ? "{$residencies} residencia(s) abierta(s)" : null,
            $requests ? "{$requests} solicitud(es) sin responder" : null,
        ]));
    }

    private function notifyManagers(int $career, string $type, string $title, string $message): void
    {
        foreach (DB::table('usuario_carrera')->where('carrera_id', $career)->where('activo', true)->whereIn('perfil_id', [1, 5, 6])->pluck('usuario_id') as $user) {
            DB::table('notificaciones')->insert(['carrera_id' => $career, 'usuario_id' => $user, 'tipo' => $type, 'titulo' => $title, 'mensaje' => $message, 'creada_en' => now()]);
        }
    }

    private function log(int $career, ?string $actorId, string $action, string $entity, int|string $id, mixed $before, mixed $after): void
    {
        DB::table('bitacora_academica')->insert(['carrera_id' => $career, 'actor_id' => $actorId, 'accion' => $action, 'entidad' => $entity, 'entidad_id' => (string) $id,
            'antes' => $before === null ? null : json_encode($before), 'despues' => json_encode($after), 'creado_en' => now()]);
    }

    /** La operación exige un responsable: en la tarea programada es la administración general. */
    private function systemActor(): string
    {
        return (string) DB::table('usuarios')->where('es_administrador_general', true)->where('activo', true)->orderBy('id')->value('id');
    }
}
