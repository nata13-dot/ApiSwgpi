# ProfileController

Fuente: [app/Http/Controllers/API/ProfileController.php](../../../app/Http/Controllers/API/ProfileController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/profile` | [show](#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| POST | `/api/profile` | [update](#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| POST | `/api/profile/complete-initial` | [completeInitial](#completeinitial) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{







}
```

## show

Visibilidad: `public`. Líneas originales 18–21.

```php
    public function show()
    {
        return response()->json($this->loadOptionalRelations(auth('api')->user()));
    }
```

## completeInitial

Visibilidad: `public`. Líneas originales 23–55.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `photo`.

```php
    public function completeInitial(Request $request)
    {
        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 3) {
            return response()->json(['message' => 'Solo estudiantes completan este registro inicial.'], 403);
        }

        $validated = $request->validate([
            'nombres' => 'required|string|max:200',
            'apa' => 'required|string|max:100',
            'ama' => 'nullable|string|max:100',
            'semestre' => 'required|integer|in:5,6,7,8,9',
            'grupo' => 'required|string|max:20',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('profiles', 'public');
        }

        $academicAssignment = $this->pullAcademicAssignment($validated);
        $validated['profile_completed_at'] = now();

        DB::transaction(function () use ($user, $validated, $academicAssignment) {
            $user->update($validated);
            $this->syncAcademicAssignment($user, $academicAssignment);
        });

        return response()->json([
            'message' => 'Perfil inicial completado',
            'user' => $this->loadOptionalRelations($user->fresh()),
        ]);
    }
```

## update

Visibilidad: `public`. Líneas originales 57–119.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `current_password`, `direccion`, `photo`.

```php
    public function update(Request $request)
    {
        $user = auth('api')->user();
        $nullableInput = static function ($value) {
            if ($value === null) {
                return null;
            }

            $normalized = trim((string) $value);

            return $normalized === '' || in_array(strtolower($normalized), ['null', 'undefined'], true)
                ? null
                : $normalized;
        };

        $request->merge([
            'direccion' => $request->filled('direccion') ? $this->normalizeAddress($request->input('direccion')) : null,
        ]);

        $validated = $request->validate([
            'telefonos' => 'nullable|string|max:200',
            'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
            'semestre' => 'prohibited',
            'grupo' => 'prohibited',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'current_password' => 'nullable|string|max:72',
            'password' => 'nullable|string|min:6|max:72|confirmed',
        ]);

        if (!empty($validated['password'])) {
            if (!$request->filled('current_password') || !Hash::check($request->input('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['La contraseña actual no coincide.']]);
            }
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        unset($validated['current_password'], $validated['password_confirmation']);

        if ($request->hasFile('photo')) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('profiles', 'public');
        }

        $academicAssignment = $this->pullAcademicAssignment($validated);
        if (array_key_exists('telefonos', $validated)) {
            $validated['telefono'] = $this->primaryPhone($validated['telefonos']);
            unset($validated['telefonos']);
        }

        DB::transaction(function () use ($user, $validated, $academicAssignment) {
            $user->update($validated);
            $this->syncAcademicAssignment($user, $academicAssignment);
        });

        return response()->json([
            'message' => 'Perfil actualizado',
            'user' => $this->loadOptionalRelations($user->fresh()),
        ]);
    }
```

## loadOptionalRelations

Visibilidad: `private`. Líneas originales 121–126.

```php
    private function loadOptionalRelations(User $user): User
    {
        return Schema::hasTable('usuarios_telefonos')
            ? $user->loadMissing('phoneNumbers')
            : $user;
    }
```

## pullAcademicAssignment

Visibilidad: `private`. Líneas originales 128–141.

```php
    private function pullAcademicAssignment(array &$data): array
    {
        $requested = array_key_exists('semestre', $data) || array_key_exists('grupo', $data);
        $semester = isset($data['semestre']) && $data['semestre'] !== ''
            ? (int) $data['semestre']
            : null;
        $group = isset($data['grupo']) && trim((string) $data['grupo']) !== ''
            ? strtoupper(trim((string) $data['grupo']))
            : null;

        unset($data['semestre'], $data['grupo']);

        return compact('requested', 'semester', 'group');
    }
```

## syncAcademicAssignment

Visibilidad: `private`. Líneas originales 143–154.

```php
    private function syncAcademicAssignment(User $user, array $assignment): void
    {
        if (!$assignment['requested'] || (int) $user->perfil_id !== 3) {
            return;
        }

        $workflow = app(\App\Services\AcademicWorkflowService::class);
        abort_if($workflow->enrollment($user->id), 409, 'La matrícula ya existe; solicita el cambio a gestión académica.');
        $targetGroup = SubjectGroup::where('activo', true)->where('periodo_id', $workflow->period()?->id)
            ->where('semestre', $assignment['semester'])->where('grupo', $assignment['group'])->firstOrFail();
        $workflow->assign($user->id, $targetGroup);
    }
```

## primaryPhone

Visibilidad: `private`. Líneas originales 156–161.

```php
    private function primaryPhone(?string $phones): ?string
    {
        return collect(preg_split('/[,;]+/', (string) $phones))
            ->map(fn ($phone) => trim($phone))
            ->first(fn ($phone) => $phone !== '');
    }
```

## normalizeAddress

Visibilidad: `private`. Líneas originales 163–170.

```php
    private function normalizeAddress(?string $address): ?string
    {
        if (!$address) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($address));
    }
```
