<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recordatorios y resúmenes programados. Cada aviso se registra en avisos_academicos antes de enviarse:
 * repetir la tarea el mismo día no duplica nada.
 */
class NotificationSchedulerService
{
    private const REMINDER_DAYS = [7, 3, 1];

    public function daily(?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();

        return ['entregas' => $this->deliveryReminders($today), 'salas' => $this->roomReminders($today)];
    }

    public function weekly(?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();

        return ['docentes' => $this->reviewDigests($today), 'carreras' => $this->careerDigests($today)];
    }

    private function deliveryReminders(Carbon $today): int
    {
        $sent = 0;
        $deliverables = DB::table('entregables as d')->join('cursos as c', 'c.id', '=', 'd.curso_id')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
            ->join('carrera_periodos as cp', fn ($join) => $join->on('cp.carrera_id', '=', 'g.carrera_id')->on('cp.periodo_id', '=', 'g.periodo_id'))
            ->whereIn('cp.estado', ['abierto', 'en_cierre'])->where('d.activo', true)->where('d.estado', 'publicado')->whereNotNull('d.fecha_limite')
            ->whereDate('d.fecha_limite', '>=', $today->copy()->subDay())->whereDate('d.fecha_limite', '<=', $today->copy()->addDays(max(self::REMINDER_DAYS)))
            ->get(['d.id', 'd.nombre', 'd.curso_id', 'd.fecha_limite', 'g.carrera_id']);

        foreach ($deliverables as $deliverable) {
            $days = (int) $today->diffInDays(Carbon::parse($deliverable->fecha_limite)->startOfDay(), false);
            if (!in_array($days, self::REMINDER_DAYS, true) && $days !== -1) {
                continue;
            }
            $pending = DB::table('curso_estudiantes as ce')->where('ce.curso_id', $deliverable->curso_id)->where('ce.activo', true)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('entregas as e')->join('proyecto_integrantes as i', 'i.proyecto_id', '=', 'e.proyecto_id')
                    ->where('e.entregable_id', $deliverable->id)->whereColumn('i.usuario_id', 'ce.estudiante_id'))
                ->pluck('ce.estudiante_id');
            $recipients = $this->once($pending, fn ($student) => "entrega:{$deliverable->id}:{$days}:{$student}");
            [$title, $message] = match (true) {
                $days === -1 => ['Entrega vencida', "«{$deliverable->nombre}» venció ayer y no se ha entregado."],
                $days === 1 => ['Entrega para mañana', "«{$deliverable->nombre}» vence mañana."],
                default => ['Entrega próxima', "«{$deliverable->nombre}» vence en {$days} días."],
            };
            ActivityNotificationService::send($recipients, null, 'recordatorio_entrega', $title, $message, '/deliverables', (int) $deliverable->carrera_id);
            $sent += count($recipients);
        }

        return $sent;
    }

    private function roomReminders(Carbon $today): int
    {
        $sent = 0;
        $rooms = DB::table('salas_evaluacion as s')->join('rubricas as r', 'r.id', '=', 's.rubrica_id')
            ->whereNotIn('s.estado', ['finalizada', 'cancelada'])->whereNotNull('s.inicia_en')
            ->whereDate('s.inicia_en', '>=', $today->copy()->addDay())->whereDate('s.inicia_en', '<=', $today->copy()->addDays(max(self::REMINDER_DAYS)))
            ->get(['s.id', 's.nombre', 's.inicia_en', 's.responsable_id', 'r.carrera_id']);

        foreach ($rooms as $room) {
            $start = Carbon::parse($room->inicia_en);
            $days = (int) $today->diffInDays($start->copy()->startOfDay(), false);
            if (!in_array($days, self::REMINDER_DAYS, true)) {
                continue;
            }
            $when = $days === 1 ? 'mañana' : "en {$days} días";
            $at = $start->format('d/m/Y H:i');
            // La fecha va en la clave: si la sala se reprograma, los recordatorios vuelven a salir.
            $teachers = DB::table('sala_docentes')->where('sala_id', $room->id)->pluck('docente_id')->push($room->responsable_id)->filter()->unique();
            $recipients = $this->once($teachers, fn ($user) => "sala:{$room->id}:{$days}:{$start->toDateString()}:{$user}");
            ActivityNotificationService::send($recipients, null, 'recordatorio_evaluacion', 'Sala de evaluación próxima', "{$room->nombre} es {$when}: {$at}.", '/evaluation-rooms', (int) $room->carrera_id);
            $sent += count($recipients);

            $students = DB::table('evaluaciones as e')->join('proyecto_integrantes as i', 'i.proyecto_id', '=', 'e.proyecto_id')
                ->where('e.sala_id', $room->id)->where('e.estado', '!=', 'archivada')->whereIn('i.rol', ['lider', 'integrante'])->pluck('i.usuario_id')->unique();
            $recipients = $this->once($students, fn ($user) => "sala:{$room->id}:{$days}:{$start->toDateString()}:{$user}");
            ActivityNotificationService::send($recipients, null, 'recordatorio_evaluacion', 'Presentación próxima', "Tu presentación en {$room->nombre} es {$when}: {$at}.", '/dashboard', (int) $room->carrera_id);
            $sent += count($recipients);
        }

        return $sent;
    }

    /** Lunes: a cada docente, lo que lleva 3 días o más sin revisar y lo que le falta capturar. */
    private function reviewDigests(Carbon $today): int
    {
        $sent = 0;
        $late = DB::table('entregas as e')->join('proyecto_integrantes as i', 'i.proyecto_id', '=', 'e.proyecto_id')->join('proyectos as p', 'p.id', '=', 'e.proyecto_id')
            ->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')
            ->whereNull('e.calificacion')->whereIn('i.rol', ['primario', 'secundario', 'asesor', 'revisor_1', 'revisor_2'])
            ->where('e.entregado_en', '<=', $today->copy()->subDays(3))
            ->groupBy('i.usuario_id', 'g.carrera_id')->selectRaw('i.usuario_id, g.carrera_id, COUNT(DISTINCT e.id) as total')->get();
        $missing = DB::table('salas_evaluacion as s')->join('rubricas as r', 'r.id', '=', 's.rubrica_id')->join('sala_docentes as t', 't.sala_id', '=', 's.id')
            ->join('evaluaciones as e', 'e.sala_id', '=', 's.id')
            ->leftJoin('dictamenes_docentes as a', fn ($join) => $join->on('a.evaluacion_id', '=', 'e.id')->on('a.docente_id', '=', 't.docente_id'))
            ->where('e.estado', '!=', 'archivada')->where('s.estado', '!=', 'finalizada')->whereNotNull('s.inicia_en')->where('s.inicia_en', '<', $today)
            ->whereNull('a.enviado_en')
            ->groupBy('t.docente_id', 'r.carrera_id')->selectRaw('t.docente_id as usuario_id, r.carrera_id, COUNT(DISTINCT e.id) as total')->get();

        $people = [];
        foreach ($late as $row) {
            $people["{$row->usuario_id}|{$row->carrera_id}"]['late'] = (int) $row->total;
        }
        foreach ($missing as $row) {
            $people["{$row->usuario_id}|{$row->carrera_id}"]['missing'] = (int) $row->total;
        }
        foreach ($people as $key => $counts) {
            [$user, $career] = explode('|', $key);
            if (!$this->once([$user], fn ($id) => "resumen_revision:{$today->toDateString()}:{$career}:{$id}")) {
                continue;
            }
            $parts = array_filter([
                !empty($counts['late']) ? "{$counts['late']} entrega(s) sin calificar" : null,
                !empty($counts['missing']) ? "{$counts['missing']} evaluación(es) sin capturar" : null,
            ]);
            ActivityNotificationService::send([$user], null, 'resumen_revision', 'Pendientes de revisión', 'Tienes '.implode(' y ', $parts).'.', empty($counts['late']) ? '/evaluations' : '/deliverables', (int) $career);
            $sent++;
        }

        return $sent;
    }

    /** Lunes: a jefatura, asistencia y coordinación, el estado de su carrera. Sin atrasos no se envía nada. */
    private function careerDigests(Carbon $today): int
    {
        $sent = 0;
        foreach (DB::table('carrera_periodos')->whereIn('estado', ['abierto', 'en_cierre'])->get(['carrera_id', 'periodo_id']) as $open) {
            $career = (int) $open->carrera_id;
            $groups = fn () => DB::table('grupos_academicos')->where('carrera_id', $career)->where('periodo_id', $open->periodo_id)->pluck('id');
            $overdue = DB::table('entregables as d')->join('cursos as c', 'c.id', '=', 'd.curso_id')->whereIn('c.grupo_id', $groups())
                ->where('d.activo', true)->where('d.estado', 'publicado')->where('d.fecha_limite', '<', $today)->count();
            $ungraded = DB::table('entregas as e')->join('entregables as d', 'd.id', '=', 'e.entregable_id')->join('cursos as c', 'c.id', '=', 'd.curso_id')
                ->whereIn('c.grupo_id', $groups())->whereNull('e.calificacion')->count();
            $rooms = DB::table('salas_evaluacion as s')->join('rubricas as r', 'r.id', '=', 's.rubrica_id')->where('r.carrera_id', $career)->where('s.estado', '!=', 'finalizada')
                ->whereBetween('s.inicia_en', [$today, $today->copy()->addDays(7)])->count();
            $requests = DB::table('autoregistros_pendientes')->where('carrera_id', $career)->where('estado', 'por_aprobar')->count();
            $parts = array_filter([
                $overdue ? "{$overdue} entregable(s) con fecha vencida" : null,
                $ungraded ? "{$ungraded} entrega(s) sin calificar" : null,
                $rooms ? "{$rooms} sala(s) esta semana" : null,
                $requests ? "{$requests} solicitud(es) de cuenta" : null,
            ]);
            if (!$parts) {
                continue;
            }
            $managers = DB::table('usuario_carrera')->where('carrera_id', $career)->where('activo', true)->whereIn('perfil_id', [5, 6, 7])->pluck('usuario_id');
            $recipients = $this->once($managers, fn ($user) => "resumen_semanal:{$today->toDateString()}:{$career}:{$user}");
            ActivityNotificationService::send($recipients, null, 'resumen_semanal', 'Resumen de la semana', implode(' · ', $parts).'.', '/dashboard', $career);
            $sent += count($recipients);
        }

        return $sent;
    }

    /** @return string[] quienes aún no recibieron este aviso */
    private function once(iterable $users, \Closure $key): array
    {
        $fresh = [];
        foreach (collect($users)->unique() as $user) {
            if (DB::table('avisos_academicos')->insertOrIgnore(['clave' => mb_substr($key((string) $user), 0, 180), 'created_at' => now()])) {
                $fresh[] = (string) $user;
            }
        }

        return $fresh;
    }
}
