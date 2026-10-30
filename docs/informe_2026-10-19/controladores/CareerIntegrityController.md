# CareerIntegrityController

Fuente: [app/Http/Controllers/API/CareerIntegrityController.php](../../../app/Http/Controllers/API/CareerIntegrityController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/admin/integrity` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| GET\|HEAD | `/api/admin/integrity/history` | [history](#history) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| POST | `/api/admin/integrity/run` | [run](#run) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MulticareerIntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareerIntegrityController extends Controller
{


}
```

## index

Visibilidad: `public`. Líneas originales 12–15.

```php
    public function index(MulticareerIntegrityService $integrity)
    {
        return response()->json($integrity->report());
    }
```

## run

Visibilidad: `public`. Líneas originales 17–23.

```php
    public function run(Request $request, MulticareerIntegrityService $integrity)
    {
        $report = $integrity->report();
        $report['run_id'] = $integrity->store($report, 'manual', $request->user('api')?->id);

        return response()->json($report);
    }
```

## history

Visibilidad: `public`. Líneas originales 25–40.

```php
    public function history()
    {
        return response()->json([
            'data' => DB::table('ejecuciones_integridad as run')
                ->leftJoin('usuarios as actor', 'actor.id', '=', 'run.ejecutado_por')
                ->select([
                    'run.id', 'run.origen', 'run.saludable', 'run.incidencias',
                    'run.verificaciones_correctas', 'run.verificaciones_totales',
                    'run.creado_en', 'run.ejecutado_por',
                    'actor.nombres as actor_nombres', 'actor.apellido_paterno as actor_apellido',
                ])
                ->orderByDesc('run.id')
                ->limit(30)
                ->get(),
        ]);
    }
```
