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
        $compact = $request->boolean('compact');
        $query = User::query();

        if ($compact) {
            $query->select(['id', 'nombres', 'apa', 'ama', 'email', 'perfil_id', 'semestre', 'grupo', 'telefonos', 'activo']);
        } else {
            $query->withCount([
                'projectsAsAdvisor as advising_projects_count' => fn ($q) => $q->whereNotNull('project_user.rol_asesor'),
                'projectsAsAdvisor as student_projects_count' => fn ($q) => $q->whereNull('project_user.rol_asesor'),
            ]);
        }

        if ($request->query('status') === 'inactive') {
            $query->where('activo', false);
        } elseif ($request->query('status') !== 'all') {
            $query->where('activo', true);
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

        if ($request->boolean('without_project')) {
            $query->where('perfil_id', 3)
                ->whereDoesntHave('projectsAsAdvisor', fn ($q) => $q->whereNull('project_user.rol_asesor'));
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
                'password' => 'required|string|min:6|max:72|confirmed',
                'perfil_id' => 'required|integer|in:1,2,3',
                'semestre' => 'nullable|integer|in:5,6,7,8',
                'grupo' => 'nullable|string|max:20',
                'apa' => 'nullable|string|max:100',
                'ama' => 'nullable|string|max:100',
                'curp' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'unique:users,curp'],
                'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
                'telefonos' => 'nullable|string|max:200',
            ]);

            $validated['password'] = Hash::make($validated['password']);
            if (($validated['perfil_id'] ?? null) != 3) {
                $validated['semestre'] = null;
                $validated['grupo'] = null;
            } elseif (!empty($validated['grupo'])) {
                $validated['grupo'] = strtoupper(trim($validated['grupo']));
            }
            if (!empty($validated['direccion'])) {
                $validated['direccion'] = $this->normalizeAddress($validated['direccion']);
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
                'activo' => 'nullable|boolean',
                'admin_password' => 'nullable|string|max:72',
                'semestre' => 'nullable|integer|in:5,6,7,8',
                'grupo' => 'nullable|string|max:20',
                'apa' => 'nullable|string|max:100',
                'ama' => 'nullable|string|max:100',
                'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
                'telefonos' => 'nullable|string|max:200',
                'password' => 'nullable|string|min:6|max:72|confirmed',
            ]);

            $touchesProtectedAdmin = (int) $user->perfil_id === 1
                && (
                    (array_key_exists('activo', $validated) && !$validated['activo'])
                );

            if ($touchesProtectedAdmin) {
                $guard = $this->guardAdminSensitiveAction($request, $user);
                if ($guard) {
                    return $guard;
                }
            }

            unset($validated['admin_password']);
            if (array_key_exists('password', $validated) && $validated['password']) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            if ((int) $user->perfil_id !== 3) {
                $validated['semestre'] = null;
                $validated['grupo'] = null;
            } elseif (array_key_exists('grupo', $validated) && $validated['grupo']) {
                $validated['grupo'] = strtoupper(trim($validated['grupo']));
            }
            if (!empty($validated['direccion'])) {
                $validated['direccion'] = $this->normalizeAddress($validated['direccion']);
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

        if ($request->user('api') && $request->user('api')->id === $user->id) {
            return response()->json(['error' => 'No puedes eliminar tu propio usuario administrador.'], 422);
        }

        $guard = $this->guardAdminSensitiveAction($request, $user);
        if ($guard) {
            return $guard;
        }

        $user->update(['activo' => false]);
        return response()->json(['message' => 'Usuario desactivado']);
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

    public function blankCsvTemplate()
    {
        $headers = [
            'id',
            'nombres',
            'apa',
            'ama',
            'email',
            'password',
            'password_confirmation',
            'telefonos',
            'direccion',
            'perfil_id',
            'semestre',
            'grupo',
            'curp',
        ];

        return response(implode(',', $headers) . "\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_usuarios.csv"',
        ]);
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

    private function normalizeAddress(?string $address): ?string
    {
        return $address ? preg_replace('/\s+/', ' ', trim($address)) : null;
    }
}
