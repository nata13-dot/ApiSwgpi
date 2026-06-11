<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\TeacherGroupAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const PROPOSAL_STATUSES = ['pendiente', 'aprobado', 'requiere_cambios', 'rechazado'];
    private const DELIVERABLE_STATUSES = ['pendiente', 'enviado', 'revisado', 'aprobado'];

    public function stats()
    {
        $recentProjects = Project::select(['id', 'title', 'created_by', 'created_at'])
            ->with('creator:id,nombres,apa,ama')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $deliverableStatusCounts = $this->statusCounts(
            Deliverable::query()->where('activo', true),
            'estado',
            self::DELIVERABLE_STATUSES
        );
        $projectProposalCounts = $this->statusCounts(
            Project::query()->where('activo', true)->where('is_proposal', true),
            'proposal_status',
            self::PROPOSAL_STATUSES
        );
        $totalDeliverables = array_sum($deliverableStatusCounts);
        $approvedDeliverables = $deliverableStatusCounts['aprobado'] ?? 0;

        return response()->json([
            'stats' => [
                'total_users' => User::count(),
                'active_users' => User::where('activo', true)->count(),
                'inactive_users' => User::where('activo', false)->count(),
                'total_projects' => Project::count(),
                'active_projects' => Project::where('activo', true)->count(),
                'total_asignaturas' => Asignatura::count(),
                'pending_deliverables' => $deliverableStatusCounts['pendiente'] ?? 0,
                'approved_deliverables' => $approvedDeliverables,
                'deliverable_completion_rate' => $this->percentage($approvedDeliverables, $totalDeliverables),
                'pending_proposals' => $projectProposalCounts['pendiente'] ?? 0,
            ],
            'charts' => [
                'users_by_role' => [
                    'Administradores' => User::where('perfil_id', 1)->count(),
                    'Docentes' => User::where('perfil_id', 2)->count(),
                    'Estudiantes' => User::where('perfil_id', 3)->count(),
                ],
                'projects_by_proposal_status' => $projectProposalCounts,
                'deliverables_by_status' => $deliverableStatusCounts,
            ],
            'recent_projects' => $recentProjects,
        ]);
    }

    public function teacher()
    {
        $userId = auth('api')->id();
        $advisorProjectIds = Project::where('activo', true)->whereHas('advisors', function ($query) use ($userId) {
            $query->where('usuarios.id', $userId);
        })->pluck('id');
        $responsibleGroupIds = TeacherGroupAssignment::where('teacher_id', $userId)
            ->where('activo', true)
            ->pluck('subject_group_id');

        $projectIds = Project::where('activo', true)
            ->where(function ($query) use ($advisorProjectIds, $responsibleGroupIds) {
                $query->whereIn('id', $advisorProjectIds)
                    ->orWhereIn('subject_group_id', $responsibleGroupIds);
            })
            ->pluck('id');

        $projects = Project::select(['id', 'title', 'created_by', 'created_at', 'subject_group_id', 'authors', 'semestre'])
            ->withCount([
                'deliverables',
                'deliverables as approved_deliverables_count' => fn ($query) => $query->where('estado', 'aprobado'),
            ])
            ->with([
                'creator:id,nombres,apa,ama',
                'advisors:id,nombres,apa,ama',
                'students:id,nombres,apa,ama',
                'subjectGroup:id,nombre,semestre,grupo',
            ])
            ->whereIn('id', $projectIds)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $deliverableStatusCounts = $this->statusCounts(
            Deliverable::query()->whereIn('project_id', $projectIds)->where('activo', true),
            'estado',
            self::DELIVERABLE_STATUSES
        );
        $proposalStatusCounts = $this->statusCounts(
            Project::query()->whereIn('id', $projectIds)->where('activo', true)->where('is_proposal', true),
            'proposal_status',
            self::PROPOSAL_STATUSES
        );
        $totalDeliverables = array_sum($deliverableStatusCounts);
        $approvedDeliverables = $deliverableStatusCounts['aprobado'] ?? 0;

        return response()->json([
            'stats' => [
                'my_projects' => $projectIds->count(),
                'students' => User::students()
                    ->where('activo', true)
                    ->whereHas('projectsAsAdvisor', fn ($query) => $query->whereIn('proyectos.id', $projectIds)->where('proyectos_integrantes.rol', 'integrante'))
                    ->count(),
                'pending_deliverables' => $deliverableStatusCounts['pendiente'] ?? 0,
                'approved_deliverables' => $approvedDeliverables,
                'deliverable_completion_rate' => $this->percentage($approvedDeliverables, $totalDeliverables),
                'pending_proposals' => $proposalStatusCounts['pendiente'] ?? 0,
            ],
            'charts' => [
                'deliverables_by_status' => $deliverableStatusCounts,
                'projects_by_proposal_status' => $proposalStatusCounts,
            ],
            'projects' => $projects,
            'recent_projects' => $projects,
        ]);
    }

    public function student()
    {
        $userId = auth('api')->id();
        $projectIds = Project::where('activo', true)
            ->whereHas('students', function ($query) use ($userId) {
                $query->where('usuarios.id', $userId);
            })
            ->pluck('id');

        $projects = Project::select(['id', 'title', 'created_by', 'created_at', 'subject_group_id', 'authors', 'semestre'])
            ->withCount([
                'deliverables',
                'deliverables as approved_deliverables_count' => fn ($query) => $query->where('estado', 'aprobado'),
            ])
            ->with([
                'creator:id,nombres,apa,ama',
                'advisors:id,nombres,apa,ama',
                'students:id,nombres,apa,ama',
                'subjectGroup:id,nombre,semestre,grupo',
            ])
            ->whereIn('id', $projectIds)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $deliverableStatusCounts = $this->statusCounts(
            Deliverable::query()
                ->where('activo', true)
                ->where(function ($query) use ($userId, $projectIds) {
                    $query->where('submitted_by', $userId)
                        ->orWhereIn('project_id', $projectIds);
                }),
            'estado',
            self::DELIVERABLE_STATUSES
        );
        $proposalStatusCounts = $this->statusCounts(
            Project::query()->whereIn('id', $projectIds)->where('activo', true)->where('is_proposal', true),
            'proposal_status',
            self::PROPOSAL_STATUSES
        );
        $totalDeliverables = array_sum($deliverableStatusCounts);
        $approvedDeliverables = $deliverableStatusCounts['aprobado'] ?? 0;

        return response()->json([
            'stats' => [
                'my_projects' => $projectIds->count(),
                'completed_deliverables' => $approvedDeliverables,
                'pending_deliverables' => $deliverableStatusCounts['pendiente'] ?? 0,
                'deliverable_completion_rate' => $this->percentage($approvedDeliverables, $totalDeliverables),
                'pending_proposals' => $proposalStatusCounts['pendiente'] ?? 0,
            ],
            'charts' => [
                'deliverables_by_status' => $deliverableStatusCounts,
                'projects_by_proposal_status' => $proposalStatusCounts,
            ],
            'projects' => $projects,
            'recent_projects' => $projects,
        ]);
    }

    private function statusCounts(Builder $query, string $column, array $statuses): array
    {
        $counts = (clone $query)
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->pluck('total', $column)
            ->all();

        return collect($statuses)
            ->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    private function percentage(int $value, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round(($value / $total) * 100);
    }
}
