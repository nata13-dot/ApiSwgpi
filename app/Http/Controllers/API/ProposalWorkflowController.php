<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerSetting;
use App\Models\Project;
use App\Models\Asignatura;
use App\Models\ProjectRegistrationWindow;
use App\Models\ProposalReviewException;
use App\Models\SubjectGroup;
use App\Models\TeacherGroupAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProposalWorkflowController extends Controller
{
    private const DEFAULT_PROPOSAL_SUBJECT = 'Fundamentos de Ingeniería de Software';

    public function configIndex()
    {
        $defaultSubject = $this->defaultProposalSubject();
        $subjectGroups = SubjectGroup::with(['asignaturas'])
            ->where('periodo_id', app(\App\Services\AcademicWorkflowService::class)->period()?->id ?? 0)
            ->whereIn('semestre', app(\App\Services\SemesterManagementService::class)->careerSemesters())
            ->orderBy('semestre')
            ->orderBy('nombre')
            ->get()
            ->each(function (SubjectGroup $group) {
                $window = $this->windowPayloadFromGroup($group);
                $group->setRelation('registrationWindows', collect($window ? [$window] : []));
                $group->setRelation('teacherAssignments', $this->assignmentsForGroup($group));
            });

        $exceptions = collect();

        return response()->json([
            'default_subject' => $defaultSubject,
            'subject_groups' => $subjectGroups,
            'grupos_academicos' => $subjectGroups,
            'teachers' => User::withCareerProfiles([1, 2, 5, 6, 7])->where('activo', true)->orderBy('nombres')->get(['id', 'nombres', 'apa', 'ama']),
            'asignaturas' => Asignatura::orderBy('nombre')->get(['id', 'clave', 'nombre']),
            'exceptions' => $exceptions,
        ]);
    }

    public function storeWindow(Request $request)
    {
        $validated = $request->validate([
            'subject_group_id' => 'required|exists:grupos_academicos,id',
            'starts_at' => ['required', 'date', new \App\Rules\NotBeforeNow()],
            'ends_at' => 'required|date|after:starts_at',
            'activo' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->guardCanManageWindow((int) $validated['subject_group_id']);
        $group = SubjectGroup::findOrFail($validated['subject_group_id']);
        $group->update([
            'registro_proyectos_desde' => $validated['starts_at'],
            'registro_proyectos_hasta' => $validated['ends_at'],
        ]);
        $window = $this->windowPayloadFromGroup($group->fresh());
        return response()->json(['message' => 'Ventana de registro creada', 'window' => $window], 201);
    }

    public function updateWindow(Request $request, $id)
    {
        $group = SubjectGroup::findOrFail($id);
        $this->guardCanManageWindow((int) $group->id);
        $validated = $request->validate([
            'starts_at' => ['required', 'date', new \App\Rules\NotBeforeNow($group->registro_proyectos_desde)],
            'ends_at' => 'required|date|after:starts_at|after:now',
            'activo' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        $group->update([
            'registro_proyectos_desde' => $validated['starts_at'],
            'registro_proyectos_hasta' => $validated['ends_at'],
        ]);
        $window = $this->windowPayloadFromGroup($group->fresh());
        return response()->json(['message' => 'Ventana de registro actualizada', 'window' => $window]);
    }

    public function destroyWindow($id)
    {
        $group = SubjectGroup::findOrFail($id);
        $this->guardCanManageWindow((int) $group->id);
        $group->update([
            'registro_proyectos_desde' => null,
            'registro_proyectos_hasta' => null,
        ]);
        return response()->json(['message' => 'Ventana de registro eliminada']);
    }

    public function storeAssignment(Request $request)
    {
        $validated = $request->validate([
            'subject_group_id' => 'required|exists:grupos_academicos,id',
            'asignatura_id' => 'required|integer|exists:asignaturas,id',
            'teacher_id' => ['required', Rule::exists('usuarios', 'id')->where('activo', true)],
            'labor' => 'nullable|string|max:120',
            'activo' => 'nullable|boolean',
        ]);

        $teacher = User::where('id', $validated['teacher_id'])->withCareerProfiles([1, 2, 5, 6, 7])->where('activo', true)->first();
        if (!$teacher) {
            throw ValidationException::withMessages(['teacher_id' => ['El responsable debe ser un docente activo.']]);
        }

        $belongsToGroup = \App\Support\CareerScope::constrain(DB::table('cursos'), 'cursos', app(\App\Support\CareerContext::class)->careerId())
            ->where('grupo_id', $validated['subject_group_id'])
            ->where('asignatura_id', $validated['asignatura_id'])
            ->exists();

        if (!$belongsToGroup) {
            throw ValidationException::withMessages([
                'asignatura_id' => ['La materia seleccionada no pertenece a la carga/grupo indicado.'],
            ]);
        }

        $subject = Asignatura::find($validated['asignatura_id']);
        $labor = $validated['labor'] ?? 'Revision de propuesta: ' . ($subject?->nombre ?? 'Materia');

        $group = SubjectGroup::findOrFail($validated['subject_group_id']);
        app(\App\Services\AcademicWorkflowService::class)->requireOpen((int) $group->periodo_id);
        $course = \App\Models\Course::where('grupo_id', $group->id)->where('asignatura_id', $validated['asignatura_id'])->where('activo', true)->firstOrFail();
        DB::transaction(function () use ($course, $teacher, $labor, $validated) {
            $key = ['curso_id' => $course->id, 'docente_id' => $teacher->id];
            $before = DB::table('curso_docentes')->where($key)->first();
            DB::table('curso_docentes')->updateOrInsert($key, ['labor' => $labor, 'activo' => $validated['activo'] ?? true, 'asignado_en' => now()]);
            app(\App\Services\AcademicWorkflowService::class)->audit('curso.docente', 'curso', $course->id, $before, $validated);
        });
        $assignment = $this->assignmentsForGroup($group)->first(fn ($row) => $row->teacher_id === $teacher->id && (int) $row->asignatura_id === (int) $course->asignatura_id);
        return response()->json(['message' => 'Docente responsable asignado', 'assignment' => $assignment], 201);
    }

    public function destroyAssignment(string $id)
    {
        $parts = explode('-', $id, 2);
        abort_unless(count($parts) === 2 && ctype_digit($parts[0]) && ctype_xdigit($parts[1]) && strlen($parts[1]) % 2 === 0, 422, 'Actualiza la lista de asignaciones.');
        $course = \App\Models\Course::findOrFail((int) $parts[0]);
        app(\App\Services\AcademicWorkflowService::class)->requireOpen((int) $course->group->periodo_id);
        $query = DB::table('curso_docentes')->where('curso_id', $course->id)->where('docente_id', hex2bin($parts[1]));
        $old = (clone $query)->first(); abort_unless($old, 404);
        $query->update(['activo' => false]);
        app(\App\Services\AcademicWorkflowService::class)->audit('curso.docente_retirado', 'curso', $course->id, $old, ['activo' => false]);
        return response()->json(['message' => 'Asignación desactivada; se conserva el historial']);
    }

    public function storeException(Request $request)
    {
        return response()->json(['message' => 'Solicita una autorización de inscripción por semestre desde Operación académica. La inscripción al curso determina el acceso a propuestas.'], 409);
    }

    public function destroyException($id)
    {
        return response()->json(['message' => 'Las excepciones históricas se conservan. Gestiona las autorizaciones vigentes desde Operación académica.'], 409);
    }

    private function assignmentsForGroup(SubjectGroup $group)
    {
        return DB::table('curso_docentes as cd')->join('cursos as c', 'c.id', '=', 'cd.curso_id')->where('c.grupo_id', $group->id)
            ->where('cd.activo', true)->where('c.activo', true)
            ->get(['cd.*', 'c.asignatura_id'])->map(function ($row) use ($group) {
                $row->id = $row->curso_id.'-'.bin2hex($row->docente_id);
                $row->teacher_id = $row->docente_id; $row->subject_group_id = $group->id;
                $row->teacher = User::find($row->docente_id);
                return $row;
            });
    }

    /**
     * Estado del alumno en el registro de propuestas: su grupo del periodo, la ventana de la carrera,
     * su propuesta (si ya tiene) con las solicitudes de unión que recibe, o su solicitud enviada.
     */
    public function studentStatus()
    {
        $student = auth('api')->user();
        if ((int) $student->perfil_id !== 3) {
            return response()->json(['message' => 'Solo estudiantes.'], 403);
        }

        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $enrollment = $workflow->enrollment($student->id);
        $group = $enrollment ? SubjectGroup::find($enrollment->grupo_id) : null;
        $project = $this->studentProject($student->id);
        $lastRejected = $project ? null : Project::where('tipo', 'propuesta')->where('estado', 'rechazado')
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $student->id))
            ->orderByDesc('revisado_en')->first(['id', 'titulo', 'estado', 'comentario_revision', 'revisado_en']);
        $myRequest = $project ? null : DB::table('solicitudes_union_proyecto as s')->join('proyectos as p', 'p.id', '=', 's.proyecto_id')
            ->where('s.estudiante_id', $student->id)->where('s.estado', 'pendiente')
            ->first(['s.id', 's.proyecto_id', 's.creada_en', 'p.titulo as project_title']);
        // En la vista simulada la cuenta es la del administrador: no hay perfil de alumno que completar.
        $profileRequired = !app(\App\Support\CareerContext::class)->simulatedProfile()
            && (!$student->profile_completed_at || !$student->nombres || !$student->apa || !$student->semestre || !$student->grupo);
        $window = $this->proposalWindow();

        return response()->json([
            'profile_required' => $profileRequired,
            'student' => $student,
            'subject_group' => $group,
            'window' => $window,
            'active_window' => $window['open'] ? ['ends_at' => $window['closes_at']] : null,
            'project' => $project ? $this->proposalPayload($project, true) : null,
            'last_rejected' => $lastRejected,
            'my_request' => $myRequest,
            'max_members' => $this->maxMembers(),
            'can_register' => !$project && !$myRequest && $window['open'] && $group && !$profileRequired,
        ]);
    }

    /** Propuestas del grupo del alumno a las que puede pedir unirse (primer nombre y apellido de cada integrante). */
    public function groupProjects()
    {
        $student = $this->requireStudent();
        $enrollment = app(\App\Services\AcademicWorkflowService::class)->enrollment($student->id);
        if (!$enrollment) {
            return response()->json(['projects' => []]);
        }
        $max = $this->maxMembers();
        $requested = DB::table('solicitudes_union_proyecto')->where('estudiante_id', $student->id)->where('estado', 'pendiente')->value('proyecto_id');

        $projects = Project::with('students:id,nombres,apellido_paterno')
            ->where('tipo', 'propuesta')->where('activo', true)->where('grupo_id', $enrollment->grupo_id)
            ->where('estado', '!=', 'rechazado')
            ->orderBy('titulo')->get(['id', 'titulo', 'estado', 'grupo_id'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'title' => $project->titulo,
                'proposal_status' => $project->estado,
                'members' => $project->students->map(fn (User $member) => trim(strtok((string) $member->nombres, ' ') . ' ' . $member->apa))->values(),
                'members_count' => $project->students->count(),
                'slots' => max(0, $max - $project->students->count()),
                'requested' => (int) $requested === (int) $project->id,
            ]);

        return response()->json(['projects' => $projects, 'max_members' => $max]);
    }

    public function register(Request $request)
    {
        $student = $this->requireStudent();
        $validated = $request->validate($this->proposalRules(), [], $this->proposalAttributes());
        $group = $this->guardStudentCanRegister($student);
        app(\App\Services\AcademicWorkflowService::class)->requireOpen((int) $group->periodo_id);

        $project = DB::transaction(function () use ($validated, $student, $group) {
            $company = new \App\Models\Empresa();
            $this->fillCompany($company, $validated);
            $company->estado_validacion = 'pendiente';
            $company->solicitada_por = $student->id;
            $company->save();

            $project = Project::create([
                'title' => trim($validated['title']),
                'description' => trim($validated['description']),
                'objetivo_general' => trim($validated['objetivo_general']),
                'subject_group_id' => $group->id,
                'empresa_id' => $company->id,
                'proposal_status' => 'pendiente',
                'tipo' => 'propuesta',
                'modalidad' => 'proyecto_integrador',
                'created_by' => $student->id,
            ]);
            $this->syncObjectives($project, $validated['objetivos']);
            DB::table('proyecto_integrantes')->insert(['proyecto_id' => $project->id, 'usuario_id' => $student->id, 'rol' => 'integrante', 'agregado_en' => now()]);

            return $project;
        });

        if (DB::table('empresas')->where('id', $project->empresa_id)->where('estado_validacion', 'pendiente')->exists()) {
            \App\Services\ActivityNotificationService::send(User::withCareerProfiles([5, 6, 7])->where('activo', true)->pluck('id'), $student->id, 'empresa_por_revisar', 'Empresa por revisar',
                "«{$project->titulo}» registró una empresa nueva.", '/companies');
        }
        \App\Services\ActivityNotificationService::send($this->validatorIds(), $student->id, 'propuesta_registrada', 'Nueva propuesta por validar',
            "{$project->titulo} ({$group->nombre}) espera validación.", '/proposals');

        return response()->json(['message' => 'Propuesta registrada. Quedó pendiente de validación.', 'project' => $this->proposalPayload($project->fresh(), true)], 201);
    }

    /** Corrección de la propuesta mientras la ventana de cambios que dio el validador siga abierta. */
    public function updateMine(Request $request)
    {
        $student = $this->requireStudent();
        $project = $this->studentProject($student->id);
        abort_unless($project && $project->is_proposal, 404, 'No tienes una propuesta registrada.');
        abort_unless($project->estado === 'requiere_cambios' && $project->revision_permitida_hasta && now()->lessThan($project->revision_permitida_hasta), 422,
            'Tu propuesta no tiene una ventana activa de corrección.');
        $validated = $request->validate($this->proposalRules(), [], $this->proposalAttributes());

        DB::transaction(function () use ($project, $validated) {
            $company = $project->empresa ?: new \App\Models\Empresa(['estado_validacion' => 'pendiente', 'solicitada_por' => auth('api')->id()]);
            $this->fillCompany($company, $validated);
            $company->save();
            $project->update([
                'title' => trim($validated['title']),
                'description' => trim($validated['description']),
                'objetivo_general' => trim($validated['objetivo_general']),
                'empresa_id' => $company->id,
                'proposal_status' => 'pendiente',
                'proposal_review_comment' => null,
                'proposal_reviewed_by' => null,
                'proposal_reviewed_at' => null,
                'revision_allowed_until' => null,
            ]);
            $this->syncObjectives($project, $validated['objetivos']);
        });

        \App\Services\ActivityNotificationService::send($this->validatorIds(), $student->id, 'propuesta_corregida', 'Propuesta corregida',
            "{$project->titulo} se corrigió y espera validación.", '/proposals');

        return response()->json(['message' => 'Corrección enviada', 'project' => $this->proposalPayload($project->fresh(), true)]);
    }

    public function requestJoin($id)
    {
        $student = $this->requireStudent();
        $group = $this->guardStudentCanRegister($student);
        $project = Project::with('students:id')->where('tipo', 'propuesta')->where('activo', true)->findOrFail($id);
        abort_unless((int) $project->grupo_id === (int) $group->id, 422, 'Solo puedes unirte a propuestas de tu grupo.');
        abort_if($project->estado === 'rechazado', 422, 'Esa propuesta fue rechazada.');
        abort_if($project->students->count() >= $this->maxMembers(), 422, 'Esa propuesta ya tiene el máximo de integrantes.');

        $requestId = DB::table('solicitudes_union_proyecto')->insertGetId(['proyecto_id' => $project->id, 'estudiante_id' => $student->id, 'estado' => 'pendiente', 'creada_en' => now()]);
        \App\Services\ActivityNotificationService::send($project->students->pluck('id'), $student->id, 'solicitud_union', 'Solicitud para unirse a tu proyecto',
            trim("{$student->nombres} {$student->apa}") . " quiere unirse a {$project->titulo}.", '/proposals');

        return response()->json(['message' => 'Solicitud enviada. Un integrante del proyecto debe autorizarla.', 'request_id' => $requestId], 201);
    }

    public function cancelJoin($id)
    {
        $student = $this->requireStudent();
        $updated = DB::table('solicitudes_union_proyecto')->where('id', $id)->where('estudiante_id', $student->id)->where('estado', 'pendiente')
            ->update(['estado' => 'cancelada', 'respondida_en' => now()]);
        abort_unless($updated, 404, 'La solicitud ya no está pendiente.');

        return response()->json(['message' => 'Solicitud cancelada']);
    }

    /** Un integrante acepta o rechaza a quien pidió unirse; aceptar respeta el máximo de integrantes. */
    public function answerJoin(Request $request, $id)
    {
        $student = $this->requireStudent();
        $validated = $request->validate(['decision' => 'required|in:aceptada,rechazada']);

        $result = DB::transaction(function () use ($id, $student, $validated) {
            $joinRequest = DB::table('solicitudes_union_proyecto')->where('id', $id)->lockForUpdate()->first();
            abort_unless($joinRequest && $joinRequest->estado === 'pendiente', 404, 'La solicitud ya fue atendida.');
            $project = Project::with('students:id')->lockForUpdate()->findOrFail($joinRequest->proyecto_id);
            abort_unless($project->students->contains('id', $student->id), 403, 'Solo los integrantes del proyecto pueden responder.');

            if ($validated['decision'] === 'aceptada') {
                abort_if($project->students->count() >= $this->maxMembers(), 422, 'El proyecto ya tiene el máximo de integrantes.');
                abort_if($this->studentProject($joinRequest->estudiante_id), 422, 'Ese alumno ya forma parte de otro proyecto.');
                DB::table('proyecto_integrantes')->insert(['proyecto_id' => $project->id, 'usuario_id' => $joinRequest->estudiante_id, 'rol' => 'integrante', 'agregado_en' => now()]);
                app(\App\Services\AcademicWorkflowService::class)->audit('proyecto.solicitud_aceptada', 'proyecto', $project->id, null, ['estudiante_id' => $joinRequest->estudiante_id]);
            }
            DB::table('solicitudes_union_proyecto')->where('id', $id)->update(['estado' => $validated['decision'], 'respondida_por' => $student->id, 'respondida_en' => now()]);

            return [$joinRequest, $project];
        });
        [$joinRequest, $project] = $result;
        $accepted = $validated['decision'] === 'aceptada';
        \App\Services\ActivityNotificationService::send([$joinRequest->estudiante_id], $student->id, 'solicitud_union_respuesta',
            $accepted ? 'Te aceptaron en el proyecto' : 'Solicitud rechazada',
            $accepted ? "Ya formas parte de {$project->titulo}." : "No se aceptó tu solicitud para {$project->titulo}.", '/proposals');

        return response()->json(['message' => $accepted ? 'Integrante agregado' : 'Solicitud rechazada']);
    }

    /** Propuestas de la carrera para quien valida (coordinación, jefatura, asistencia o docente designado). */
    public function teacherProjects(Request $request)
    {
        $user = auth('api')->user();
        if (!$this->isValidator($user)) {
            return response()->json(['is_validator' => false, 'projects' => []]);
        }

        $projects = Project::with(['students:id,nombres,apellido_paterno,apellido_materno', 'subjectGroup', 'proposalReviewer:id,nombres,apellido_paterno', 'objectives', 'empresa'])
            ->where('tipo', 'propuesta')->where('activo', true)
            ->when($request->filled('status'), fn ($query) => $query->where('estado', $request->query('status')))
            ->orderByRaw("FIELD(estado, 'pendiente', 'requiere_cambios', 'aprobado', 'rechazado')")
            ->orderByDesc('creado_en')->get()
            ->map(fn (Project $project) => $this->proposalPayload($project));

        return response()->json(['is_validator' => true, 'projects' => $projects]);
    }

    public function review(Request $request, $id)
    {
        $validator = auth('api')->user();
        abort_unless($this->isValidator($validator), 403, 'Solo coordinación, jefatura o docentes validadores pueden validar propuestas.');

        $project = Project::findOrFail($id);
        if (!$project->is_proposal) {
            return response()->json(['message' => 'El registro seleccionado no es una propuesta.'], 422);
        }

        $validated = $request->validate([
            'proposal_status' => 'required|in:aprobado,requiere_cambios,rechazado',
            'proposal_review_comment' => 'nullable|required_unless:proposal_status,aprobado|string|max:3000',
            'revision_allowed_until' => 'nullable|required_if:proposal_status,requiere_cambios|date|after:now',
        ], [], ['proposal_review_comment' => 'observaciones', 'revision_allowed_until' => 'fecha límite de corrección']);

        $project->update([
            'proposal_status' => $validated['proposal_status'],
            'proposal_review_comment' => $validated['proposal_review_comment'] ?? null,
            'revision_allowed_until' => $validated['proposal_status'] === 'requiere_cambios' ? $validated['revision_allowed_until'] : null,
            'proposal_reviewed_by' => $validator->id,
            'proposal_reviewed_at' => now(),
        ]);
        if ($validated['proposal_status'] === 'aprobado' && $project->empresa && $project->empresa->estado_validacion === 'pendiente') {
            $project->empresa->update(['estado_validacion' => 'aprobada', 'validada_por' => $validator->id, 'validada_en' => now()]);
        }
        if ($validated['proposal_status'] === 'rechazado') {
            DB::table('solicitudes_union_proyecto')->where('proyecto_id', $project->id)->where('estado', 'pendiente')->update(['estado' => 'cancelada', 'respondida_en' => now()]);
        }

        $labels = ['aprobado' => 'Propuesta aprobada', 'requiere_cambios' => 'Tu propuesta requiere cambios', 'rechazado' => 'Propuesta rechazada'];
        \App\Services\ActivityNotificationService::send($project->students()->pluck('usuarios.id'), $validator->id, 'propuesta_revisada',
            $labels[$validated['proposal_status']], $project->titulo, '/proposals');

        return response()->json(['message' => 'Revisión registrada', 'project' => $this->proposalPayload($project->fresh())]);
    }

    /** Ventanas de autoregistro y de propuestas, y docentes validadores de la carrera activa. */
    public function settings()
    {
        $user = auth('api')->user();
        $careerId = app(\App\Services\AcademicWorkflowService::class)->careerId();
        $selfUntil = SelfRegistrationController::openUntilFor($careerId);
        // Una ventana abierta antes de que los grupos de alumnos nuevos se crearan solos se completa al consultarla.
        $period = app(\App\Services\AcademicWorkflowService::class)->period();
        $semester = \App\Support\NewStudentGroups::semester($careerId);
        if ($selfUntil?->isFuture() && $period && \App\Support\NewStudentGroups::allowedIn($period, $semester)) {
            \App\Support\NewStudentGroups::ensure($careerId, (int) $period->id);
        }

        return response()->json([
            'self_registration' => [
                'until' => $selfUntil?->toIso8601String(),
                'open' => (bool) $selfUntil?->isFuture(),
                // La ventana puede estar abierta y aun así no mostrarse «Crear cuenta»: se le dice a quien la administra.
                'blocker' => $selfUntil?->isFuture() ? SelfRegistrationController::blockerFor(\App\Models\Career::findOrFail($careerId)) : null,
                'can_manage' => $this->canManageSelfRegistration($user),
                'groups' => \App\Support\NewStudentGroups::letters((int) app(\App\Services\AcademicWorkflowService::class)->careerId()),
                // Semestre al que entran los alumnos nuevos y los que admite el periodo abierto (5° por defecto).
                'semester' => $semester,
                'semester_options' => $period ? \App\Support\NewStudentGroups::optionsFor($period) : [],
                'rule' => \App\Support\SelfRegistrationRule::publicPayload(\App\Support\SelfRegistrationRule::forCareer($careerId)),
            ],
            'proposal_window' => $this->proposalWindow() + ['until' => CareerSetting::valueFor('proposal_registration_until')],
            'period' => app(\App\Services\AcademicWorkflowService::class)->period()?->only(['id', 'nombre']),
            'validator_ids' => CareerSetting::valueFor('proposal_validator_teacher_ids', []),
            'teachers' => User::withCareerProfiles([2])->where('activo', true)->orderBy('nombres')->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno']),
            'max_project_members' => $this->maxMembers(),
        ]);
    }

    /** Letras de los grupos de 5° que elige un alumno nuevo. Quitar una no borra un grupo que ya tenga alumnos. */
    public function updateNewStudentGroups(Request $request)
    {
        abort_unless($this->canManageSelfRegistration(auth('api')->user()), 403, 'Los grupos los define la administración general o la jefatura.');
        $validated = $request->validate(['groups' => 'required|array|min:1|max:10', 'groups.*' => 'required|string|max:3|regex:/^[A-Za-z0-9]+$/'], [], ['groups' => 'grupos']);
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $career = (int) $workflow->careerId();
        $letters = collect($validated['groups'])->map(fn ($letter) => strtoupper($letter))->unique()->values()->all();
        CareerSetting::setValue('new_student_groups', $letters, 'array', 'Grupos para alumnos nuevos');
        $period = $workflow->period();
        if ($period && \App\Support\NewStudentGroups::allowedIn($period, \App\Support\NewStudentGroups::semester($career))) {
            \App\Support\NewStudentGroups::ensure($career, (int) $period->id);
        }

        return response()->json(['message' => 'Grupos guardados', 'groups' => $letters]);
    }

    public function updateSelfRegistration(Request $request)
    {
        abort_unless($this->canManageSelfRegistration(auth('api')->user()), 403, 'El autoregistro lo abre la administración general o la jefatura.');
        $validated = $request->validate([
            'until' => 'nullable|date|after:now',
            // Sin elegir nada, los alumnos nuevos entran a 5°.
            'semester' => ['nullable', 'integer', \Illuminate\Validation\Rule::in(\App\Support\NewStudentGroups::SEMESTERS)],
        ], [], ['until' => 'fecha de cierre', 'semester' => 'semestre']);
        if (!empty($validated['until'])) {
            $period = app(\App\Services\AcademicWorkflowService::class)->period();
            abort_unless($period, 409, 'Abre el periodo de la carrera antes de habilitar el autoregistro.');
            $semester = (int) ($validated['semester'] ?? \App\Support\NewStudentGroups::DEFAULT_SEMESTER);
            abort_unless(\App\Support\NewStudentGroups::allowedIn($period, $semester), 409,
                "El periodo {$period->nombre} es de ".\App\Support\NewStudentGroups::halfLabel($period).": no tiene {$semester}° semestre. Elige "
                .implode(' o ', array_map(fn ($option) => "{$option}°", \App\Support\NewStudentGroups::optionsFor($period))).'.');
            abort_unless(Carbon::parse($validated['until'])->lte(Carbon::parse($period->fecha_fin)->endOfDay()), 422, 'La fecha de cierre debe estar dentro del periodo abierto.');
            CareerSetting::setValue('new_student_semester', $semester, 'integer', 'Semestre de los alumnos nuevos');
            \App\Support\NewStudentGroups::ensure((int) app(\App\Services\AcademicWorkflowService::class)->careerId(), (int) $period->id);
        }
        $until = empty($validated['until']) ? null : Carbon::parse($validated['until'])->toIso8601String();
        CareerSetting::setValue('self_registration_until', $until, 'string', 'Cierre del autoregistro de alumnos');
        app(\App\Services\AcademicWorkflowService::class)->audit('autoregistro.ventana', 'carrera', app(\App\Services\AcademicWorkflowService::class)->careerId(), null, ['hasta' => $until, 'semestre' => empty($validated['until']) ? null : \App\Support\NewStudentGroups::semester((int) app(\App\Services\AcademicWorkflowService::class)->careerId())]);

        return response()->json(['message' => $until ? 'Autoregistro abierto' : 'Autoregistro cerrado', 'until' => $until]);
    }

    public function updateProposalWindow(Request $request)
    {
        $validated = $request->validate(['until' => 'nullable|date|after:now'], [], ['until' => 'fecha de cierre']);
        $until = empty($validated['until']) ? null : Carbon::parse($validated['until'])->toIso8601String();
        CareerSetting::setValue('proposal_registration_until', $until, 'string', 'Cierre del registro de propuestas');
        app(\App\Services\AcademicWorkflowService::class)->audit('propuestas.ventana', 'carrera', app(\App\Services\AcademicWorkflowService::class)->careerId(), null, ['hasta' => $until]);

        return response()->json(['message' => $until ? 'Registro de propuestas abierto' : 'Registro de propuestas cerrado', 'until' => $until]);
    }

    public function updateValidators(Request $request)
    {
        $validated = $request->validate(['teacher_ids' => 'present|array', 'teacher_ids.*' => 'string|distinct']);
        $valid = User::withCareerProfiles([2])->where('activo', true)->whereIn('id', $validated['teacher_ids'])->pluck('id')->all();
        if (count($valid) !== count($validated['teacher_ids'])) {
            throw ValidationException::withMessages(['teacher_ids' => ['Solo puedes designar docentes activos de la carrera.']]);
        }
        CareerSetting::setValue('proposal_validator_teacher_ids', $valid);

        return response()->json(['message' => 'Docentes validadores guardados', 'validator_ids' => $valid]);
    }

    private function requireStudent(): User
    {
        $student = auth('api')->user();
        abort_unless((int) $student->perfil_id === 3, 403, 'Solo estudiantes.');
        abort_if(app(\App\Support\CareerContext::class)->simulatedProfile(), 403, 'La vista simulada es de solo lectura.');

        return $student;
    }

    /** El alumno puede registrar o pedir unirse: perfil completo, inscrito en un grupo, ventana abierta y sin proyecto. */
    private function guardStudentCanRegister(User $student): SubjectGroup
    {
        abort_unless($this->proposalWindow()['open'], 422, 'El registro de propuestas de tu carrera está cerrado.');
        abort_unless($student->profile_completed_at, 422, 'Completa tu perfil antes de registrar una propuesta.');
        $enrollment = app(\App\Services\AcademicWorkflowService::class)->enrollment($student->id);
        $group = $enrollment ? SubjectGroup::find($enrollment->grupo_id) : null;
        abort_unless($group && $group->activo, 422, 'No tienes un grupo asignado en el periodo actual. Pide a la jefatura que te asigne uno.');
        abort_if($this->studentProject($student->id), 422, 'Ya formas parte de un proyecto.');
        abort_if(DB::table('solicitudes_union_proyecto')->where('estudiante_id', $student->id)->where('estado', 'pendiente')->exists(), 422,
            'Tienes una solicitud pendiente para unirte a un proyecto; cancélala primero.');

        return $group;
    }

    /** Proyecto vigente del alumno; una propuesta rechazada ya no lo ata. */
    private function studentProject(string $studentId): ?Project
    {
        return Project::where('activo', true)
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $studentId))
            ->where(fn ($query) => $query->where('tipo', '!=', 'propuesta')->orWhere('estado', '!=', 'rechazado'))
            ->orderByDesc('creado_en')->first();
    }

    private function proposalPayload(Project $project, bool $forMember = false): array
    {
        $project->loadMissing(['students:id,nombres,apellido_paterno,apellido_materno', 'subjectGroup', 'objectives', 'empresa', 'proposalReviewer:id,nombres,apellido_paterno']);
        $payload = $project->toArray();
        $payload['objetivos'] = $project->objectives->pluck('descripcion')->values();
        $payload['max_members'] = $this->maxMembers();
        if ($forMember) {
            $payload['join_requests'] = DB::table('solicitudes_union_proyecto as s')->join('usuarios as u', 'u.id', '=', 's.estudiante_id')
                ->where('s.proyecto_id', $project->id)->where('s.estado', 'pendiente')->orderBy('s.creada_en')
                ->get(['s.id', 's.estudiante_id', 's.creada_en', 'u.nombres', 'u.apellido_paterno']);
        }

        return $payload;
    }

    private function proposalRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'objetivo_general' => 'required|string|max:3000',
            'objetivos' => 'required|array|min:1|max:12',
            'objetivos.*' => 'required|string|max:1000',
            'company_name' => 'required|string|max:255',
            'company_giro' => 'required|string|max:255',
            'company_contact_name' => 'required|string|max:255',
            'company_contact_position' => 'required|string|max:255',
            'company_address' => 'required|string|max:1000',
            'company_contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]{7,30}$/'],
            'company_contact_email' => 'nullable|email|max:255',
        ];
    }

    private function proposalAttributes(): array
    {
        return [
            'title' => 'nombre del proyecto', 'description' => 'descripción', 'objetivo_general' => 'objetivo general',
            'objetivos' => 'objetivos específicos', 'objetivos.*' => 'objetivo específico', 'company_name' => 'empresa o negocio',
            'company_giro' => 'giro', 'company_contact_name' => 'persona a cargo', 'company_contact_position' => 'puesto',
            'company_address' => 'dirección', 'company_contact_phone' => 'teléfono de contacto', 'company_contact_email' => 'correo de contacto',
        ];
    }

    private function fillCompany(\App\Models\Empresa $company, array $validated): void
    {
        $company->fill([
            'nombre' => trim($validated['company_name']),
            'giro' => trim($validated['company_giro']),
            'contacto_nombre' => trim($validated['company_contact_name']),
            'contacto_cargo' => trim($validated['company_contact_position']),
            'contacto_telefono' => trim((string) ($validated['company_contact_phone'] ?? '')) ?: null,
            'contacto_correo' => strtolower(trim((string) ($validated['company_contact_email'] ?? ''))) ?: null,
            'direccion' => trim($validated['company_address']),
        ]);
    }

    private function syncObjectives(Project $project, array $objectives): void
    {
        DB::table('proyecto_objetivos')->where('proyecto_id', $project->id)->delete();
        $rows = collect($objectives)->map(fn ($text) => trim((string) $text))->filter()->values()
            ->map(fn ($text, $index) => ['proyecto_id' => $project->id, 'orden' => $index + 1, 'descripcion' => $text]);
        DB::table('proyecto_objetivos')->insert($rows->all());
    }

    private function proposalWindow(): array
    {
        $until = CareerSetting::valueFor('proposal_registration_until');
        $closes = $until ? Carbon::parse($until) : null;

        return ['open' => (bool) $closes?->isFuture(), 'closes_at' => $closes?->toIso8601String()];
    }

    private function maxMembers(): int
    {
        return (int) CareerSetting::valueFor('max_project_members', 4);
    }

    private function isValidator(User $user): bool
    {
        return $user->canManageProjects() || ((int) $user->perfil_id === 2 && in_array((string) $user->id, array_map('strval', CareerSetting::valueFor('proposal_validator_teacher_ids', [])), true));
    }

    private function canManageSelfRegistration(User $user): bool
    {
        return $user->isGeneralAdmin() || in_array((int) $user->perfil_id, [1, 5, 6], true);
    }

    /** Quienes reciben aviso de una propuesta nueva: coordinación, jefatura, asistencia y docentes validadores. */
    private function validatorIds(): array
    {
        return User::withCareerProfiles([5, 6, 7])->where('activo', true)->pluck('id')
            ->merge(CareerSetting::valueFor('proposal_validator_teacher_ids', []))->unique()->values()->all();
    }

    public function searchStudents(Request $request)
    {
        $user = auth('api')->user();
        if (!in_array((int) $user->perfil_id, [1, 2, 3, 5, 6, 7], true)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $term = trim((string) $request->query('q', ''));
        $query = User::students()->where('activo', true)
            ->when((int) $user->perfil_id === 3, fn ($q) => $q->where('id', '!=', $user->id))
            ->whereDoesntHave('projectsAsAdvisor', fn ($q) => $q->where('proyecto_integrantes.rol', 'integrante'));

        if ($term !== '') {
            \App\Support\TextSearch::apply($query, $term, ['id', 'nombres', 'apa', 'ama']);
        }

        // Sólo lo necesario para identificar a un compañero: nunca correo, teléfono ni datos de la cuenta.
        return response()->json($query->orderBy('nombres')->limit(20)->get()->map(fn (User $student) => [
            'id' => $student->id, 'nombres' => $student->nombres, 'apa' => $student->apa, 'ama' => $student->ama,
            'semestre' => $student->semestre, 'grupo' => $student->grupo,
        ])->values());
    }
    public function windowGroups()
    {
        $user = auth('api')->user();
        $defaultSubject = $this->defaultProposalSubject();
        $query = SubjectGroup::with(['asignaturas'])
            ->where('periodo_id', app(\App\Services\AcademicWorkflowService::class)->period()?->id ?? 0)
            ->where('semestre', 5)
            ->whereHas('asignaturas', fn ($subjectQuery) => $subjectQuery->where('asignaturas.id', $defaultSubject->id));

        $groups = $query->orderBy('nombre')->get()
            ->each(function (SubjectGroup $group) {
                $window = $this->windowPayloadFromGroup($group);
                $group->setRelation('registrationWindows', collect($window ? [$window] : []));
                $group->setRelation('teacherAssignments', $this->assignmentsForGroup($group));
            });

        return response()->json([
            'default_subject' => $defaultSubject,
            'groups' => $groups,
        ]);
    }

    private function defaultProposalSubject(): Asignatura
    {
        return Asignatura::where('nombre', self::DEFAULT_PROPOSAL_SUBJECT)->firstOrFail();
    }

    private function guardCanManageWindow(int $groupId): void
    {
        $group = SubjectGroup::findOrFail($groupId);
        app(\App\Services\AcademicWorkflowService::class)->requireOpen((int) $group->periodo_id);
        $user = auth('api')->user();
        if ($user->canManageProjects()) {
            return;
        }

        $allowed = DB::table('curso_docentes')
            ->join('cursos', 'cursos.id', '=', 'curso_docentes.curso_id')
            ->where('cursos.grupo_id', $groupId)
            ->where('curso_docentes.docente_id', $user->id)
            ->where('curso_docentes.activo', true)
            ->exists();

        if (!$allowed) {
            abort(403, 'Solo los docentes responsables de esta carga pueden administrar su ventana.');
        }
    }

    private function windowPayloadFromGroup(?SubjectGroup $group): ?array
    {
        if (!$group || !$group->registro_proyectos_desde || !$group->registro_proyectos_hasta) {
            return null;
        }

        return [
            'id' => $group->id,
            'subject_group_id' => $group->id,
            'starts_at' => $group->registro_proyectos_desde,
            'ends_at' => $group->registro_proyectos_hasta,
            'activo' => true,
            'notes' => null,
        ];
    }
}
