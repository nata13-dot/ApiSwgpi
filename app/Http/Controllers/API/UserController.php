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

        return response()->json($query->orderByDesc('activo')->orderBy('nombres')->paginate(15));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|unique:users',
                'nombres' => 'required|string',
                'email' => 'nullable|email|unique:users',
                'password' => 'required|string|min:6',
                'perfil_id' => 'required|in:1,2,3',
                'apa' => 'nullable|string',
                'ama' => 'nullable|string',
                'curp' => 'nullable|string|unique:users',
            ]);

            $validated['password'] = Hash::make($validated['password']);
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
                'nombres' => 'nullable|string',
                'email' => 'nullable|email|unique:users,email,' . $user->id . ',id',
                'perfil_id' => 'nullable|in:1,2,3',
                'activo' => 'nullable|boolean',
                'apa' => 'nullable|string',
                'ama' => 'nullable|string',
                'direccion' => 'nullable|string',
                'telefonos' => 'nullable|string',
            ]);

            $user->update($validated);
            return response()->json(['message' => 'Usuario actualizado', 'user' => $user]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function toggleActive($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        $user->update(['activo' => !$user->activo]);
        return response()->json(['message' => 'Estado actualizado', 'activo' => $user->activo]);
    }

    public function getInactive()
    {
        return response()->json(User::where('activo', false)->paginate(15));
    }
}
