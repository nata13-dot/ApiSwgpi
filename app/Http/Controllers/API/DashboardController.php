<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\TeacherGroupAssignment;
use App\Models\User;

class DashboardController extends Controller
{
    public function stats()
    {
        $recentProjects = Project::select(['id', 'title', 'created_by', 'created_at'])
            ->with('creator:id,nombres,apa,ama')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_users' => User::count(),
                'active_users' => User::where('activo', true)->count(),
                'total_projects' => Project::count(),
                'total_asignaturas' => Asignatura::count(),
            ],
            'recent_projects' => $recentProjects,
        ]);
    }

    public function teacher()
    {
        $userId = auth('api')->id();
        $advisorProjectIds = Project::where('activo', true)->whereHas('advisors', function ($query) use ($userId) {
            $query->where('users.id', $userId);
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

        return response()->json([
            'stats' => [
                'my_projects' => $projectIds->count(),
                'students' => User::students()
                    ->where('activo', true)
                    ->whereHas('projectsAsAdvisor', fn ($query) => $query->whereIn('projects.id', $projectIds)->whereNull('project_user.rol_asesor'))
                    ->count(),
                'pending_deliverables' => Deliverable::whereIn('project_id', $projectIds)
                    ->where('estado', 'pendiente')
                    ->count(),
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
                $query->where('users.id', $userId);
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

        return response()->json([
            'stats' => [
                'my_projects' => $projectIds->count(),
                'completed_deliverables' => Deliverable::where('submitted_by', $userId)
                    ->where('estado', 'aprobado')
                    ->count(),
                'pending_deliverables' => Deliverable::where('submitted_by', $userId)
                    ->where('estado', 'pendiente')
                    ->count(),
            ],
            'projects' => $projects,
            'recent_projects' => $projects,
        ]);
    }
}
