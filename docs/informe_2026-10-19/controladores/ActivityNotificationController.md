# ActivityNotificationController

Fuente: [app/Http/Controllers/API/ActivityNotificationController.php](../../../app/Http/Controllers/API/ActivityNotificationController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/activity-notifications` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| PUT | `/api/activity-notifications/read-all` | [markAllRead](#markallread) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| PUT | `/api/activity-notifications/{notification}/read` | [markRead](#markread) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ActivityNotificationController extends Controller
{



}
```

## index

Visibilidad: `public`. Líneas originales 12–27.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `limit`.

```php
    public function index(Request $request)
    {
        if (!Schema::hasTable('notificaciones_actividad')) {
            return response()->json(['data' => [], 'unread_count' => 0]);
        }

        $userId = (string) auth('api')->id();
        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $query = ActivityNotification::with('actor:id,nombres,apellido_paterno,apellido_materno')
            ->where('usuario_id', $userId);

        return response()->json([
            'data' => (clone $query)->latest('creada_en')->limit($limit)->get(),
            'unread_count' => (clone $query)->whereNull('leida_en')->count(),
        ]);
    }
```

## markRead

Visibilidad: `public`. Líneas originales 29–37.

```php
    public function markRead(ActivityNotification $notification)
    {
        $this->guardOwner($notification);
        if ($notification->leida_en === null) {
            $notification->update(['leida_en' => now()]);
        }

        return response()->json(['message' => 'Notificacion leida']);
    }
```

## markAllRead

Visibilidad: `public`. Líneas originales 39–50.

```php
    public function markAllRead()
    {
        if (!Schema::hasTable('notificaciones_actividad')) {
            return response()->json(['message' => 'Notificaciones leidas']);
        }

        ActivityNotification::where('usuario_id', auth('api')->id())
            ->whereNull('leida_en')
            ->update(['leida_en' => now()]);

        return response()->json(['message' => 'Notificaciones leidas']);
    }
```

## guardOwner

Visibilidad: `private`. Líneas originales 52–55.

```php
    private function guardOwner(ActivityNotification $notification): void
    {
        abort_unless((string) $notification->usuario_id === (string) auth('api')->id(), 403);
    }
```
