<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Asignatura;
use App\Models\ProjectRegistrationWindow;
use App\Models\ProposalReviewException;
use App\Models\SubjectGroup;
use App\Models\TeacherGroupAssignment;
use App\Models\User;
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
            ->whereBetween('semestre', [5, 9])
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
            'starts_at' => 'required|date',
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
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
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

        $belongsToGroup = DB::table('cursos')
            ->where('carrera_id', app(\App\Support\CareerContext::class)->careerId())
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
            ->where('c.carrera_id', $group->carrera_id)->where('cd.activo', true)->where('c.activo', true)
            ->get(['cd.*', 'c.asignatura_id'])->map(function ($row) use ($group) {
                $row->id = $row->curso_id.'-'.bin2hex($row->docente_id);
                $row->teacher_id = $row->docente_id; $row->subject_group_id = $group->id;
                $row->teacher = User::find($row->docente_id);
                return $row;
            });
    }

    public function studentStatus()
    {
        $student = auth('api')->user();
        if ((int) $student->perfil_id !== 3) {
            return response()->json(['message' => 'Solo estudiantes.'], 403);
        }

        $defaultSubject = $this->defaultProposalSubject();
        $exception = null;
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $enrollment = $workflow->enrollment($student->id);
        $enrolledGroups = DB::table('curso_estudiantes as ce')->join('cursos as c', 'c.id', '=', 'ce.curso_id')
            ->where('ce.estudiante_id', $student->id)->where('ce.activo', true)->where('c.activo', true)
            ->where('c.carrera_id', $workflow->careerId())->where('c.asignatura_id', $defaultSubject->id)->pluck('c.grupo_id');
        $groups = $enrollment ? SubjectGroup::where('activo', true)->where('periodo_id', $enrollment->periodo_id)->whereIn('id', $enrolledGroups)->orderBy('id')->get() : collect();
        $group = $groups->firstWhere('id', $enrollment?->grupo_id) ?? $groups->first();

        $project = Project::with(['students', 'subjectGroup'])
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $student->id))
            ->first();

        $course = $group ? \App\Models\Course::where('grupo_id', $group->id)->where('asignatura_id', $defaultSubject->id)->where('activo', true)->first() : null;
        $window = $group && $course && app(\App\Services\CourseAdmissionService::class)->enrolled($course->id, $student->id)
            && $group->registro_proyectos_desde
            && $group->registro_proyectos_hasta
            && now()->between($group->registro_proyectos_desde, $group->registro_proyectos_hasta)
                ? $this->windowPayloadFromGroup($group)
                : null;

        $profileRequired = !$student->profile_completed_at
            || !$student->nombres
            || !$student->apa
            || !$student->semestre
            || !$student->grupo;

        return response()->json([
            'profile_required' => $profileRequired,
            'student' => $student,
            'subject_group' => $group,
            'default_subject' => $defaultSubject,
            'has_exception' => (bool) $exception,
            'active_window' => $window,
            'project' => $project,
            'can_register' => !$project && (bool) $window && !$profileRequired,
        ]);
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
            $query->where(function ($q) use ($term) {
                $q->where('id', 'like', "%{$term}%")
                    ->orWhere('nombres', 'like', "%{$term}%")
                    ->orWhere('apa', 'like', "%{$term}%");
            });
        }

        return response()->json($query->orderBy('nombres')->limit(20)->get(['id', 'nombres', 'apa', 'ama', 'semestre', 'grupo']));
    }
    public function teacherProjects()
    {
        $teacher = auth('api')->user();
        if (!in_array((int) $teacher->perfil_id, [1, 2, 5, 6, 7], true)) {
            return response()->json(['message' => 'Solo docentes.'], 403);
        }

        $projects = Project::with(['students', 'subjectGroup', 'creator', 'proposalReviewer'])
            ->where('tipo', 'propuesta')
            ->whereHas('advisors', fn ($query) => $query->where('usuarios.id', $teacher->id))
            ->orderByRaw("FIELD(estado, 'pendiente', 'requiere_cambios', 'aprobado', 'rechazado')")
            ->orderByDesc('created_at')
            ->get();

        return response()->json($projects);
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

    public function review(Request $request, $id)
    {
        $teacher = auth('api')->user();
        if (!in_array((int) $teacher->perfil_id, [1, 2, 5, 6, 7], true)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $project = Project::findOrFail($id);
        if (!$project->is_proposal) {
            return response()->json(['message' => 'El registro seleccionado no es una propuesta.'], 422);
        }
        if (!$teacher->canManageProjects()) {
            $allowed = DB::table('curso_docentes as cd')->join('cursos as c', 'c.id', '=', 'cd.curso_id')
                ->where('cd.docente_id', $teacher->id)->where('cd.activo', true)->where('c.activo', true)
                ->where('c.carrera_id', $project->carrera_id)->where('c.grupo_id', $project->grupo_id)->exists();
            if (!$allowed) {
                return response()->json(['message' => 'Este proyecto no pertenece a tus grupos asignados.'], 403);
            }
        }

        $validated = $request->validate([
            'proposal_status' => 'required|in:aprobado,requiere_cambios,rechazado',
            'proposal_review_comment' => 'nullable|string|max:3000',
            'revision_allowed_until' => 'nullable|required_if:proposal_status,requiere_cambios|date|after:now',
        ]);

        $project->update([
            'proposal_status' => $validated['proposal_status'],
            'proposal_review_comment' => $validated['proposal_review_comment'] ?? null,
            'revision_allowed_until' => $validated['proposal_status'] === 'requiere_cambios' ? $validated['revision_allowed_until'] : null,
            'proposal_reviewed_by' => $teacher->id,
            'proposal_reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Revision registrada', 'project' => $project->load(['students', 'subjectGroup', 'proposalReviewer'])]);
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
