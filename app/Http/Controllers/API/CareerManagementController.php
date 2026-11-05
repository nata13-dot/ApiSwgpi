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
        // Un solo conteo agrupado por carrera y perfil en lugar de una consulta por perfil.
        $roles = DB::table('usuario_carrera')->where('activo', true)
            ->selectRaw('carrera_id, perfil_id, COUNT(*) total')->groupBy('carrera_id', 'perfil_id')->get();
        $byProfile = fn (int $profile) => $roles->where('perfil_id', $profile)->pluck('total', 'carrera_id');
        $memberships = $roles->groupBy('carrera_id')->map(fn ($rows) => $rows->sum('total'));
        $administrators = $byProfile(1);
        $careerHeads = $byProfile(5);
        $careerHeadAssistants = $byProfile(6);
        $projectCoordinators = $byProfile(7);
        $teachers = $byProfile(2);
        $students = $byProfile(3);
        $modulesByCareer = DB::table('carrera_modulos')->orderBy('modulo')
            ->get(['carrera_id', 'modulo', 'habilitado', 'configuracion'])->groupBy('carrera_id');
        $projects = $this->countsByCareer('proyectos', fn ($query) => $query->where('activo', true));
        $subjects = $this->countsByCareer('asignaturas', fn ($query) => $query->where('activo', true));
        $groups = $this->countsByCareer('grupos_academicos', fn ($query) => $query->where('activo', true));
        $documents = $this->countsByCareer('documentos');
        $evaluations = $this->countsByCareer('evaluaciones');
        $rubrics = $this->countsByCareer('rubricas', fn ($query) => $query->where('activa', true));
        $settings = $this->countsByCareer('configuraciones_carrera', fn ($query) => $query->whereIn('clave', \App\Models\CareerSetting::STORED_KEYS));
        // Periodo vigente de cada carrera (abierto o en cierre; si no, el más reciente en preparación).
        $periods = DB::table('carrera_periodos as cp')->join('periodos_academicos as p', 'p.id', '=', 'cp.periodo_id')
            ->whereIn('cp.estado', ['abierto', 'en_cierre', 'preparacion'])
            ->orderByRaw("FIELD(cp.estado, 'abierto', 'en_cierre', 'preparacion')")->orderByDesc('p.fecha_inicio')
            ->get(['cp.carrera_id', 'cp.estado', 'cp.conciliado', 'p.id', 'p.nombre'])->unique('carrera_id')->keyBy('carrera_id');

        return response()->json([
            'careers' => Career::query()->orderBy('id')->get()->map(function (Career $career) use (
                $memberships, $administrators, $careerHeads, $careerHeadAssistants, $projectCoordinators,
                $teachers, $students, $projects,
                $subjects, $groups, $documents, $evaluations, $rubrics, $settings, $modulesByCareer, $periods
            ) {
                $careerId = $career->id;
                $modules = collect($modulesByCareer[$careerId] ?? [])
                    ->map(fn ($module) => (object) ['modulo' => $module->modulo, 'habilitado' => $module->habilitado, 'configuracion' => $module->configuracion])
                    ->values();
                $checklist = [
                    'identity' => filled($career->nombre) && filled($career->nombre_corto) && filled($career->color_primario),
                    // Administrador y jefe de carrera son cargos independientes y opcionales.
                    // La carrera está cubierta si existe al menos uno de los dos.
                    'career_management' => ((int) ($administrators[$careerId] ?? 0) + (int) ($careerHeads[$careerId] ?? 0)) > 0,
                    'subjects' => (int) ($subjects[$careerId] ?? 0) > 0,
                    'groups' => (int) ($groups[$careerId] ?? 0) > 0,
                    'students' => (int) ($students[$careerId] ?? 0) > 0,
                    'configuration' => (int) ($settings[$careerId] ?? 0) >= count(\App\Models\CareerSetting::STORED_KEYS),
                    'rubrics' => (int) ($rubrics[$careerId] ?? 0) >= 4,
                ];
                $completed = collect($checklist)->filter()->count();
                $readiness = (int) round(($completed / count($checklist)) * 100);

                return array_merge($career->toArray(), [
                    'basic_semesters_enabled' => (bool) \App\Models\CareerSetting::valueForCareer($careerId, 'basic_semesters_enabled', false),
                    'period' => $periods->get($careerId),
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

        // Las tablas derivadas no guardan carrera: se agrupa por la de su padre.
        $career = \App\Support\CareerScope::expression($table, $table);

        return DB::query()->fromSub($query->selectRaw("{$career} AS carrera_id"), 'fila')
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
            // Carreras con casos integradores pueden registrar 1°–4°; las demás sólo 5°–9°.
            'basic_semesters_enabled' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($career, $validated): void {
            $modules = $validated['modules'] ?? null;
            if (array_key_exists('basic_semesters_enabled', $validated)) {
                \App\Models\CareerSetting::setValueForCareer($career->id, 'basic_semesters_enabled', (bool) $validated['basic_semesters_enabled'], 'boolean', 'Registro de semestres 1° a 4°');
            }
            unset($validated['modules'], $validated['basic_semesters_enabled']);
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
                    ->leftJoin('usuario_carrera as membresia', fn ($join) => $join
                        ->on('membresia.usuario_id', '=', 'usuarios.id')
                        ->where('membresia.carrera_id', $validated['carrera_id']))
                    ->select(['usuarios.id', 'usuarios.nombres', 'usuarios.apellido_paterno', 'usuarios.apellido_materno',
                        'usuarios.correo', 'usuarios.activo', 'membresia.perfil_id as career_profile_id']),
                'career',
            ])
            ->where('carrera_id', $validated['carrera_id']);

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $query->whereHas('user', function ($userQuery) use ($search): void {
                \App\Support\TextSearch::apply($userQuery->withoutGlobalScope('careerMembership'), $search, ['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo']);
            });
        }

        return response()->json($query->orderByDesc('activo')->orderBy('perfil_id')->paginate(25));
    }

    public function storeMembership(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|string|exists:usuarios,id',
            'carrera_id' => 'required|integer|exists:carreras,id',
            'perfil_id' => 'required|integer|'.\App\Support\Profiles::rule(),
            'es_principal' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        $this->guardStudentMembership($validated);
        $validated['asignado_por'] = auth('api')->id();

        $membership = DB::transaction(function () use ($validated) {
            if (!empty($validated['es_principal'])) {
                UserCareer::where('usuario_id', $validated['usuario_id'])->update(['es_principal' => false]);
            }

            $existing = UserCareer::where('usuario_id', $validated['usuario_id'])->where('carrera_id', $validated['carrera_id'])->first();
            if ($existing && ((int) $existing->perfil_id !== (int) $validated['perfil_id'] || !($validated['activo'] ?? true))) app(\App\Services\AcademicMembershipService::class)->ensureReleased($existing);
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
            'perfil_id' => 'sometimes|required|integer|'.\App\Support\Profiles::rule(),
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
            $membership->refresh();
            if ((isset($validated['perfil_id']) && (int) $validated['perfil_id'] !== (int) $membership->perfil_id) || (array_key_exists('activo', $validated) && !$validated['activo'])) {
                app(\App\Services\AcademicMembershipService::class)->ensureReleased($membership);
            }
            $before = $membership->toArray();
            $membership->update($validated);
            app(\App\Services\AcademicWorkflowService::class)->audit('membresia.actualizada', 'membresia', $membership->id, $before, $membership->toArray());
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
        app(\App\Services\AcademicMembershipService::class)->ensureReleased($membership);
        $before = $membership->toArray();
        $membership->update(['activo' => false]);
        app(\App\Services\AcademicWorkflowService::class)->audit('membresia.baja', 'membresia', $membership->id, $before, $membership->toArray());

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
