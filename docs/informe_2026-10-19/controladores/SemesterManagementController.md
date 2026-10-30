# SemesterManagementController

Fuente: [app/Http/Controllers/API/SemesterManagementController.php](../../../app/Http/Controllers/API/SemesterManagementController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| POST | `/api/academic-operations/authorizations` | [storeException](#storeexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:teacher |
| GET\|HEAD | `/api/semester-management` | [summary](#summary) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| POST | `/api/semester-management/exceptions` | [storeException](#storeexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| DELETE | `/api/semester-management/exceptions/{exception}` | [destroyException](#destroyexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| PUT | `/api/semester-management/exceptions/{exception}/decision` | [decideException](#decideexception) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| POST | `/api/semester-management/periods` | [storePeriod](#storeperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| PUT | `/api/semester-management/periods/{period}` | [updatePeriod](#updateperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| POST | `/api/semester-management/periods/{period}/activate` | [activatePeriod](#activateperiod) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| POST | `/api/semester-management/periods/{period}/promote` | [applyPromotion](#applypromotion) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| GET\|HEAD | `/api/semester-management/periods/{period}/promotion-preview` | [promotionPreview](#promotionpreview) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| POST | `/api/semester-management/periods/{period}/transition` | [transition](#transition) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:academic_manager |
| GET\|HEAD | `/api/semester-management/search` | [search](#search) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Project;
use App\Models\SemesterPresentationException;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Services\SemesterManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SemesterManagementController extends Controller
{












}
```

## __construct

Visibilidad: `public`. Líneas originales 19–21.

```php
    public function __construct(private readonly SemesterManagementService $semesterService)
    {
    }
```

## summary

Visibilidad: `public`. Líneas originales 23–52.

```php
    public function summary()
    {
        $activePeriod = $this->semesterService->activePeriod();
        $periods = AcademicPeriod::query()
            ->withCount('subjectGroups')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();

        $exceptions = DB::table('autorizaciones_academicas')
            ->where('carrera_id', app(\App\Services\AcademicWorkflowService::class)->careerId())
            ->orderByDesc('id')->get();
        $states = DB::table('carrera_periodos')->where('carrera_id', app(\App\Services\AcademicWorkflowService::class)->careerId())->get()->keyBy('periodo_id');
        $periods->each(function ($period) use ($states) {
            $period->operacion = $states->get($period->id);
        });

        return response()->json([
            'scheduler' => DB::table('ejecuciones_academicas')->orderByDesc('id')->first(),
            'active_period_id' => $activePeriod?->id,
            'periods' => $periods,
            'exceptions' => $exceptions,
            'stats' => [
                'students' => User::students()->where('activo', true)->count(),
                'projects' => Project::where('activo', true)->count(),
                'groups' => SubjectGroup::where('activo', true)->whereBetween('semestre', [5, 9])->count(),
                'exceptions' => $exceptions->count(),
            ],
        ]);
    }
```

## storePeriod

Visibilidad: `public`. Líneas originales 54–61.

```php
    public function storePeriod(Request $request)
    {
        abort_unless(auth('api')->user()->isGeneralAdmin(), 403, 'El calendario es institucional.');
        $validated = $this->validatePeriod($request);
        $period = AcademicPeriod::create($validated + ['activo' => false]);

        return response()->json(['message' => 'Periodo creado', 'period' => $period], 201);
    }
```

## updatePeriod

Visibilidad: `public`. Líneas originales 63–70.

```php
    public function updatePeriod(Request $request, AcademicPeriod $period)
    {
        abort_unless(auth('api')->user()->isGeneralAdmin(), 403, 'El calendario es institucional.');
        $validated = $this->validatePeriod($request, $period->id);
        $period->update($validated);

        return response()->json(['message' => 'Periodo actualizado', 'period' => $period->fresh()]);
    }
```

## activatePeriod

Visibilidad: `public`. Líneas originales 72–77.

```php
    public function activatePeriod(AcademicPeriod $period)
    {
        $period = $this->semesterService->activate($period, true);

        return response()->json(['message' => 'Periodo activado', 'period' => $period]);
    }
```

## promotionPreview

Visibilidad: `public`. Líneas originales 79–82.

```php
    public function promotionPreview(AcademicPeriod $period)
    {
        return response()->json(app(\App\Services\AcademicPromotionService::class)->preview($period));
    }
```

## applyPromotion

Visibilidad: `public`. Líneas originales 84–93.

```php
    public function applyPromotion(Request $request, AcademicPeriod $period)
    {
        $data = $request->validate([
            'operation_id' => 'required|uuid', 'snapshot' => 'required|string|size:64',
            'movements' => 'present|array', 'movements.*.student_id' => 'required|string|distinct',
            'movements.*.action' => 'required|in:avance,permanencia,salida',
            'movements.*.group_id' => 'nullable|integer', 'movements.*.reason' => 'required|string|max:500',
        ]);
        return response()->json(['message' => 'Lote aplicado', 'summary' => app(\App\Services\AcademicPromotionService::class)->confirm($period, $data)]);
    }
```

## transition

Visibilidad: `public`. Líneas originales 95–100.

```php
    public function transition(Request $request, AcademicPeriod $period)
    {
        $data = $request->validate(['state' => 'required|in:abierto,en_cierre,cerrado']);
        app(\App\Services\AcademicPeriodLifecycleService::class)->transition($period, $data['state']);
        return response()->json(['message' => 'Estado actualizado']);
    }
```

## search

Visibilidad: `public`. Líneas originales 102–129.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `q`.

```php
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['students' => [], 'projects' => []]);
        }

        $students = User::query()
            ->students()
            ->where('activo', true)
            ->where(function ($query) use ($term) {
                $query->where('id', 'like', "%{$term}%")
                    ->orWhere('nombres', 'like', "%{$term}%")
                    ->orWhere('apellido_paterno', 'like', "%{$term}%")
                    ->orWhere('apellido_materno', 'like', "%{$term}%");
            })
            ->limit(12)
            ->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'semestre', 'grupo']);

        $projects = Project::query()
            ->with('subjectGroup:id,semestre,grupo')
            ->where('activo', true)
            ->where('titulo', 'like', "%{$term}%")
            ->limit(12)
            ->get(['id', 'titulo', 'grupo_id']);

        return response()->json(['students' => $students, 'projects' => $projects]);
    }
```

## storeException

Visibilidad: `public`. Líneas originales 131–144.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `type`.

```php
    public function storeException(Request $request)
    {
        $request->merge(['type' => $request->input('type', 'presentacion_semestre')]);
        $data = $request->validate([
            'period_id' => 'required|integer|exists:periodos_academicos,id',
            'project_id' => 'nullable|integer', 'student_id' => 'nullable|string',
            'course_id' => 'nullable|integer', 'evaluation_id' => 'nullable|integer',
            'type' => 'required|in:presentacion_semestre,reprogramacion,reintento,inscripcion_semestre',
            'presentation_semester' => 'nullable|required_if:type,presentacion_semestre|integer|in:5,6,7,8',
            'reason' => 'required|string|max:1000', 'expires_at' => 'required|date|after:now',
        ]);
        return response()->json(['message' => 'Solicitud registrada; requiere resolución',
            'exception' => app(\App\Services\AcademicAuthorizationService::class)->request($data)], 201);
    }
```

## decideException

Visibilidad: `public`. Líneas originales 146–152.

```php
    public function decideException(Request $request, int $exception)
    {
        $data = $request->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:aprobada,rechazada,revocada',
            'reason' => 'required|string|max:1000', 'project_scope' => 'sometimes|boolean']);
        return response()->json(['message' => 'Resolución registrada',
            'exception' => app(\App\Services\AcademicAuthorizationService::class)->decide($exception, $data)]);
    }
```

## destroyException

Visibilidad: `public`. Líneas originales 154–158.

```php
    public function destroyException(Request $request, int $exception)
    {
        $request->merge(['status' => 'revocada']);
        return $this->decideException($request, $exception);
    }
```

## validatePeriod

Visibilidad: `private`. Líneas originales 160–188.

```php
    private function validatePeriod(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('periodos_academicos', 'nombre')->ignore($ignoreId),
            ],
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'automatic_promotion' => 'required|boolean',
        ]);

        $overlap = AcademicPeriod::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->whereDate('fecha_inicio', '<=', $validated['ends_at'])
            ->whereDate('fecha_fin', '>=', $validated['starts_at'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => ['Las fechas se cruzan con otro periodo.']]);
        }

        return [
            'nombre' => trim($validated['name']),
            'fecha_inicio' => $validated['starts_at'],
            'fecha_fin' => $validated['ends_at'],
        ];
    }
```
