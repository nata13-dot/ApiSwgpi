<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerSetting;
use App\Models\Project;
use App\Models\User;
use App\Support\CareerContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de los widgets de la pantalla de inicio según el perfil activo: pendientes (cuadrícula y pendiente principal),
 * próximas fechas (agenda y cuenta regresiva) y accesos rápidos. Se ven sin desbloquear el teléfono, así que sólo
 * llevan números y títulos: ni nombres de alumnos ni calificaciones.
 */
class WidgetController extends Controller
{
    private const PROFILES = [1 => 'Administración', 2 => 'Docente', 3 => 'Estudiante', 4 => 'Administrador general', 5 => 'Jefatura de carrera', 6 => 'Asistencia de jefatura', 7 => 'Coordinación de proyectos'];
    private const EVENT_LABELS = ['deliverable' => 'Entrega', 'presentation' => 'Mi presentación', 'room' => 'Evaluación', 'self_registration' => 'Cierre del autoregistro', 'proposals' => 'Cierre de propuestas', 'period_end' => 'Fin del periodo'];

    public function __invoke()
    {
        $user = auth('api')->user();
        $profile = (int) $user->perfil_id;
        $kind = match (true) {
            $profile === 3 => 'student',
            $profile === 2 => 'teacher',
            default => 'management',
        };
        $items = array_values(array_filter(match ($kind) {
            'student' => $this->student($user),
            'teacher' => $this->teacher($user),
            default => $this->management($user, $profile),
        }));
        $agenda = collect($this->agenda($user, $kind))->filter()->filter(fn ($event) => Carbon::parse($event['at'])->isFuture())
            ->sortBy('at')->take(6)->values()->all();
        // El pendiente principal: el primero con algo que atender.
        $main = collect($items)->first(fn ($item) => !in_array($item['value'], ['0', '—'], true)) ?? ($items[0] ?? null);
        $eventKinds = match ($kind) {
            'student' => ['deliverable', 'presentation', 'period_end'],
            'teacher' => ['room', 'deliverable', 'period_end'],
            default => ['self_registration', 'proposals', 'room', 'period_end'],
        };

        return response()->json([
            'name' => $user->nombres,
            'profile' => $user->isGeneralAdmin() ? 'Administrador general' : (self::PROFILES[$profile] ?? 'SGPI'),
            'career' => app(CareerContext::class)->career()?->nombre_corto,
            'updated_at' => now()->toIso8601String(),
            'items' => $items,
            'main' => $main['key'] ?? null,
            'agenda' => $agenda,
            'countdown_options' => array_map(fn ($key) => ['key' => $key, 'label' => self::EVENT_LABELS[$key]], $eventKinds),
            'shortcuts' => $this->shortcuts($user, $kind),
        ]);
    }

    private function student(User $user): array
    {
        $projectIds = Project::where('activo', true)
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id))
            ->pluck('id');
        // Las mismas inscripciones que usa Entregables para el alumno.
        $courseIds = DB::table('curso_estudiantes')->where('estudiante_id', $user->id)->where('activo', true)->pluck('curso_id');

        $next = DB::table('entregables')
            ->whereIn('entregables.curso_id', $courseIds)
            ->where('entregables.activo', true)->where('entregables.estado', 'publicado')
            ->where('entregables.fecha_limite', '>=', now())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('entregas')
                ->whereColumn('entregas.entregable_id', 'entregables.id')->whereIn('entregas.proyecto_id', $projectIds))
            ->orderBy('entregables.fecha_limite')
            ->first(['entregables.nombre', 'entregables.fecha_limite']);

        $project = Project::whereIn('id', $projectIds)->orderByDesc('creado_en')->first(['id', 'titulo', 'tipo', 'estado']);
        $projectState = match (true) {
            !$project => null,
            $project->tipo !== 'propuesta' => 'Activo',
            default => ['pendiente' => 'En revisión', 'requiere_cambios' => 'Con cambios', 'aprobado' => 'Aprobada', 'rechazado' => 'Rechazada'][$project->proposal_status] ?? 'Propuesta',
        };

        $presentation = DB::table('v_evaluacion_horario as horario')
            ->join('salas_evaluacion as sala', 'sala.id', '=', 'horario.sala_id')
            ->whereIn('horario.proyecto_id', $projectIds)
            ->where('sala.estado', 'programada')
            ->where('horario.inicio_estimado', '>=', now()->subMinutes(30))
            ->orderBy('horario.inicio_estimado')
            ->first(['sala.nombre', 'sala.salon', 'horario.inicio_estimado']);

        $graded = DB::table('entregas')->join('entregables', 'entregables.id', '=', 'entregas.entregable_id')
            ->whereIn('entregas.proyecto_id', $projectIds)->whereNotNull('entregas.calificacion');
        $lastGraded = (clone $graded)->orderByDesc('entregas.entregado_en')->value('entregables.nombre');

        return [
            $this->item('deliverable', 'Próxima entrega', $next ? $this->countdown($next->fecha_limite) : '—',
                $next ? "{$next->nombre} · {$this->date($next->fecha_limite)}" : 'Sin entregas pendientes', '/deliverables'),
            $this->item('project', 'Mi proyecto', $projectState ?? '—', $project?->title ?? 'Aún no tienes proyecto', $project ? '/projects' : '/proposals'),
            $this->item('presentation', 'Mi presentación', $presentation ? $this->shortDate($presentation->inicio_estimado) : '—',
                $presentation ? trim(($presentation->salon ?: $presentation->nombre) . ' · ' . Carbon::parse($presentation->inicio_estimado)->format('H:i')) : 'Sin presentación asignada', '/evaluations'),
            $this->item('grades', 'Calificaciones', (string) (clone $graded)->count(), $lastGraded ? "Última: {$lastGraded}" : 'Aún sin calificaciones', '/deliverables'),
        ];
    }

    private function teacher(User $user): array
    {
        $toReview = DB::table('entregas')
            ->join('entregables', 'entregables.id', '=', 'entregas.entregable_id')
            ->join('curso_docentes', 'curso_docentes.curso_id', '=', 'entregables.curso_id')
            ->where('curso_docentes.docente_id', $user->id)->where('curso_docentes.activo', true)
            ->where('entregables.activo', true)
            ->whereNull('entregas.calificacion')
            ->distinct()->count('entregas.id');

        $validator = in_array((string) $user->id, array_map('strval', CareerSetting::valueFor('proposal_validator_teacher_ids', [])), true);

        $rooms = DB::table('salas_evaluacion as sala')
            ->join('sala_docentes', 'sala_docentes.sala_id', '=', 'sala.id')
            ->where('sala_docentes.docente_id', $user->id)
            ->where('sala.estado', 'programada')
            ->where('sala.termina_en', '>=', now())
            ->whereIn('sala.id', \App\Support\CareerScope::ids('salas_evaluacion', app(CareerContext::class)->careerId()))
            ->orderBy('sala.inicia_en');
        $nextRoom = (clone $rooms)->first(['sala.nombre', 'sala.salon', 'sala.inicia_en']);

        $advised = Project::where('activo', true)->whereHas('advisors', fn ($query) => $query->where('usuarios.id', $user->id))->count();

        return [
            $this->item('review', 'Entregas por revisar', (string) $toReview, $toReview ? 'Esperan tu calificación' : 'Todo al día', '/deliverables'),
            $validator ? $this->item('proposals', 'Propuestas por validar', (string) $this->pendingProposals(), 'De tu carrera', '/proposals') : null,
            $this->item('rooms', 'Próximas evaluaciones', (string) (clone $rooms)->count(),
                $nextRoom ? trim(($nextRoom->salon ?: $nextRoom->nombre) . ' · ' . $this->date($nextRoom->inicia_en)) : 'Sin salas asignadas', '/evaluations'),
            $this->item('advised', 'Proyectos asesorados', (string) $advised, $advised ? 'Como asesor o revisor' : 'Sin asesorías', '/projects'),
        ];
    }

    private function management(User $user, int $profile): array
    {
        $careerId = app(CareerContext::class)->careerId();
        $requests = in_array($profile, [1, 4, 5, 6], true) || $user->isGeneralAdmin()
            ? DB::table('autoregistros_pendientes')->where('carrera_id', $careerId)->where('estado', 'por_aprobar')->count()
            : null;
        $students = User::inCareer($careerId, [3])->where('activo', true)->count();
        $projects = Project::where('activo', true)->where(fn ($scope) => $scope->where('tipo', '!=', 'propuesta')->orWhere('estado', 'aprobado'))->count();

        $alerts = null;
        if ($user->isGeneralAdmin() && \App\Support\SchemaCache::hasTable('alertas_operativas')) {
            $active = DB::table('alertas_operativas')->whereIn('estado', ['abierta', 'atendida']);
            $alerts = ['total' => (clone $active)->count(), 'critical' => (clone $active)->where('severidad', 'critica')->count()];
        }

        return [
            $requests !== null ? $this->item('requests', 'Solicitudes de cuenta', (string) $requests, $requests ? 'Esperan aprobación' : 'Sin solicitudes', '/registration-requests') : null,
            $this->item('proposals', 'Propuestas por validar', (string) $this->pendingProposals(), 'De la carrera', '/proposals'),
            $this->item('career', 'Proyectos activos', (string) $projects, "{$students} alumnos activos", '/dashboard'),
            $alerts ? $this->item('alerts', 'Alertas operativas', (string) $alerts['total'], $alerts['critical'] ? "{$alerts['critical']} críticas" : 'Sin alertas críticas', '/operations') : null,
        ];
    }

    /** Próximas fechas del perfil; las pasadas se descartan arriba. */
    private function agenda(User $user, string $kind): array
    {
        $careerId = app(CareerContext::class)->careerId();
        $period = app(\App\Services\AcademicWorkflowService::class)->period($careerId);
        $events = $period ? [$this->event('period_end', "Fin del periodo {$period->nombre}", 'Concluye solo por fecha', Carbon::parse($period->fecha_fin)->endOfDay(), '/dashboard')] : [];

        if ($kind === 'student') {
            $projectIds = Project::where('activo', true)->whereHas('students', fn ($query) => $query->where('usuarios.id', $user->id))->pluck('id');
            $courseIds = DB::table('curso_estudiantes')->where('estudiante_id', $user->id)->where('activo', true)->pluck('curso_id');
            foreach (DB::table('entregables')->whereIn('curso_id', $courseIds)->where('activo', true)->where('estado', 'publicado')
                ->where('fecha_limite', '>=', now())
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('entregas')->whereColumn('entregas.entregable_id', 'entregables.id')->whereIn('entregas.proyecto_id', $projectIds))
                ->orderBy('fecha_limite')->limit(3)->get(['nombre', 'fecha_limite']) as $deliverable) {
                $events[] = $this->event('deliverable', "Entrega: {$deliverable->nombre}", 'Fecha límite', $deliverable->fecha_limite, '/deliverables');
            }
            $presentation = DB::table('v_evaluacion_horario as horario')->join('salas_evaluacion as sala', 'sala.id', '=', 'horario.sala_id')
                ->whereIn('horario.proyecto_id', $projectIds)->where('sala.estado', 'programada')->where('horario.inicio_estimado', '>=', now())
                ->orderBy('horario.inicio_estimado')->first(['sala.nombre', 'sala.salon', 'horario.inicio_estimado']);
            if ($presentation) {
                $events[] = $this->event('presentation', 'Mi presentación', $presentation->salon ?: $presentation->nombre, $presentation->inicio_estimado, '/evaluations');
            }
            if ($projectIds->isEmpty() && ($until = $this->proposalWindowCloses())) {
                $events[] = $this->event('proposals', 'Cierra el registro de propuestas', 'Registra tu propuesta antes', $until, '/proposals');
            }

            return $events;
        }

        $rooms = DB::table('salas_evaluacion as sala')->where('sala.estado', 'programada')->where('sala.inicia_en', '>=', now())
            ->whereIn('sala.id', \App\Support\CareerScope::ids('salas_evaluacion', $careerId))->orderBy('sala.inicia_en');
        if ($kind === 'teacher') {
            $rooms->join('sala_docentes', 'sala_docentes.sala_id', '=', 'sala.id')->where('sala_docentes.docente_id', $user->id);
            foreach (DB::table('entregables')->join('curso_docentes', 'curso_docentes.curso_id', '=', 'entregables.curso_id')
                ->where('curso_docentes.docente_id', $user->id)->where('curso_docentes.activo', true)
                ->where('entregables.activo', true)->where('entregables.fecha_limite', '>=', now())
                ->orderBy('entregables.fecha_limite')->limit(2)->get(['entregables.nombre', 'entregables.fecha_limite']) as $deliverable) {
                $events[] = $this->event('deliverable', "Vence: {$deliverable->nombre}", 'Entrega de tus alumnos', $deliverable->fecha_limite, '/deliverables');
            }
        } else {
            if (($until = \App\Http\Controllers\API\SelfRegistrationController::openUntilFor($careerId)) && $until->isFuture()) {
                $events[] = $this->event('self_registration', 'Cierra el autoregistro', 'Alumnos nuevos', $until, '/period-center');
            }
            if ($until = $this->proposalWindowCloses()) {
                $events[] = $this->event('proposals', 'Cierra el registro de propuestas', 'Ventana de la carrera', $until, '/period-center');
            }
        }
        foreach ($rooms->limit(3)->get(['sala.nombre', 'sala.salon', 'sala.inicia_en']) as $room) {
            $events[] = $this->event('room', "Evaluación: {$room->nombre}", $room->salon ?: 'Sala de evaluación', $room->inicia_en, '/evaluation-rooms');
        }

        return $events;
    }

    /** Accesos rápidos del perfil; el widget deja elegir hasta cuatro. */
    private function shortcuts(User $user, string $kind): array
    {
        $links = match ($kind) {
            'student' => [['deliverables', 'Mis entregas', '/deliverables'], ['project', 'Mi proyecto', '/projects'], ['proposals', 'Propuestas', '/proposals'],
                ['evaluations', 'Evaluaciones', '/evaluations'], ['repository', 'Repositorio', '/repository'], ['profile', 'Mi perfil', '/profile']],
            'teacher' => [['deliverables', 'Entregables', '/deliverables'], ['projects', 'Mis proyectos', '/projects'], ['evaluations', 'Evaluaciones', '/evaluations'],
                ['proposals', 'Propuestas', '/proposals'], ['rooms', 'Salas y turnos', '/evaluation-rooms'], ['repository', 'Repositorio', '/repository']],
            default => array_values(array_filter([['period', 'Operación del periodo', '/period-center'],
                ($user->canManageAcademics() || $user->isGeneralAdmin()) ? ['requests', 'Solicitudes', '/registration-requests'] : null,
                ['proposals', 'Propuestas', '/proposals'], ['projects', 'Proyectos', '/projects'],
                // Igual que canViewUsers de la app: jefatura, asistencia, coordinación y administración general.
                ($user->isGeneralAdmin() || in_array((int) $user->perfil_id, [5, 6, 7], true)) ? ['users', 'Usuarios', '/users'] : null,
                ['evaluations', 'Evaluaciones', '/evaluations'], ['rooms', 'Salas y turnos', '/evaluation-rooms']])),
        };

        return array_map(fn ($link) => ['key' => $link[0], 'label' => $link[1], 'path' => $link[2]], $links);
    }

    private function proposalWindowCloses(): ?Carbon
    {
        $until = CareerSetting::valueFor('proposal_registration_until');
        $closes = $until ? Carbon::parse($until) : null;

        return $closes?->isFuture() ? $closes : null;
    }

    private function event(string $kind, string $title, string $detail, $at, string $path): array
    {
        return ['kind' => $kind, 'title' => mb_substr($title, 0, 60), 'detail' => mb_substr($detail, 0, 60), 'at' => Carbon::parse($at)->toIso8601String(), 'path' => $path];
    }

    private function pendingProposals(): int
    {
        return Project::where('tipo', 'propuesta')->where('activo', true)->where('estado', 'pendiente')->count();
    }

    private function item(string $key, string $label, string $value, string $detail, string $path): array
    {
        return ['key' => $key, 'label' => $label, 'value' => mb_substr($value, 0, 12), 'detail' => mb_substr($detail, 0, 70), 'path' => $path];
    }

    private function countdown($date): string
    {
        $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay());
        return match (true) {
            $days <= 0 => 'Hoy',
            $days === 1 => 'Mañana',
            default => "{$days} días",
        };
    }

    private function date($date): string
    {
        return Carbon::parse($date)->locale('es')->isoFormat('D MMM, HH:mm');
    }

    private function shortDate($date): string
    {
        return Carbon::parse($date)->locale('es')->isoFormat('D MMM');
    }
}
