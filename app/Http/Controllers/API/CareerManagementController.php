<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\UserCareer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareerManagementController extends Controller
{
    public function index()
    {
        $memberships = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true));
        $administrators = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true)->where('perfil_id', 1));
        $careerHeads = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true)->where('perfil_id', 5));
        $careerHeadAssistants = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true)->where('perfil_id', 6));
        $projectCoordinators = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true)->where('perfil_id', 7));
        $teachers = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true)->where('perfil_id', 2));
        $students = $this->countsByCareer('usuario_carrera', fn ($query) => $query->where('activo', true)->where('perfil_id', 3));
        $projects = $this->countsByCareer('proyectos', fn ($query) => $query->where('activo', true));
        $subjects = $this->countsByCareer('asignaturas', fn ($query) => $query->where('activo', true));
        $groups = $this->countsByCareer('grupos_academicos', fn ($query) => $query->where('activo', true));
        $documents = $this->countsByCareer('documentos');
        $evaluations = $this->countsByCareer('evaluaciones');
        $rubrics = $this->countsByCareer('rubricas', fn ($query) => $query->where('activa', true));
        $settings = $this->countsByCareer('configuraciones_carrera');

        return response()->json([
            'careers' => Career::query()->orderBy('id')->get()->map(function (Career $career) use (
                $memberships, $administrators, $careerHeads, $careerHeadAssistants, $projectCoordinators,
                $teachers, $students, $projects,
                $subjects, $groups, $documents, $evaluations, $rubrics, $settings
            ) {
                $careerId = $career->id;
                $modules = DB::table('carrera_modulos')
                    ->where('carrera_id', $careerId)
                    ->orderBy('modulo')
                    ->get(['modulo', 'habilitado', 'configuracion']);
                $checklist = [
                    'identity' => filled($career->nombre) && filled($career->nombre_corto) && filled($career->color_primario),
                    // Administrador y jefe de carrera son cargos independientes y opcionales.
                    // La carrera está cubierta si existe al menos uno de los dos.
                    'career_management' => ((int) ($administrators[$careerId] ?? 0) + (int) ($careerHeads[$careerId] ?? 0)) > 0,
                    'subjects' => (int) ($subjects[$careerId] ?? 0) > 0,
                    'groups' => (int) ($groups[$careerId] ?? 0) > 0,
                    'students' => (int) ($students[$careerId] ?? 0) > 0,
                    'configuration' => (int) ($settings[$careerId] ?? 0) >= 5,
                    'rubrics' => (int) ($rubrics[$careerId] ?? 0) >= 4,
                ];
                $completed = collect($checklist)->filter()->count();
                $readiness = (int) round(($completed / count($checklist)) * 100);

                return array_merge($career->toArray(), [
                    'members_count' => (int) ($memberships[$careerId] ?? 0),
                    'projects_count' => (int) ($projects[$careerId] ?? 0),
                    'subjects_count' => (int) ($subjects[$careerId] ?? 0),
                    'groups_count' => (int) ($groups[$careerId] ?? 0),
                    'documents_count' => (int) ($documents[$careerId] ?? 0),
                    'evaluations_count' => (int) ($evaluations[$careerId] ?? 0),
                    'rubrics_count' => (int) ($rubrics[$careerId] ?? 0),
                    'role_counts' => [
                        'administrators' => (int) ($administrators[$careerId] ?? 0),
                        'career_heads' => (int) ($careerHeads[$careerId] ?? 0),
                        'career_head_assistants' => (int) ($careerHeadAssistants[$careerId] ?? 0),
                        'project_coordinators' => (int) ($projectCoordinators[$careerId] ?? 0),
                        'teachers' => (int) ($teachers[$careerId] ?? 0),
                        'students' => (int) ($students[$careerId] ?? 0),
                    ],
                    'readiness' => [
                        'percentage' => $readiness,
                        'status' => $readiness === 100 ? 'ready' : ($completed > 2 ? 'in_progress' : 'pending'),
                        'completed' => $completed,
                        'total' => count($checklist),
                        'checklist' => $checklist,
                    ],
                    'modules' => $modules,
                ]);
            }),
        ]);
    }

    private function countsByCareer(string $table, ?callable $filter = null)
    {
        $query = DB::table($table);
        if ($filter) {
            $filter($query);
        }

        return $query
            ->selectRaw('carrera_id, COUNT(*) total')
            ->groupBy('carrera_id')
            ->pluck('total', 'carrera_id');
    }

    public function update(Request $request, Career $career)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:180',
            'nombre_corto' => 'sometimes|required|string|max:100',
            'color_primario' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secundario' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_acento' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'lema' => 'nullable|string|max:255',
            'logo_ruta' => 'nullable|string|max:255',
            'portada_ruta' => 'nullable|string|max:255',
            'activa' => 'sometimes|boolean',
            'modules' => 'sometimes|array',
            'modules.*.modulo' => 'required|string|max:80',
            'modules.*.habilitado' => 'required|boolean',
        ]);

        DB::transaction(function () use ($career, $validated): void {
            $modules = $validated['modules'] ?? null;
            unset($validated['modules']);
            $career->update($validated);

            foreach ($modules ?? [] as $module) {
                DB::table('carrera_modulos')->updateOrInsert(
                    ['carrera_id' => $career->id, 'modulo' => $module['modulo']],
                    [
                        'habilitado' => $module['habilitado'],
                        'actualizado_en' => now(),
                    ]
                );
            }
        });

        return response()->json(['career' => $career->fresh()]);
    }

    public function memberships(Request $request)
    {
        $validated = $request->validate([
            'carrera_id' => 'required|integer|exists:carreras,id',
            'search' => 'nullable|string|max:100',
        ]);

        $query = UserCareer::query()
            ->with([
                'user' => fn ($userQuery) => $userQuery
                    ->withoutGlobalScope('careerMembership')
                    ->select(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'perfil_id', 'activo']),
                'career',
            ])
            ->where('carrera_id', $validated['carrera_id']);

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->withoutGlobalScope('careerMembership')
                    ->where(function ($searchQuery) use ($search): void {
                        $searchQuery->where('id', 'like', "%{$search}%")
                    ->orWhere('nombres', 'like', "%{$search}%")
                    ->orWhere('apellido_paterno', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json($query->orderByDesc('activo')->orderBy('perfil_id')->paginate(25));
    }

    public function storeMembership(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|string|exists:usuarios,id',
            'carrera_id' => 'required|integer|exists:carreras,id',
            'perfil_id' => 'required|integer|in:1,2,3,5,6,7',
            'es_principal' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        $this->guardStudentMembership($validated);
        $validated['asignado_por'] = auth('api')->id();

        $membership = DB::transaction(function () use ($validated) {
            if (!empty($validated['es_principal'])) {
                UserCareer::where('usuario_id', $validated['usuario_id'])->update(['es_principal' => false]);
            }

            return UserCareer::updateOrCreate(
                [
                    'usuario_id' => $validated['usuario_id'],
                    'carrera_id' => $validated['carrera_id'],
                ],
                $validated
            );
        });

        return response()->json([
            'membership' => $membership->load([
                'user' => fn ($query) => $query->withoutGlobalScope('careerMembership'),
                'career',
            ]),
        ], 201);
    }

    public function updateMembership(Request $request, UserCareer $membership)
    {
        $validated = $request->validate([
            'perfil_id' => 'sometimes|required|integer|in:1,2,3,5,6,7',
            'es_principal' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);
        $candidate = array_merge($membership->only(['usuario_id', 'carrera_id', 'perfil_id', 'activo']), $validated);
        $this->guardStudentMembership($candidate, $membership->id);

        DB::transaction(function () use ($membership, $validated): void {
            if (!empty($validated['es_principal'])) {
                UserCareer::where('usuario_id', $membership->usuario_id)
                    ->whereKeyNot($membership->id)
                    ->update(['es_principal' => false]);
            }
            $membership->update($validated);
        });

        return response()->json([
            'membership' => $membership->fresh()->load([
                'user' => fn ($query) => $query->withoutGlobalScope('careerMembership'),
                'career',
            ]),
        ]);
    }

    public function destroyMembership(UserCareer $membership)
    {
        $membership->delete();

        return response()->json(['message' => 'Membresía eliminada correctamente.']);
    }

    private function guardStudentMembership(array $data, ?int $exceptId = null): void
    {
        if ((int) ($data['perfil_id'] ?? 0) !== 3 || !($data['activo'] ?? true)) {
            return;
        }

        $hasAnother = UserCareer::query()
            ->where('usuario_id', $data['usuario_id'])
            ->where('activo', true)
            ->where('perfil_id', 3)
            ->where('carrera_id', '<>', $data['carrera_id'])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        abort_if($hasAnother, 422, 'Un estudiante sólo puede tener una carrera activa.');
    }
}
