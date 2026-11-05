<?php

namespace App\Services;

use App\Http\Controllers\API\DeliverableController;
use App\Http\Controllers\API\EvaluationController;
use App\Http\Controllers\API\ProposalWorkflowController;
use App\Models\CareerSetting;
use App\Models\Deliverable;
use App\Models\EvaluationRoom;
use App\Models\Project;
use App\Support\CareerContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Respuestas del asistente con datos reales, siempre de quien pregunta o de su carrera. Reutiliza las mismas
 * consultas que sus pantallas, para no mostrar nada que esa persona no pueda ver ya.
 */
class HelpPersonalDataService
{
    private const PROPOSAL_STATES = [
        'pendiente' => 'en validación',
        'requiere_cambios' => 'con cambios solicitados',
        'aprobado' => 'aprobada',
        'rechazado' => 'rechazada',
    ];

    public function answer(string $handler): array
    {
        // La vista simulada usa la cuenta del administrador: no hay datos de la persona simulada que mostrar.
        if (app(CareerContext::class)->simulatedProfile()) {
            return ['lines' => ['En la vista simulada no se muestran datos personales. Prueba con los demás temas.']];
        }

        return $this->{$handler}();
    }

    private function studentStatusData(): array
    {
        return app(ProposalWorkflowController::class)->studentStatus()->getData(true);
    }

    private function studentNextStep(): array
    {
        $status = $this->studentStatusData();
        $project = $status['project'] ?? null;
        $proposalScreen = ['app' => '/proposals', 'web' => '/pages/student/proposal-register.php'];

        if (!empty($status['profile_required'])) {
            return ['lines' => ['Primero completa tus datos: nombre, semestre y grupo. El sistema te los pide en tu panel de Inicio.'], 'app' => '/dashboard', 'web' => '/pages/student/dashboard.php'];
        }
        if (!$project) {
            if (!empty($status['my_request'])) {
                return ['lines' => ['Tu solicitud para unirte a «'.$status['my_request']['project_title'].'» espera que un integrante la acepte.', 'Mientras tanto puedes cancelarla si cambias de opinión.']] + $proposalScreen;
            }
            if (empty($status['subject_group'])) {
                return ['lines' => ['No tienes grupo asignado en este periodo, así que aún no puedes registrar una propuesta.', 'Pídele a tu jefatura que te asigne uno.']];
            }
            if (!empty($status['window']['open'])) {
                return ['lines' => ['Registra tu propuesta, o únete a la de un compañero de tu grupo.', 'El registro cierra el '.$this->date($status['window']['closes_at']).'.']] + $proposalScreen;
            }

            return ['lines' => ['Debes registrar una propuesta, pero el registro está cerrado para tu carrera.', 'Pregunta a tu jefatura cuándo se abrirá.']];
        }

        $state = $project['proposal_status'] ?? $project['estado'] ?? null;
        if ($state === 'pendiente') {
            return ['lines' => ['Tu propuesta «'.($project['title'] ?? $project['titulo']).'» está en validación. Te avisaremos del resultado; por ahora no tienes que hacer nada.']] + $proposalScreen;
        }
        if ($state === 'requiere_cambios') {
            $until = $project['revision_allowed_until'] ?? $project['revision_permitida_hasta'] ?? null;

            return ['lines' => array_values(array_filter(['Te pidieron cambios en tu propuesta. Revisa las observaciones en «Corregir propuesta» y vuelve a enviarla.', $until ? 'Tienes hasta el '.$this->date($until).'.' : null]))] + $proposalScreen;
        }

        $pending = $this->pendingDeliverables();
        if ($pending['enrolled'] === false) {
            return ['lines' => ['Inscríbete a tu materia de seguimiento con la clave que te dé tu docente, en Seguimiento académico.'], 'app' => '/academic-workflows', 'web' => '/pages/academic-workflows.php'];
        }
        if ($pending['items'] !== []) {
            return ['lines' => ['Tienes '.count($pending['items']).' entrega(s) por subir:', ...array_slice(array_map(fn ($line) => $line['label'], $pending['items']), 0, 5)], 'app' => '/deliverables', 'web' => '/pages/student/my-deliverables.php'];
        }
        $room = $this->nextRoom();
        if ($room) {
            return ['lines' => ['Prepárate para tu presentación: '.$this->roomLine($room), 'Revisa que tus evidencias estén completas en Proyectos y tesis › Evidencias.'], 'app' => '/evaluation-documents', 'web' => '/pages/evaluation-documents.php'];
        }

        return ['lines' => ['Vas al día: no tienes entregas pendientes ni una sala próxima programada.', 'Te avisaremos cuando te asignen sala o haya una nueva entrega.']];
    }

    private function studentProject(): array
    {
        $status = $this->studentStatusData();
        $project = $status['project'] ?? null;
        $screen = ['app' => '/proposals', 'web' => '/pages/student/proposal-register.php'];
        if (!$project) {
            $rejected = $status['last_rejected'] ?? null;

            return ['lines' => $rejected
                ? ['Tu propuesta «'.$rejected['titulo'].'» fue rechazada.', 'Motivo: '.($rejected['comentario_revision'] ?: 'no se indicó.'), 'Puedes registrar otra mientras el registro siga abierto.']
                : ['Aún no tienes un proyecto registrado.']] + $screen;
        }
        $model = Project::with(['students:id,nombres,apellido_paterno', 'advisors:id,nombres,apellido_paterno'])->find($project['id']);
        $state = $model->estado;
        $names = fn ($people) => $people->map(fn ($person) => trim($person->nombres.' '.$person->apellido_paterno))->implode(', ');

        return ['lines' => array_values(array_filter([
            'Proyecto: «'.$model->titulo.'».',
            $model->tipo === 'propuesta' ? 'Estado de la propuesta: '.(self::PROPOSAL_STATES[$state] ?? $state).'.' : 'Estado: '.$state.'.',
            $model->students->isNotEmpty() ? 'Integrantes: '.$names($model->students).'.' : null,
            $model->advisors->isNotEmpty() ? 'Asesoría: '.$names($model->advisors).'.' : 'Aún no tienes asesor asignado.',
            $state === 'requiere_cambios' && $model->comentario_revision ? 'Observaciones: '.$model->comentario_revision : null,
        ]))] + $screen;
    }

    /** @return array{enrolled: bool, items: list<array{label: string}>, graded: list<string>} */
    private function pendingDeliverables(): array
    {
        $user = auth('api')->user();
        $enrolled = DB::table('curso_estudiantes')->where('estudiante_id', $user->id)->where('activo', true)->exists();
        $rows = app(DeliverableController::class)->myDeliverables()->getData(true)['data'] ?? [];
        $deadlines = Deliverable::whereIn('id', array_column($rows, 'id'))->pluck('fecha_limite', 'id');

        $items = [];
        $graded = [];
        foreach ($rows as $row) {
            if ($row['calificacion'] !== null) {
                $graded[] = "{$row['nombre']}: {$row['calificacion']}";
            } elseif (!$row['delivery_id'] && $row['puede_subir']) {
                $deadline = $deadlines[$row['id']] ?? null;
                $items[] = ['label' => $row['nombre'].($deadline ? ' (límite: '.$this->date($deadline).')' : '')];
            }
        }

        return ['enrolled' => $enrolled, 'items' => $items, 'graded' => $graded];
    }

    private function studentDeliverables(): array
    {
        $data = $this->pendingDeliverables();
        $screen = ['app' => '/deliverables', 'web' => '/pages/student/my-deliverables.php'];
        if (!$data['enrolled']) {
            return ['lines' => ['Aún no estás inscrito a ninguna materia. Inscríbete en Seguimiento académico con la clave de tu docente.'], 'app' => '/academic-workflows', 'web' => '/pages/academic-workflows.php'];
        }

        return ['lines' => [
            ...($data['items'] ? ['Por subir:', ...array_map(fn ($item) => $item['label'], array_slice($data['items'], 0, 8))] : ['No tienes entregas por subir.']),
            ...($data['graded'] ? ['Calificaciones:', ...array_slice($data['graded'], 0, 8)] : []),
        ]] + $screen;
    }

    /** La próxima presentación, o la última si ya no queda ninguna. */
    private function nextRoom(): ?array
    {
        $rows = app(EvaluationController::class)->studentSchedule()->getData(true);
        foreach ($rows as $row) {
            if (!empty($row['date']) && Carbon::parse($row['date'])->isFuture()) {
                return $row;
            }
        }

        return null;
    }

    private function roomLine(array $room): string
    {
        return trim(($room['room_name'] ?? 'Sala').($room['classroom'] ? ", salón {$room['classroom']}" : '').', el '.$this->date($room['date']).'.');
    }

    private function studentRoom(): array
    {
        $room = $this->nextRoom();
        $screen = ['app' => '/dashboard', 'web' => '/pages/student/dashboard.php'];
        if (!$room) {
            return ['lines' => ['No tienes una presentación próxima programada.', 'Te avisaremos cuando te asignen sala o cambie el horario.']] + $screen;
        }

        return ['lines' => array_values(array_filter([
            $this->roomLine($room),
            !empty($room['project_title']) ? 'Proyecto: «'.$room['project_title'].'».' : null,
            !empty($room['presentation_minutes']) ? 'Tienes '.$room['presentation_minutes'].' minutos de presentación.' : null,
            'Ten listas tus evidencias en Proyectos y tesis › Evidencias.',
        ]))] + $screen;
    }

    private function teacherPending(): array
    {
        $user = auth('api')->user();
        $lines = [];

        if (in_array((string) $user->id, array_map('strval', CareerSetting::valueFor('proposal_validator_teacher_ids', [])), true)) {
            $lines[] = 'Propuestas por validar: '.$this->pendingProposals().'.';
        }
        $ungraded = DB::table('entregas as e')
            ->join('entregables as d', 'd.id', '=', 'e.entregable_id')
            ->join('curso_docentes as cd', 'cd.curso_id', '=', 'd.curso_id')
            ->where('cd.docente_id', $user->id)->where('cd.activo', true)
            ->where('d.activo', true)->whereNull('e.calificacion')->count();
        $lines[] = 'Entregas por calificar: '.$ungraded.'.';

        $room = EvaluationRoom::query()
            ->where(fn ($scope) => $scope->whereHas('teachers', fn ($teachers) => $teachers->where('usuarios.id', $user->id))->orWhere('responsible_teacher_id', $user->id))
            ->where('fecha_evaluacion', '>', now())->orderBy('fecha_evaluacion')->first();
        $lines[] = $room
            ? 'Tu próxima sala: '.$room->nombre.($room->salon ? ", salón {$room->salon}" : '').', el '.$this->date($room->fecha_evaluacion).'.'
            : 'No tienes salas próximas.';

        return ['lines' => $lines, 'app' => '/deliverables', 'web' => '/pages/teacher/my-deliverables.php'];
    }

    private function pendingProposals(): int
    {
        return Project::query()->where('activo', true)->where('tipo', 'propuesta')->where('estado', 'pendiente')->count();
    }

    private function managerPending(): array
    {
        $user = auth('api')->user();
        $career = app(CareerContext::class)->careerId();
        $lines = ['Propuestas por validar: '.$this->pendingProposals().'.'];
        if ($user->canManageAcademics()) {
            $lines[] = 'Solicitudes de cuenta por revisar: '.DB::table('autoregistros_pendientes')->where('carrera_id', $career)->where('estado', 'por_aprobar')->count().'.';
        }
        $lines[] = 'Empresas por validar: '.DB::table('empresas')->where('estado_validacion', 'pendiente')->count().'.';

        $room = EvaluationRoom::query()->where('fecha_evaluacion', '>', now())->orderBy('fecha_evaluacion')->first();
        $lines[] = $room ? 'Próxima sala: '.$room->nombre.', el '.$this->date($room->fecha_evaluacion).'.' : 'No hay salas próximas programadas.';

        $period = app(AcademicWorkflowService::class)->period();
        $lines[] = $period ? 'Periodo abierto: '.$period->nombre.'.' : 'La carrera no tiene un periodo abierto.';

        $until = CareerSetting::valueFor('proposal_registration_until');
        $lines[] = $until && Carbon::parse($until)->isFuture()
            ? 'Registro de propuestas abierto hasta el '.$this->date($until).'.'
            : 'El registro de propuestas está cerrado.';

        return ['lines' => $lines, 'app' => '/proposals', 'web' => '/pages/admin/proposal-config.php'];
    }

    private function generalStatus(): array
    {
        $alerts = DB::table('alertas_operativas')->whereNull('resuelta_en');
        $critical = (clone $alerts)->where('severidad', 'critica')->count();
        $backup = DB::table('respaldos_base_datos')->where('estado', 'completado')->max('creado_en');

        return ['lines' => [
            'Alertas abiertas: '.$alerts->count().($critical ? " ({$critical} críticas)." : '.'),
            $backup ? 'Último respaldo correcto: '.$this->date($backup).'.' : 'No hay respaldos correctos registrados.',
        ], 'app' => '/operations', 'web' => '/pages/admin/operations.php'];
    }

    private function date(mixed $value): string
    {
        return Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y \a \l\a\s H:i');
    }
}
