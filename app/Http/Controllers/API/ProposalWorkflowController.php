<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Asignatura;
use App\Models\ProjectRegistrationWindow;
use App\Models\SubjectGroup;
use App\Models\TeacherGroupAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalWorkflowController extends Controller
{
    public function configIndex()
    {
        return response()->json([
            'subject_groups' => SubjectGroup::with(['asignaturas', 'registrationWindows', 'teacherAssignments.teacher', 'teacherAssignments.asignatura'])->orderBy('semestre')->orderBy('nombre')->get(),
            'teachers' => User::where('perfil_id', 2)->where('activo', true)->orderBy('nombres')->get(['id', 'nombres', 'apa', 'ama']),
            'asignaturas' => Asignatura::orderBy('nombre')->get(['id', 'clave', 'nombre']),
        ]);
    }

    public function storeWindow(Request $request)
    {
        $validated = $request->validate([
            'subject_group_id' => 'required|exists:subject_groups,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'activo' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $window = ProjectRegistrationWindow::create($validated);
        return response()->json(['message' => 'Ventana de registro creada', 'window' => $window], 201);
    }

    public function updateWindow(Request $request, $id)
    {
        $window = ProjectRegistrationWindow::findOrFail($id);
        $validated = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'activo' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        $window->update($validated);
        return response()->json(['message' => 'Ventana de registro actualizada', 'window' => $window]);
    }

    public function destroyWindow($id)
    {
        ProjectRegistrationWindow::findOrFail($id)->delete();
        return response()->json(['message' => 'Ventana de registro eliminada']);
    }

    public function storeAssignment(Request $request)
    {
        $validated = $request->validate([
            'subject_group_id' => 'required|exists:subject_groups,id',
            'asignatura_id' => 'required|integer|exists:asignaturas,id',
            'teacher_id' => 'required|exists:users,id',
            'labor' => 'nullable|string|max:120',
            'activo' => 'nullable|boolean',
        ]);

        $teacher = User::where('id', $validated['teacher_id'])->where('perfil_id', 2)->where('activo', true)->first();
        if (!$teacher) {
            throw ValidationException::withMessages(['teacher_id' => ['El responsable debe ser un docente activo.']]);
        }

        $belongsToGroup = DB::table('subject_group_asignatura')
            ->where('subject_group_id', $validated['subject_group_id'])
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
                'teacher_id' => $validated['teacher_id'],
            ],
            [
                'asignatura_id' => $validated['asignatura_id'],
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

    public function studentStatus()
    {
        $student = auth('api')->user();
        if ((int) $student->perfil_id !== 3) {
            return response()->json(['message' => 'Solo estudiantes.'], 403);
        }

        $group = SubjectGroup::with('registrationWindows')
            ->where('semestre', $student->semestre)
            ->where('grupo', strtoupper((string) $student->grupo))
            ->where('activo', true)
            ->first();

        $project = Project::with(['students', 'subjectGroup'])
            ->whereHas('students', fn ($query) => $query->where('users.id', $student->id))
            ->first();

        $window = $group?->registrationWindows()
            ->where('activo', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('ends_at')
            ->first();

        $profileRequired = !$student->profile_completed_at
            || !$student->nombres
            || !$student->apa
            || !$student->semestre
            || !$student->grupo;

        return response()->json([
            'profile_required' => $profileRequired,
            'student' => $student,
            'subject_group' => $group,
            'active_window' => $window,
            'project' => $project,
            'can_register' => !$project && (bool) $window && !$profileRequired,
        ]);
    }

    public function searchStudents(Request $request)
    {
        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 3) {
            return response()->json(['message' => 'Solo estudiantes.'], 403);
        }

        $term = trim((string) $request->query('q', ''));
        $query = User::where('perfil_id', 3)->where('activo', true)
            ->where('id', '!=', $user->id)
            ->whereDoesntHave('projectsAsAdvisor', function ($q) {
                $q->whereNull('project_user.rol_asesor');
            });

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

        $groupIds = TeacherGroupAssignment::where('teacher_id', $teacher->id)->where('activo', true)->pluck('subject_group_id');
        $projects = Project::with(['students', 'subjectGroup', 'creator', 'proposalReviewer'])
            ->whereIn('subject_group_id', $groupIds)
            ->orderByRaw("FIELD(proposal_status, 'pendiente', 'requiere_cambios', 'aprobado', 'rechazado')")
            ->orderByDesc('created_at')
            ->get();

        return response()->json($projects);
    }

    public function review(Request $request, $id)
    {
        $teacher = auth('api')->user();
        if (!in_array((int) $teacher->perfil_id, [1, 2], true)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $project = Project::findOrFail($id);
        if ((int) $teacher->perfil_id === 2) {
            $allowed = TeacherGroupAssignment::where('teacher_id', $teacher->id)
                ->where('subject_group_id', $project->subject_group_id)
                ->where('activo', true)
                ->exists();
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
}
