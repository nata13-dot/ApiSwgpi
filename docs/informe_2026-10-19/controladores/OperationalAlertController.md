# OperationalAlertController

Fuente: [app/Http/Controllers/API/OperationalAlertController.php](../../../app/Http/Controllers/API/OperationalAlertController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/admin/operational-alerts` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| POST | `/api/admin/operational-alerts/scan` | [scan](#scan) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| GET\|HEAD | `/api/admin/operational-alerts/summary` | [summary](#summary) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| PUT | `/api/admin/operational-alerts/{alert}/acknowledge` | [acknowledge](#acknowledge) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\OperationalAlertService;
use Illuminate\Http\Request;

class OperationalAlertController extends Controller
{



}
```

## index

Visibilidad: `public`. Líneas originales 11–14.

```php
    public function index(OperationalAlertService $alerts)
    {
        return response()->json($alerts->summary());
    }
```

## scan

Visibilidad: `public`. Líneas originales 16–19.

```php
    public function scan(OperationalAlertService $alerts)
    {
        return response()->json($alerts->scan());
    }
```

## summary

Visibilidad: `public`. Líneas originales 21–24.

```php
    public function summary(OperationalAlertService $alerts)
    {
        return response()->json($alerts->activeSummary());
    }
```

## acknowledge

Visibilidad: `public`. Líneas originales 26–32.

```php
    public function acknowledge(Request $request, int $alert, OperationalAlertService $alerts)
    {
        return response()->json([
            'message' => 'Alerta marcada como atendida.',
            'alert' => $alerts->acknowledge($alert, (string) $request->user('api')->id),
        ]);
    }
```
