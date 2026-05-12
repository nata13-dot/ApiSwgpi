<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->query('status') === 'active') {
            $query->where('activo', true);
        }

        if ($request->query('status') === 'inactive') {
            $query->where('activo', false);
        }

        if ($request->filled('perfil_id')) {
            $query->where('perfil_id', $request->perfil_id);
        }

        if ($request->filled('semestre')) {
            $query->where('semestre', $request->semestre);
        }

        if ($request->filled('grupo')) {
            $query->where('grupo', strtoupper($request->grupo));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        return response()->json($query->orderByDesc('activo')->orderBy('perfil_id')->orderBy('nombres')->paginate($perPage));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:users,id'],
                'nombres' => 'required|string|max:200',
                'email' => 'nullable|email|unique:users',
                'password' => 'required|string|min:6|max:72',
                'perfil_id' => 'required|integer|in:1,2,3',
                'semestre' => 'nullable|integer|in:5,6,7,8',
                'grupo' => 'nullable|string|max:20',
                'apa' => 'nullable|string|max:100',
                'ama' => 'nullable|string|max:100',
                'curp' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'unique:users,curp'],
            ]);

            $validated['password'] = Hash::make($validated['password']);
            if (($validated['perfil_id'] ?? null) != 3) {
                $validated['semestre'] = null;
                $validated['grupo'] = null;
            } elseif (!empty($validated['grupo'])) {
                $validated['grupo'] = strtoupper(trim($validated['grupo']));
            }
            $user = User::create($validated);

            return response()->json(['message' => 'Usuario creado', 'user' => $user], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            $validated = $request->validate([
                'nombres' => 'nullable|string|max:200',
                'email' => 'nullable|email|unique:users,email,' . $user->id . ',id',
                'perfil_id' => 'nullable|integer|in:1,2,3',
                'activo' => 'nullable|boolean',
                'admin_password' => 'nullable|string|max:72',
                'semestre' => 'nullable|integer|in:5,6,7,8',
                'grupo' => 'nullable|string|max:20',
                'apa' => 'nullable|string|max:100',
                'ama' => 'nullable|string|max:100',
                'direccion' => 'nullable|string|max:1000',
                'telefonos' => 'nullable|string|max:200',
            ]);

            $touchesProtectedAdmin = (int) $user->perfil_id === 1
                && (
                    (array_key_exists('activo', $validated) && !$validated['activo'])
                    || (array_key_exists('perfil_id', $validated) && (int) $validated['perfil_id'] !== 1)
                );

            if ($touchesProtectedAdmin) {
                $guard = $this->guardAdminSensitiveAction($request, $user);
                if ($guard) {
                    return $guard;
                }
            }

            unset($validated['admin_password']);
            if (array_key_exists('perfil_id', $validated) && (int) $validated['perfil_id'] !== 3) {
                $validated['semestre'] = null;
                $validated['grupo'] = null;
            } elseif (array_key_exists('grupo', $validated) && $validated['grupo']) {
                $validated['grupo'] = strtoupper(trim($validated['grupo']));
            }
            $user->update($validated);
            return response()->json(['message' => 'Usuario actualizado', 'user' => $user]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $guard = $this->guardAdminSensitiveAction($request, $user);
        if ($guard) {
            return $guard;
        }

        $user->delete();
        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function toggleActive(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $guard = $this->guardAdminSensitiveAction($request, $user);
        if ($guard) {
            return $guard;
        }

        $user->update(['activo' => !$user->activo]);
        return response()->json(['message' => 'Estado actualizado', 'activo' => $user->activo]);
    }

    public function getInactive()
    {
        return response()->json(User::where('activo', false)->paginate(15));
    }

    private function guardAdminSensitiveAction(Request $request, User $target)
    {
        if ((int) $target->perfil_id !== 1) {
            return null;
        }

        $currentAdmin = auth('api')->user();
        if (!$currentAdmin || (int) $currentAdmin->perfil_id !== 1) {
            return response()->json(['error' => 'Solo un administrador puede modificar otro administrador'], 403);
        }

        $password = $request->input('admin_password');
        if (!$password) {
            return response()->json(['error' => 'Se requiere la contraseña del administrador actual', 'requires_password' => true], 423);
        }

        if (!Hash::check($password, $currentAdmin->password)) {
            return response()->json(['error' => 'Contraseña de administrador incorrecta'], 403);
        }

        return null;
    }
}
