# ContinuityPolicyController

Fuente: [app/Http/Controllers/API/ContinuityPolicyController.php](../../../app/Http/Controllers/API/ContinuityPolicyController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/admin/continuity-policy` | [show](#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |
| PUT | `/api/admin/continuity-policy` | [update](#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:general_admin |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ContinuityPolicyService;
use Illuminate\Http\Request;

class ContinuityPolicyController extends Controller
{

}
```

## show

Visibilidad: `public`. Líneas originales 11–14.

```php
    public function show(ContinuityPolicyService $policies)
    {
        return response()->json($policies->get());
    }
```

## update

Visibilidad: `public`. Líneas originales 16–29.

```php
    public function update(Request $request, ContinuityPolicyService $policies)
    {
        $validated = $request->validate([
            'target_readiness' => 'required|integer|min:50|max:100',
            'critical_readiness' => 'required|integer|min:1|lt:target_readiness',
            'max_backup_age_hours' => 'required|integer|min:1|max:168',
            'backup_retention_days' => 'required|integer|min:7|max:365',
        ]);

        return response()->json([
            'message' => 'Política de continuidad actualizada.',
            'policy' => $policies->update($validated, (string) $request->user('api')->id),
        ]);
    }
```
