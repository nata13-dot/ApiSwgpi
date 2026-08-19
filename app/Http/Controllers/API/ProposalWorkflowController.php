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
        $subjectGroups = SubjectGroup::with(['asignaturas', 'teacherAssignments.teacher'])
            ->where('semestre', 5)
            ->whereHas('asignaturas', fn ($query) => $query->where('asignaturas.id', $defaultSubject->id))
            ->orderBy('semestre')
            ->orderBy('nombre')
            ->get()
            ->each(function (SubjectGroup $group) {
                $window = $this->windowPayloadFromGroup($group);
                $group->setRelation('registrationWindows', collect($window ? [$window] : []));
            });

        $exceptions = Schema::hasTable('excepciones_revision_propuesta')
            ? ProposalReviewException::with(['asignatura:id,nombre', 'subjectGroup:id,nombre,semestre,grupo', 'teacher:id,nombres,apa,ama', 'student:id,nombres,apa,ama,semestre,grupo'])
                ->where('activo', true)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return response()->json([
            'default_subject' => $defaultSubject,
            'subject_groups' => $subjectGroups,
            'grupos_academicos' => $subjectGroups,
            'teachers' => User::teachers()->where('activo', true)->orderBy('nombres')->get(['id', 'nombres', 'apa', 'ama']),
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
            'teacher_id' => ['required', Rule::exists('usuarios', 'id')->where('activo', true)->where('perfil_id', 2)],
            'labor' => 'nullable|string|max:120',
            'activo' => 'nullable|boolean',
        ]);

        $teacher = User::where('id', $validated['teacher_id'])->teachers()->where('activo', true)->first();
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

        $assignment = TeacherGroupAssignment::updateOrCreate(
            [
                'subject_group_id' => $validated['subject_group_id'],
                'asignatura_id' => $validated['asignatura_id'],
                'teacher_id' => $validated['teacher_id'],
            ],
            [
                'labor' => $labor,
                'activo' => $validated['activo'] ?? true,
            ]
        );

        return response()->json(['message' => 'Docente responsable asignado', 'assignment' => $assignment->load('teacher')], 201);
    }

    public function destroyAssignment($id)
    {
        TeacherGroupAssignment::findOrFail($id)->delete();
        return response()->json(['message' => 'Responsable removido']);
    }

    public function storeException(Request $request)
    {
        if (!Schema::hasTable('excepciones_revision_propuesta')) {
            return response()->json([
                'message' => 'El esquema v2 no conserva excepciones de propuesta independientes del proyecto.',
            ], 422);
        }

        $defaultSubject = $this->defaultProposalSubject();
        $validated = $request->validate([
            'subject_group_id' => 'required|exists:grupos_academicos,id',
            'student_id' => ['required', Rule::exists('usuarios', 'id')->where('activo', true)->where('perfil_id', 3)],
            'notes' => 'nullable|string|max:1000',
        ]);

        $group = SubjectGroup::where('id', $validated['subject_group_id'])
            ->where('semestre', 5)
            ->where('activo', true)
            ->whereHas('asignaturas', fn ($query) => $query->where('asignaturas.id', $defaultSubject->id))
            ->first();
        if (!$group) {
            throw ValidationException::withMessages([
                'subject_group_id' => ['Selecciona una carga activa de quinto semestre que incluya la materia predeterminada.'],
            ]);
        }

        $exception = ProposalReviewException::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'asignatura_id' => $defaultSubject->id,
            ],
            [
                'subject_group_id' => $group->id,
                'teacher_id' => null,
                'notes' => $validated['notes'] ?? null,
                'activo' => true,
            ]
        );

        return response()->json(['message' => 'Excepcion de alumno registrada', 'exception' => $exception->load(['teacher', 'student', 'asignatura', 'subjectGroup'])], 201);
    }

    public function destroyException($id)
    {
        if (!Schema::hasTable('excepciones_revision_propuesta')) {
            return response()->json(['message' => 'Excepcion no encontrada'], 404);
        }

        ProposalReviewException::findOrFail($id)->update(['activo' => false]);
        return response()->json(['message' => 'Excepcion desactivada']);
    }

    public function studentStatus()
    {
        $student = auth('api')->user();
        if ((int) $student->perfil_id !== 3) {
            return response()->json(['message' => 'Solo estudiantes.'], 403);
        }

        $defaultSubject = $this->defaultProposalSubject();
        $exception = Schema::hasTable('excepciones_revision_propuesta')
            ? ProposalReviewException::where('student_id', $student->id)
                ->where('asignatura_id', $defaultSubject->id)
                ->where('activo', true)
                ->latest('creado_en')
                ->first()
            : null;

        $group = SubjectGroup::query()
            ->where('activo', true)
            ->whereHas('asignaturas', fn ($query) => $query->where('asignaturas.id', $defaultSubject->id))
            ->when(
                $exception,
                fn ($query) => $query->where('id', $exception->subject_group_id),
                fn ($query) => $query->where('semestre', 5)->where('grupo', strtoupper((string) $student->grupo))
            )
            ->first();

        $project = Project::with(['students', 'subjectGroup'])
            ->whereHas('students', fn ($query) => $query->where('usuarios.id', $student->id))
            ->first();

        $window = $group
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
        if (!in_array((int) $user->perfil_id, [1, 2, 3], true)) {
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
        if ((int) $teacher->perfil_id !== 2) {
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
            ->where('semestre', 5)
            ->whereHas('asignaturas', fn ($subjectQuery) => $subjectQuery->where('asignaturas.id', $defaultSubject->id));

        $groups = $query->orderBy('nombre')->get()
            ->each(function (SubjectGroup $group) {
                $window = $this->windowPayloadFromGroup($group);
                $group->setRelation('registrationWindows', collect($window ? [$window] : []));
                $group->setRelation('teacherAssignments', collect());
            });

        return response()->json([
            'default_subject' => $defaultSubject,
            'groups' => $groups,
        ]);
    }

    public function review(Request $request, $id)
    {
        $teacher = auth('api')->user();
        if (!in_array((int) $teacher->perfil_id, [1, 2], true)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $project = Project::findOrFail($id);
        if (!$project->is_proposal) {
            return response()->json(['message' => 'El registro seleccionado no es una propuesta.'], 422);
        }
        if ((int) $teacher->perfil_id === 2) {
            $allowed = TeacherGroupAssignment::where('teacher_id', $teacher->id)
                ->where('subject_group_id', $project->subject_group_id)
                ->where('activo', true)
                ->exists();
            if (!$allowed) {
                $studentIds = $project->students()->pluck('usuarios.id');
                $allowed = ProposalReviewException::where('teacher_id', $teacher->id)
                    ->where('activo', true)
                    ->whereIn('student_id', $studentIds)
                    ->exists();
            }
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
