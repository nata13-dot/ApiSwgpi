<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const PROPOSAL_STATUSES = ['borrador', 'pendiente', 'en_revision', 'requiere_cambios', 'aprobado', 'rechazado', 'finalizado', 'archivado'];
    private const DELIVERABLE_STATUSES = ['borrador', 'publicado', 'cerrado'];

    public function stats()
    {
        $loadPayload = function () {
            $recentProjects = DB::table('proyectos')
                ->leftJoin('usuarios as creador', 'creador.id', '=', 'proyectos.creado_por')
                ->select([
                    'proyectos.id',
                    'proyectos.titulo',
                    'proyectos.creado_por',
                    'proyectos.creado_en',
                    'creador.nombres as creador_nombres',
                    'creador.apellido_paterno as creador_apa',
                    'creador.apellido_materno as creador_ama',
                ])
                ->where('proyectos.activo', true)
                ->orderByDesc('proyectos.creado_en')
                ->limit(5)
                ->get()
                ->map(fn ($project) => [
                    'id' => $project->id,
                    'title' => $project->titulo,
                    'created_at' => $project->creado_en,
                    'creator' => $project->creado_por ? [
                        'id' => $project->creado_por,
                        'nombres' => $project->creador_nombres,
                        'apa' => $project->creador_apa,
                        'ama' => $project->creador_ama,
                    ] : null,
                ]);

            $deliverableStatusCounts = $this->statusCounts(
                DB::table('entregables')->where('activo', true),
                'estado',
                self::DELIVERABLE_STATUSES
            );
            $projectProposalCounts = $this->statusCounts(
                DB::table('proyectos')->where('activo', true)->where('tipo', 'propuesta'),
                'estado',
                self::PROPOSAL_STATUSES
            );
            $userCounts = DB::table('usuarios')->selectRaw(
                'COUNT(*) AS total_users,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS active_users,
                SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) AS inactive_users,
                SUM(CASE WHEN perfil_id = 1 THEN 1 ELSE 0 END) AS administrators,
                SUM(CASE WHEN perfil_id = 2 THEN 1 ELSE 0 END) AS teachers,
                SUM(CASE WHEN perfil_id = 3 THEN 1 ELSE 0 END) AS students'
            )->first();
            $projectCounts = DB::table('proyectos')->selectRaw(
                'COUNT(*) AS total_projects,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS active_projects'
            )->first();
            $totalDeliverables = array_sum($deliverableStatusCounts);
            $approvedDeliverables = $deliverableStatusCounts['cerrado'] ?? 0;

            return [
                'stats' => [
                    'total_users' => (int) $userCounts->total_users,
                    'active_users' => (int) $userCounts->active_users,
                    'inactive_users' => (int) $userCounts->inactive_users,
                    'total_projects' => (int) $projectCounts->total_projects,
                    'active_projects' => (int) $projectCounts->active_projects,
                    'total_asignaturas' => DB::table('asignaturas')->count(),
                    'pending_deliverables' => $deliverableStatusCounts['publicado'] ?? 0,
                    'approved_deliverables' => $approvedDeliverables,
                    'deliverable_completion_rate' => $this->percentage($approvedDeliverables, $totalDeliverables),
                    'pending_proposals' => $projectProposalCounts['pendiente'] ?? 0,
                ],
                'charts' => [
                    'users_by_role' => [
                        'Administradores' => (int) $userCounts->administrators,
                        'Docentes' => (int) $userCounts->teachers,
                        'Estudiantes' => (int) $userCounts->students,
                    ],
                    'projects_by_proposal_status' => $projectProposalCounts,
                    'deliverables_by_status' => $deliverableStatusCounts,
                ],
                'recent_projects' => $recentProjects,
            ];
        };

        try {
            $payload = Cache::store(config('auth.activity_cache_store', 'file'))
                ->remember('dashboard:admin:stats:v3', now()->addSeconds(20), $loadPayload);
        } catch (\Throwable) {
            $payload = $loadPayload();
        }

        return response()->json($payload);
    }

    public function teacher()
    {
        $userId = auth('api')->id();
        $advisorProjectIds = Project::where('activo', true)->whereHas('advisors', function ($query) use ($userId) {
            $query->where('usuarios.id', $userId);
        })->pluck('id');
        $responsibleGroupIds = DB::table('curso_docentes')
            ->join('cursos', 'cursos.id', '=', 'curso_docentes.curso_id')
            ->where('curso_docentes.docente_id', $userId)
            ->where('curso_docentes.activo', true)
            ->pluck('cursos.grupo_id');

        $projectIds = Project::where('activo', true)
            ->where(function ($query) use ($advisorProjectIds, $responsibleGroupIds) {
                $query->whereIn('id', $advisorProjectIds)
                    ->orWhereIn('subject_group_id', $responsibleGroupIds);
            })
            ->pluck('id');

        $projects = Project::select(['id', 'title', 'created_by', 'created_at', 'subject_group_id', 'authors', 'semestre'])
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

        $projectGroupIds = Project::whereIn('id', $projectIds)->pluck('grupo_id')->filter();
        $deliverableStatusCounts = $this->statusCounts(
            DB::table('entregables')
                ->join('cursos', 'cursos.id', '=', 'entregables.curso_id')
                ->whereIn('cursos.grupo_id', $projectGroupIds)
                ->where('entregables.activo', true),
            'estado',
            self::DELIVERABLE_STATUSES
        );
        $proposalStatusCounts = $this->statusCounts(
            Project::query()->whereIn('id', $projectIds)->where('activo', true)->where('tipo', 'propuesta'),
            'estado',
            self::PROPOSAL_STATUSES
        );
        $totalDeliverables = array_sum($deliverableStatusCounts);
        $approvedDeliverables = $deliverableStatusCounts['cerrado'] ?? 0;

        return response()->json([
            'stats' => [
                'my_projects' => $projectIds->count(),
                'students' => User::students()
                    ->where('activo', true)
                    ->whereHas('projectsAsAdvisor', fn ($query) => $query->whereIn('proyectos.id', $projectIds)->where('proyecto_integrantes.rol', 'integrante'))
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

        $projectGroupIds = Project::whereIn('id', $projectIds)->pluck('grupo_id')->filter();
        $deliverableStatusCounts = $this->statusCounts(
            DB::table('entregables')
                ->join('cursos', 'cursos.id', '=', 'entregables.curso_id')
                ->whereIn('cursos.grupo_id', $projectGroupIds)
                ->where('entregables.activo', true),
            'estado',
            self::DELIVERABLE_STATUSES
        );
        $proposalStatusCounts = $this->statusCounts(
            Project::query()->whereIn('id', $projectIds)->where('activo', true)->where('tipo', 'propuesta'),
            'estado',
            self::PROPOSAL_STATUSES
        );
        $totalDeliverables = array_sum($deliverableStatusCounts);
        $approvedDeliverables = $deliverableStatusCounts['cerrado'] ?? 0;

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

    private function statusCounts($query, string $column, array $statuses): array
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
