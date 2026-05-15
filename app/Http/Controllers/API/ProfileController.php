<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show()
    {
        return response()->json(auth('api')->user());
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
            'semestre' => 'required|integer|in:5,6,7,8',
            'grupo' => 'required|string|max:20',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('profiles', 'public');
        }

        $validated['profile_completed_at'] = now();
        $user->update($validated);

        return response()->json(['message' => 'Perfil inicial completado', 'user' => $user->fresh()]);
    }

    public function update(Request $request)
    {
        $user = auth('api')->user();
        $request->merge([
            'semestre' => $request->input('semestre') === '' ? null : $request->input('semestre'),
            'grupo' => $request->input('grupo') === '' ? null : $request->input('grupo'),
            'direccion' => $request->filled('direccion') ? $this->normalizeAddress($request->input('direccion')) : null,
        ]);

        $validated = $request->validate([
            'telefonos' => 'nullable|string|max:200',
            'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
            'semestre' => [(int) $user->perfil_id === 3 ? 'nullable' : 'prohibited', 'integer', Rule::in([5, 6, 7, 8])],
            'grupo' => [(int) $user->perfil_id === 3 ? 'nullable' : 'prohibited', 'string', 'max:20'],
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

        $user->update($validated);

        return response()->json(['message' => 'Perfil actualizado', 'user' => $user->fresh()]);
    }

    private function normalizeAddress(?string $address): ?string
    {
        if (!$address) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($address));
    }
}
