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
    public function show()
    {
        return response()->json($this->loadOptionalRelations(auth('api')->user()));
    }

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
            'semestre' => $nullableInput($request->input('semestre')),
            'grupo' => $nullableInput($request->input('grupo')),
            'direccion' => $request->filled('direccion') ? $this->normalizeAddress($request->input('direccion')) : null,
        ]);

        $validated = $request->validate([
            'telefonos' => 'nullable|string|max:200',
            'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
            'semestre' => ['nullable', Rule::prohibitedIf((int) $user->perfil_id !== 3), 'integer', Rule::in([5, 6, 7, 8, 9])],
            'grupo' => ['nullable', Rule::prohibitedIf((int) $user->perfil_id !== 3), 'string', 'max:20'],
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

    private function loadOptionalRelations(User $user): User
    {
        return Schema::hasTable('usuarios_telefonos')
            ? $user->loadMissing('phoneNumbers')
            : $user;
    }

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

    private function syncAcademicAssignment(User $user, array $assignment): void
    {
        if (!$assignment['requested'] || (int) $user->perfil_id !== 3) {
            return;
        }

        $targetGroup = null;
        if ($assignment['semester'] !== null && $assignment['group'] !== null) {
            $targetGroup = SubjectGroup::where('activo', true)
                ->where('semestre', $assignment['semester'])
                ->where('grupo', $assignment['group'])
                ->first();

            if (!$targetGroup) {
                throw ValidationException::withMessages([
                    'grupo' => ["No existe un grupo activo {$assignment['semester']} {$assignment['group']}."],
                ]);
            }
        }

        DB::table('grupo_estudiantes')
            ->where('estudiante_id', $user->id)
            ->where('activo', true)
            ->update(['activo' => false]);

        if ($targetGroup) {
            DB::table('grupo_estudiantes')->updateOrInsert(
                ['grupo_id' => $targetGroup->id, 'estudiante_id' => $user->id],
                ['inscrito_en' => now(), 'activo' => true]
            );
        }
    }

    private function primaryPhone(?string $phones): ?string
    {
        return collect(preg_split('/[,;]+/', (string) $phones))
            ->map(fn ($phone) => trim($phone))
            ->first(fn ($phone) => $phone !== '');
    }

    private function normalizeAddress(?string $address): ?string
    {
        if (!$address) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($address));
    }
}
