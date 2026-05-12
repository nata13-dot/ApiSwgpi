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
        $recentProjects = Project::with('creator')
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
        $advisorProjectIds = Project::whereHas('advisors', function ($query) use ($userId) {
            $query->where('users.id', $userId);
        })->pluck('id');
        $responsibleGroupIds = TeacherGroupAssignment::where('teacher_id', $userId)->where('activo', true)->pluck('subject_group_id');

        $projects = Project::with(['creator', 'advisors', 'asignaturas', 'subjectGroup'])
            ->where(function ($query) use ($advisorProjectIds, $responsibleGroupIds) {
                $query->whereIn('id', $advisorProjectIds)
                    ->orWhereIn('subject_group_id', $responsibleGroupIds);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'my_projects' => $projects->count(),
                'students' => User::students()->count(),
                'pending_deliverables' => Deliverable::whereIn('project_id', $projects->pluck('id'))
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

        $projects = Project::with(['creator', 'advisors', 'asignaturas', 'subjectGroup'])
            ->whereHas('students', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'my_projects' => $projects->count(),
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