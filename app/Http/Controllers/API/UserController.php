<?php

namespace App\Http\Controllers\API;

use App\Mail\UserCredentialsMail;
use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\UserCareer;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $careerId = app(\App\Support\CareerContext::class)->careerId();
        $canGovernUsers = auth('api')->user()?->globalProfileId() === 4;
        $compact = !$canGovernUsers || $request->boolean('compact');
        $hasPhoneTable = Schema::hasTable('usuarios_telefonos');
        $query = User::withoutGlobalScope('careerMembership')
            ->whereHas('careerMemberships', fn ($membershipQuery) => $membershipQuery
                ->where('carrera_id', $careerId));

        if ($compact) {
            $columns = ['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'perfil_id', 'activo'];
            if ($canGovernUsers) {
                $columns = [...$columns, 'correo', 'telefono'];
            }
            $query->select($columns);
            if ($canGovernUsers && $hasPhoneTable) {
                $query->with('phoneNumbers');
            }
        } else {
            if ($hasPhoneTable) {
                $query->with('phoneNumbers');
            }
            $query->withCount([
                'projectsAsAdvisor as advising_projects_count' => fn ($q) => $q->where('proyecto_integrantes.rol', '!=', 'integrante'),
                'projectsAsAdvisor as student_projects_count' => fn ($q) => $q->where('proyecto_integrantes.rol', 'integrante'),
            ]);
        }

        $query->addSelect([
            'career_profile_id' => UserCareer::query()
                ->select('perfil_id')
                ->whereColumn('usuario_carrera.usuario_id', 'usuarios.id')
                ->where('usuario_carrera.carrera_id', $careerId)
                ->limit(1),
            'career_membership_active' => UserCareer::query()
                ->select('activo')
                ->whereColumn('usuario_carrera.usuario_id', 'usuarios.id')
                ->where('usuario_carrera.carrera_id', $careerId)
                ->limit(1),
        ]);

        if ($request->query('status') === 'inactive') {
            $query->where(function ($statusQuery) use ($careerId): void {
                $statusQuery->where('usuarios.activo', false)
                    ->orWhereHas('careerMemberships', fn ($membershipQuery) => $membershipQuery
                        ->where('carrera_id', $careerId)
                        ->where('activo', false));
            });
        } elseif ($request->query('status') !== 'all') {
            $query->where('usuarios.activo', true)
                ->whereHas('careerMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('carrera_id', $careerId)
                    ->where('activo', true));
        }

        if ($request->filled('perfil_id')) {
            $query->whereHas('careerMemberships', fn ($membershipQuery) => $membershipQuery
                ->where('carrera_id', $careerId)
                ->where('perfil_id', (int) $request->perfil_id));
        } elseif ($request->filled('perfil_ids')) {
            $profileIds = collect(explode(',', (string) $request->query('perfil_ids')))
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => in_array($id, [1, 2, 3, 5, 6, 7], true))
                ->unique()
                ->values();

            if ($profileIds->isNotEmpty()) {
                $query->whereHas('careerMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('carrera_id', $careerId)
                    ->whereIn('perfil_id', $profileIds->all()));
            }
        }

        if ($request->filled('semestre')) {
            $query->whereExists(function ($subquery) use ($request, $careerId) {
                $subquery->selectRaw('1')
                    ->from('grupo_estudiantes')
                    ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
                    ->whereColumn('grupo_estudiantes.estudiante_id', 'usuarios.id')
                    ->where('grupo_estudiantes.activo', true)
                    ->where('grupos_academicos.carrera_id', $careerId)
                    ->where('grupos_academicos.semestre', $request->semestre);
            });
        }

        if ($request->filled('grupo')) {
            $group = strtoupper($request->grupo);
            $query->whereExists(function ($subquery) use ($group, $careerId) {
                $subquery->selectRaw('1')
                    ->from('grupo_estudiantes')
                    ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
                    ->whereColumn('grupo_estudiantes.estudiante_id', 'usuarios.id')
                    ->where('grupo_estudiantes.activo', true)
                    ->where('grupos_academicos.carrera_id', $careerId)
                    ->where('grupos_academicos.clave_grupo', $group);
            });
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($scope) use ($search, $hasPhoneTable) {
                $scope->where('id', 'like', "%{$search}%")
                    ->orWhere('nombres', 'like', "%{$search}%")
                    ->orWhere('apellido_paterno', 'like', "%{$search}%")
                    ->orWhere('apellido_materno', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellido_paterno, ''), ' ', COALESCE(apellido_materno, '')) LIKE ?", ["%{$search}%"]);

                if ($hasPhoneTable) {
                    $scope->orWhereHas('phoneNumbers', fn ($phoneQuery) => $phoneQuery->where('telefono', 'like', "%{$search}%"));
                }
            });
        }

        if ($request->boolean('without_project')) {
            $query->students()
                ->whereDoesntHave('projectsAsAdvisor', fn ($q) => $q->where('proyecto_integrantes.rol', 'integrante'));
        }

        $perPage = min((int) $request->query('per_page', $compact ? 100 : 15), $compact ? 500 : 100);
        $users = $query
            ->orderByDesc('career_membership_active')
            ->orderByDesc('usuarios.activo')
            ->orderBy('career_profile_id')
            ->orderBy('nombres')
            ->paginate($perPage);
        $users->getCollection()->transform(function (User $user): User {
            $accountActive = (bool) $user->getRawOriginal('activo');
            $membershipActive = (bool) $user->getAttribute('career_membership_active');
            $user->setAttribute('account_active', $accountActive);
            $user->setAttribute('membership_active', $membershipActive);
            $user->setAttribute('activo', $accountActive && $membershipActive);

            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:usuarios,id'],
                'nombres' => 'required|string|max:200',
                'email' => 'nullable|email|unique:usuarios,correo',
                'password' => 'required|string|min:6|max:72|confirmed',
                'perfil_id' => 'required|integer|in:1,2,3,5,6,7',
                'semestre' => 'nullable|integer|in:5,6,7,8,9',
                'grupo' => 'nullable|string|max:20',
                'apa' => 'nullable|string|max:100',
                'ama' => 'nullable|string|max:100',
                'curp' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'unique:usuarios,curp'],
                'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
                'telefonos' => 'nullable|string|max:200',
            ]);

            $academicAssignment = $this->pullAcademicAssignment($validated);
            $this->preparePhoneData($validated);
            $validated['password'] = Hash::make($validated['password']);
            if (!empty($validated['direccion'])) {
                $validated['direccion'] = $this->normalizeAddress($validated['direccion']);
            }
            $user = DB::transaction(function () use ($validated, $academicAssignment) {
                $user = User::create($validated);
                $this->syncAcademicAssignment($user, $academicAssignment);

                return $user;
            });

            return response()->json([
                'message' => 'Usuario creado',
                'user' => $this->loadPhonesIfAvailable($user),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $query = User::query();
        if (Schema::hasTable('usuarios_telefonos')) {
            $query->with('phoneNumbers');
        }
        $user = $query->find($id);
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
                'email' => 'nullable|email|unique:usuarios,correo,' . $user->id . ',id',
                'activo' => 'nullable|boolean',
                'perfil_id' => 'nullable|integer|in:1,2,3,5,6,7',
                'admin_password' => 'nullable|string|max:72',
                'semestre' => 'nullable|integer|in:5,6,7,8,9',
                'grupo' => 'nullable|string|max:20',
                'apa' => 'nullable|string|max:100',
                'ama' => 'nullable|string|max:100',
                'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
                'telefonos' => 'nullable|string|max:200',
                'password' => 'nullable|string|min:6|max:72|confirmed',
            ]);

            $touchesProtectedAdmin = $user->isAdmin()
                && (
                    (array_key_exists('activo', $validated) && !$validated['activo'])
                    || (array_key_exists('perfil_id', $validated) && (int) $validated['perfil_id'] !== (int) $user->perfil_id)
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

            $careerProfileId = (int) ($validated['perfil_id'] ?? $user->perfil_id);
            $academicAssignment = $this->pullAcademicAssignment($validated, $careerProfileId);
            unset($validated['perfil_id']);
            $this->preparePhoneData($validated);
            if (!empty($validated['direccion'])) {
                $validated['direccion'] = $this->normalizeAddress($validated['direccion']);
            }
            DB::transaction(function () use ($user, $validated, $academicAssignment, $careerProfileId) {
                $user->update($validated);
                $user->careerMemberships()
                    ->where('carrera_id', app(\App\Support\CareerContext::class)->careerId())
                    ->update(['perfil_id' => $careerProfileId, 'actualizado_en' => now()]);
                $this->syncAcademicAssignment($user, $academicAssignment);
            });
            return response()->json([
                'message' => 'Usuario actualizado',
                'user' => $this->loadPhonesIfAvailable($user->fresh()),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $careerId = app(\App\Support\CareerContext::class)->careerId();
        $user = User::withoutGlobalScope('careerMembership')
            ->whereHas('careerMemberships', fn ($query) => $query->where('carrera_id', $careerId))
            ->find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        if ($request->user('api') && $request->user('api')->id === $user->id) {
            return response()->json(['error' => 'No puedes eliminar tu propio usuario administrador.'], 422);
        }

        $membership = $user->careerMemberships()->where('carrera_id', $careerId)->firstOrFail();
        $guard = $this->guardAdminSensitiveAction($request, $user, (int) $membership->perfil_id);
        if ($guard) {
            return $guard;
        }

        DB::transaction(function () use ($user, $careerId) {
            $user->careerMemberships()->where('carrera_id', $careerId)->update([
                'activo' => false,
                'actualizado_en' => now(),
            ]);
            $this->deactivateOperationalLinks($user);
            if (!$user->careerMemberships()->where('activo', true)->exists()) {
                $user->update(['activo' => false]);
            }
        });
        return response()->json(['message' => 'Usuario desactivado en la carrera actual']);
    }

    public function toggleActive(Request $request, $id)
    {
        $careerId = app(\App\Support\CareerContext::class)->careerId();
        $user = User::withoutGlobalScope('careerMembership')
            ->whereHas('careerMemberships', fn ($query) => $query->where('carrera_id', $careerId))
            ->find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $membership = $user->careerMemberships()->where('carrera_id', $careerId)->firstOrFail();
        $guard = $this->guardAdminSensitiveAction($request, $user, (int) $membership->perfil_id);
        if ($guard) {
            return $guard;
        }

        $willBeActive = !$membership->activo;
        DB::transaction(function () use ($user, $membership, $willBeActive) {
            $membership->update(['activo' => $willBeActive]);
            if ($willBeActive && !$user->activo) {
                $user->update(['activo' => true]);
            }
            if (!$willBeActive) {
                $this->deactivateOperationalLinks($user);
                if (!$user->careerMemberships()->where('activo', true)->exists()) {
                    $user->update(['activo' => false]);
                }
            }
        });
        return response()->json(['message' => 'Estado actualizado', 'activo' => $willBeActive]);
    }

    public function getInactive(Request $request)
    {
        $request->query->set('status', 'inactive');

        return $this->index($request);
    }

    private function deactivateOperationalLinks(User $user): void
    {
        $groupIds = SubjectGroup::query()->pluck('id');
        DB::table('grupo_estudiantes')
            ->where('estudiante_id', $user->id)
            ->whereIn('grupo_id', $groupIds)
            ->where('activo', true)
            ->update(['activo' => false]);

        DB::table('curso_docentes')
            ->where('docente_id', $user->id)
            ->whereIn('curso_id', DB::table('cursos')->whereIn('grupo_id', $groupIds)->select('id'))
            ->where('activo', true)
            ->update(['activo' => false]);
    }

    public function credentialEmailTemplate()
    {
        return response()->json([
            'subject' => 'Credenciales de acceso al SGPI',
            'body' => "Hola {{Nombre}},\n\nSe han generado tus credenciales temporales para acceder al Sistema de Gestion de Proyectos Integradores.\n\nUsuario: {{Usuario}}\nCorreo: {{Correo}}\nContraseña temporal: {{Contraseña}}\nPerfil: {{Perfil}}\n\nPor seguridad, cambia tu contraseña despues de iniciar sesion.",
            'tags' => [
                '{{Nombre}}',
                '{{Usuario}}',
                '{{Correo}}',
                '{{Contraseña}}',
                '{{Perfil}}',
                '{{Semestre}}',
                '{{Grupo}}',
            ],
            'security_recommendations' => [
                'No se reenvian contraseñas existentes; el sistema genera una contraseña temporal nueva y guarda solo su hash.',
                'Solicita siempre la contraseña del administrador para autorizar el envio masivo.',
                'Evita lotes muy grandes; el endpoint limita el envio a 200 destinatarios por solicitud.',
                'Recomienda al usuario cambiar la contraseña despues del primer acceso.',
            ],
        ]);
    }

    public function sendCredentialEmails(Request $request)
    {
        try {
            $validated = $request->validate([
                'subject' => 'nullable|string|max:150',
                'body' => 'required|string|min:20|max:5000',
                'user_ids' => 'nullable|array|max:200',
                'user_ids.*' => ['string', 'max:10', 'exists:usuarios,id'],
                'perfil_id' => 'nullable|integer|in:1,2,3,5,6,7',
                'perfil_ids' => 'nullable|array|max:6',
                'perfil_ids.*' => 'integer|in:1,2,3,5,6,7',
                'status' => 'nullable|in:active,inactive,all',
                'semestre' => 'nullable|integer|in:5,6,7,8,9',
                'grupo' => 'nullable|string|max:20',
                'q' => 'nullable|string|max:100',
                'rotate_password' => 'nullable|boolean',
                'admin_password' => 'required|string|max:72',
            ], [], [
                'subject' => 'asunto',
                'body' => 'base del correo',
                'user_ids' => 'usuarios',
                'perfil_id' => 'perfil',
                'admin_password' => 'contraseña de administrador',
            ]);

            $currentAdmin = auth('api')->user();
            if (!$currentAdmin || !$currentAdmin->isAdmin()) {
                return response()->json(['error' => 'Solo un administrador puede enviar credenciales.'], 403);
            }

            if (!Hash::check($validated['admin_password'], $currentAdmin->password)) {
                return response()->json(['error' => 'Contraseña de administrador incorrecta'], 403);
            }

            $bodyTemplate = $validated['body'];
            $usesPasswordTag = $this->templateUsesPasswordTag($bodyTemplate);
            $rotatePassword = array_key_exists('rotate_password', $validated)
                ? (bool) $validated['rotate_password']
                : $usesPasswordTag;

            if ($usesPasswordTag && !$rotatePassword) {
                return response()->json([
                    'errors' => [
                        'rotate_password' => ['Para usar la etiqueta {{Contraseña}} debes generar contraseñas temporales nuevas.'],
                    ],
                ], 422);
            }

            $users = $this->credentialEmailRecipients($request)->get();

            if ($users->isEmpty()) {
                return response()->json(['errors' => ['users' => ['No hay usuarios activos con correo para este envio.']]], 422);
            }

            if ($users->count() > 200) {
                return response()->json(['errors' => ['users' => ['El envio masivo esta limitado a 200 destinatarios por solicitud. Ajusta los filtros.']]], 422);
            }

            $sent = 0;
            $errors = [];
            $subjectTemplate = $validated['subject'] ?? 'Credenciales de acceso al SGPI';

            foreach ($users as $user) {
                $temporaryPassword = '';
                $previousPasswordHash = $user->password;
                if ($rotatePassword) {
                    $temporaryPassword = $this->generateTemporaryPassword();
                    $user->forceFill(['password' => Hash::make($temporaryPassword)])->save();
                }

                try {
                    $subject = $this->renderCredentialTemplate($subjectTemplate, $user, $temporaryPassword);
                    $body = $this->renderCredentialTemplate($bodyTemplate, $user, $temporaryPassword);
                    Mail::to($user->email)->send(new UserCredentialsMail($subject, $body));
                    $sent++;
                } catch (\Throwable $e) {
                    if ($rotatePassword) {
                        $user->forceFill(['password' => $previousPasswordHash])->save();
                    }
                    report($e);
                    $errors[] = [
                        'id' => $user->id,
                        'email' => $user->email,
                        'error' => 'No se pudo enviar el correo.',
                    ];
                }
            }

            return response()->json([
                'message' => $errors ? 'Envio procesado con errores' : 'Credenciales enviadas correctamente',
                'sent' => $sent,
                'failed' => count($errors),
                'errors' => $errors,
            ], $errors ? 207 : 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function blankCsvTemplate()
    {
        return $this->usersExcelTemplate();
    }

    public function usersExcelTemplate()
    {
        return $this->excelTemplateResponse('plantilla_usuarios.xls', 'Plantilla para carga masiva de usuarios', [
            'matricula_nomina',
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'email',
            'password',
            'confirmar_password',
            'telefono',
            'direccion',
            'perfil',
            'semestre',
            'grupo',
            'curp',
            'activo',
        ], [
            'perfil: usa 1=Administrador, 2=Docente, 3=Estudiante, 5=Jefe de Carrera, 6=Asistente de Jefe de Carrera o 7=Coordinador de Proyectos.',
            'semestre y grupo solo aplican para estudiantes.',
            'activo: usa 1 para activo o 0 para inactivo.',
            'No cambies el formato del archivo a .xlsx; usa la plantilla .xls generada por el sistema.',
        ]);
    }

    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'archivo' => 'required|file|max:10240',
            ]);
            $this->guardImportExtension($request->file('archivo')->getClientOriginalExtension());

            $rows = $this->readTabularUpload($request->file('archivo')->getRealPath());
            $errors = [];
            $preparedRows = [];

            if (empty($rows)) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo no contiene filas para importar.'],
                ]);
            }

            $duplicateIds = collect($rows)
                ->map(fn ($row) => trim((string) ($row['id'] ?? '')))
                ->filter()
                ->duplicates()
                ->unique()
                ->values();
            if ($duplicateIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'archivo' => ['Hay identificadores repetidos en el archivo: '.$duplicateIds->join(', ')],
                ]);
            }

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $profileId = $this->parseProfileValue($row['perfil_id'] ?? 0);
                $semesterValue = $this->normalizeSpreadsheetValue($row['semestre'] ?? '');
                $userId = trim((string) ($row['id'] ?? ''));
                $existing = $userId === ''
                    ? null
                    : User::withoutGlobalScopes()->find($userId);
                $data = [
                    'id' => $userId,
                    'nombres' => trim((string) ($row['nombres'] ?? '')),
                    'apa' => trim((string) ($row['apa'] ?? '')) ?: null,
                    'ama' => trim((string) ($row['ama'] ?? '')) ?: null,
                    'email' => trim((string) ($row['email'] ?? '')) ?: null,
                    'password' => (string) ($row['password'] ?? ''),
                    'password_confirmation' => (string) ($row['password_confirmation'] ?? ''),
                    'telefonos' => trim((string) ($row['telefonos'] ?? '')) ?: null,
                    'direccion' => trim((string) ($row['direccion'] ?? '')) ?: null,
                    'perfil_id' => $profileId,
                    'semestre' => $profileId === 3 && $semesterValue !== '' ? (int) $semesterValue : null,
                    'grupo' => $profileId === 3 ? (trim((string) ($row['grupo'] ?? '')) ?: null) : null,
                    'curp' => trim((string) ($row['curp'] ?? '')) ?: null,
                    'activo' => $this->parseBooleanValue($row['activo'] ?? '1'),
                ];

                $validator = Validator::make(
                    $data,
                    [
                        'id' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/'],
                        'nombres' => 'required|string|max:200',
                        'email' => ['nullable', 'email', Rule::unique('usuarios', 'correo')->ignore($existing?->id, 'id')],
                        'password' => 'required|string|min:6|max:72|confirmed',
                        'perfil_id' => 'required|integer|in:1,2,3,5,6,7',
                        'semestre' => 'nullable|integer|in:5,6,7,8,9',
                        'grupo' => 'nullable|string|max:20',
                        'apa' => 'nullable|string|max:100',
                        'ama' => 'nullable|string|max:100',
                        'curp' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('usuarios', 'curp')->ignore($existing?->id, 'id')],
                        'direccion' => ['nullable', 'string', 'min:10', 'max:1000', 'regex:/^(?=.*\d)[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s#.,\-\/]+$/u'],
                        'telefonos' => 'nullable|string|max:200',
                        'activo' => 'nullable|boolean',
                    ],
                    $this->importValidationMessages(),
                    $this->importValidationAttributes()
                );

                if ($validator->fails()) {
                    $errors[] = ['fila' => $line, 'errores' => $validator->errors()->all()];
                    continue;
                }

                if ($existing && $data['email'] && $existing->email && strcasecmp($data['email'], $existing->email) !== 0) {
                    $errors[] = ['fila' => $line, 'errores' => ['El identificador ya existe con un correo diferente.']];
                    continue;
                }

                if ($existing && $profileId === 3 && $existing->careerMemberships()
                    ->where('activo', true)
                    ->where('perfil_id', 3)
                    ->where('carrera_id', '<>', app(\App\Support\CareerContext::class)->careerId())
                    ->exists()) {
                    $errors[] = ['fila' => $line, 'errores' => ['El estudiante ya tiene otra carrera activa.']];
                    continue;
                }

                $academicAssignment = $this->pullAcademicAssignment($data);
                $this->preparePhoneData($data);
                if (!empty($data['direccion'])) {
                    $data['direccion'] = $this->normalizeAddress($data['direccion']);
                }
                unset($data['password_confirmation']);
                $preparedRows[] = compact('data', 'academicAssignment', 'existing', 'profileId', 'line');
            }

            if ($errors) {
                return response()->json([
                    'message' => 'No se importó ninguna fila. Corrige el archivo e intenta nuevamente.',
                    'created' => 0,
                    'linked' => 0,
                    'errors' => $errors,
                ], 422);
            }

            $summary = DB::transaction(function () use ($preparedRows) {
                $created = 0;
                $linked = 0;
                $careerId = app(\App\Support\CareerContext::class)->careerId();

                foreach ($preparedRows as $prepared) {
                    $data = $prepared['data'];
                    $existing = $prepared['existing'];
                    if ($existing) {
                        $user = $existing;
                        UserCareer::updateOrCreate(
                            ['usuario_id' => $user->id, 'carrera_id' => $careerId],
                            [
                                'perfil_id' => $prepared['profileId'],
                                'activo' => (bool) ($data['activo'] ?? true),
                                'es_principal' => !$user->careerMemberships()->where('activo', true)->exists(),
                                'asignado_por' => auth('api')->id(),
                            ]
                        );
                        $linked++;
                    } else {
                        $data['password'] = Hash::make($data['password']);
                        $user = User::create($data);
                        $created++;
                    }
                    $this->syncAcademicAssignment($user, $prepared['academicAssignment']);
                }

                return compact('created', 'linked');
            });

            return response()->json([
                'message' => 'Importacion procesada',
                'created' => $summary['created'],
                'linked' => $summary['linked'],
                'errors' => [],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'No se pudo importar el archivo', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'No se pudo procesar el archivo. Descarga nuevamente la plantilla .xls e intenta otra vez.',
                'errors' => ['archivo' => [$e->getMessage()]],
            ], 422);
        }
    }

    private function guardAdminSensitiveAction(Request $request, User $target, ?int $careerProfileId = null)
    {
        $protectedProfile = $careerProfileId ?? (int) $target->perfil_id;
        if (!in_array($protectedProfile, [1, 5], true)) {
            return null;
        }

        $currentAdmin = auth('api')->user();
        if (!$currentAdmin || $currentAdmin->globalProfileId() !== 4) {
            return response()->json(['error' => 'Solo el Administrador General puede modificar perfiles con autoridad'], 403);
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

    private function loadPhonesIfAvailable(User $user): User
    {
        return Schema::hasTable('usuarios_telefonos')
            ? $user->load('phoneNumbers')
            : $user;
    }

    private function pullAcademicAssignment(array &$data, ?int $profileId = null): array
    {
        $requested = array_key_exists('semestre', $data) || array_key_exists('grupo', $data);
        $semester = isset($data['semestre']) && $data['semestre'] !== ''
            ? (int) $data['semestre']
            : null;
        $group = isset($data['grupo']) && trim((string) $data['grupo']) !== ''
            ? strtoupper(trim((string) $data['grupo']))
            : null;

        unset($data['semestre'], $data['grupo']);

        return [
            'requested' => $requested,
            'profile_id' => $profileId ?? (int) ($data['perfil_id'] ?? 0),
            'semester' => $semester,
            'group' => $group,
        ];
    }

    private function syncAcademicAssignment(User $user, array $assignment): void
    {
        if (!$assignment['requested']) {
            return;
        }

        $targetGroup = null;
        if ((int) $assignment['profile_id'] === 3
            && $assignment['semester'] !== null
            && $assignment['group'] !== null) {
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
            ->whereIn('grupo_id', SubjectGroup::query()->pluck('id'))
            ->where('activo', true)
            ->update(['activo' => false]);

        if (!$targetGroup) {
            return;
        }

        DB::table('grupo_estudiantes')->updateOrInsert(
            [
                'grupo_id' => $targetGroup->id,
                'estudiante_id' => $user->id,
            ],
            [
                'inscrito_en' => now(),
                'activo' => true,
            ]
        );
    }

    private function preparePhoneData(array &$data): void
    {
        if (!array_key_exists('telefonos', $data)) {
            return;
        }

        $phones = collect(preg_split('/[,;]+/', (string) ($data['telefonos'] ?? '')))
            ->map(fn ($phone) => trim($phone))
            ->filter();

        unset($data['telefonos']);
        $data['telefono'] = $phones->first();
    }

    private function credentialEmailRecipients(Request $request)
    {
        $query = User::query()
            ->whereNotNull('email')
            ->where('email', '<>', '');

        if ($request->filled('user_ids')) {
            $query->whereIn('id', $request->input('user_ids', []));
        } else {
            if ($request->query('status') === 'inactive' || $request->input('status') === 'inactive') {
                $query->where('activo', false);
            } elseif (($request->query('status') ?? $request->input('status')) !== 'all') {
                $query->where('activo', true);
            }

            if ($request->filled('perfil_ids')) {
                $query->withCareerProfiles(array_map('intval', $request->input('perfil_ids', [])));
            } elseif ($request->filled('perfil_id')) {
                $query->withCareerProfiles((int) $request->input('perfil_id'));
            }

            if ($request->filled('semestre')) {
                $query->whereExists(function ($subquery) use ($request) {
                    $subquery->selectRaw('1')
                        ->from('grupo_estudiantes')
                        ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
                        ->whereColumn('grupo_estudiantes.estudiante_id', 'usuarios.id')
                        ->where('grupo_estudiantes.activo', true)
                        ->where('grupos_academicos.semestre', $request->input('semestre'));
                });
            }

            if ($request->filled('grupo')) {
                $group = strtoupper(trim((string) $request->input('grupo')));
                $query->whereExists(function ($subquery) use ($group) {
                    $subquery->selectRaw('1')
                        ->from('grupo_estudiantes')
                        ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
                        ->whereColumn('grupo_estudiantes.estudiante_id', 'usuarios.id')
                        ->where('grupo_estudiantes.activo', true)
                        ->where('grupos_academicos.clave_grupo', $group);
                });
            }

            if ($request->filled('q')) {
                $search = trim((string) $request->input('q'));
                $query->where(function ($scope) use ($search) {
                    $scope->where('id', 'like', "%{$search}%")
                        ->orWhere('nombres', 'like', "%{$search}%")
                        ->orWhere('apellido_paterno', 'like', "%{$search}%")
                        ->orWhere('apellido_materno', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellido_paterno, ''), ' ', COALESCE(apellido_materno, '')) LIKE ?", ["%{$search}%"]);
                });
            }
        }

        return $query->orderBy('perfil_id')->orderBy('nombres')->limit(201);
    }

    private function templateUsesPasswordTag(string $template): bool
    {
        return str_contains($template, '{{Contraseña}}')
            || str_contains($template, '{{Contrasena}}')
            || str_contains($template, '{{Password}}');
    }

    private function renderCredentialTemplate(string $template, User $user, string $temporaryPassword = ''): string
    {
        $fullName = trim("{$user->nombres} {$user->apa} {$user->ama}");
        $replacements = [
            '{{Nombre}}' => $fullName ?: $user->id,
            '{{Usuario}}' => $user->id,
            '{{Correo}}' => $user->email ?? '',
            '{{Contraseña}}' => $temporaryPassword,
            '{{Contrasena}}' => $temporaryPassword,
            '{{Password}}' => $temporaryPassword,
            '{{Perfil}}' => $this->profileName((int) $user->perfil_id),
            '{{Semestre}}' => $user->semestre ? (string) $user->semestre : '',
            '{{Grupo}}' => $user->grupo ?? '',
        ];

        return strtr($template, $replacements);
    }

    private function profileName(int $profileId): string
    {
        return match ($profileId) {
            1 => 'Administrador',
            2 => 'Docente',
            3 => 'Estudiante',
            5 => 'Jefe de Carrera',
            6 => 'Asistente de Jefe de Carrera',
            7 => 'Coordinador de Proyectos',
            default => 'Usuario',
        };
    }

    private function generateTemporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@$#%';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private function normalizeAddress(?string $address): ?string
    {
        return $address ? preg_replace('/\s+/', ' ', trim($address)) : null;
    }

    private function excelTemplateResponse(string $filename, string $title, array $headers, array $notes = [])
    {
        $cells = collect($headers)->map(fn ($header) => '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>')->implode('');
        $blankRows = collect(range(1, 20))->map(fn () => '<tr>' . str_repeat('<td></td>', count($headers)) . '</tr>')->implode('');
        $noteHtml = collect($notes)->map(fn ($note) => '<p>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>')->implode('');
        $html = '<html><head><meta charset="UTF-8"></head><body>'
            . '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
            . $noteHtml
            . '<table border="1"><tr>' . $cells . '</tr>' . $blankRows . '</table>'
            . '</body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function readTabularUpload(string $path): array
    {
        $content = file_get_contents($path);
        if (str_starts_with($content, 'PK')) {
            return $this->readXlsx($path);
        }
        if (str_starts_with($content, "\xD0\xCF\x11\xE0")) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo esta guardado como Excel 97-2003 binario (.xls). Abre la plantilla y guardala como .xlsx antes de importarla.'],
            ]);
        }
        if (stripos($content, '<table') !== false) {
            return $this->readHtmlTable($content);
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value) => $this->normalizeImportHeader($value), $headers);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = $this->combineSpreadsheetRow($headers, $values);
            if (!$this->spreadsheetRowHasImportData($row)) continue;
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw ValidationException::withMessages([
                'archivo' => ['El servidor no tiene habilitado soporte para leer .xlsx. Usa la plantilla .xls sin cambiar su formato.'],
            ]);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'archivo' => ['No se pudo abrir el archivo .xlsx.'],
            ]);
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            throw ValidationException::withMessages([
                'archivo' => ['No se encontro la primera hoja del archivo .xlsx.'],
            ]);
        }

        return $this->readXlsxSheet($sheetXml, $sharedStrings);
    }

    private function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!$xml) return [];

        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/is', $xml, $matches);
        return collect($matches[1] ?? [])->map(function ($si) {
            preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $si, $textMatches);
            return $this->cleanSpreadsheetCell(implode('', $textMatches[1] ?? []));
        })->all();
    }

    private function readXlsxSheet(string $xml, array $sharedStrings): array
    {
        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/is', $xml, $rowMatches);
        $headers = [];
        $rows = [];
        $headerFound = false;

        foreach ($rowMatches[1] ?? [] as $rowXml) {
            $cells = [];
            preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/is', $rowXml, $cellMatches, PREG_SET_ORDER);
            foreach ($cellMatches as $cellMatch) {
                $attributes = $cellMatch[1] ?? '';
                $cellXml = $cellMatch[2] ?? '';
                $index = $this->xlsxColumnIndex($attributes);
                $cells[$index] = $this->xlsxCellValue($cellXml, $attributes, $sharedStrings);
            }

            if (!$cells) continue;
            $maxIndex = max(array_keys($cells));
            $orderedCells = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $orderedCells[] = $cells[$i] ?? '';
            }

            $normalizedCells = array_map(fn ($value) => $this->normalizeImportHeader($value), $orderedCells);
            if (!$headerFound && $this->looksLikeUserImportHeader($normalizedCells)) {
                $headers = $normalizedCells;
                $headerFound = true;
                continue;
            }
            if (!$headerFound) continue;

            $row = $this->combineSpreadsheetRow($headers, $orderedCells);
            if (!$this->spreadsheetRowHasImportData($row)) continue;
            $rows[] = $row;
        }

        return $rows;
    }

    private function xlsxColumnIndex(string $attributes): int
    {
        if (!preg_match('/\br="([A-Z]+)\d+"/i', $attributes, $match)) {
            return 0;
        }

        $letters = strtoupper($match[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function xlsxCellValue(string $cellXml, string $attributes, array $sharedStrings): string
    {
        $type = preg_match('/\bt="([^"]+)"/i', $attributes, $match) ? $match[1] : '';
        preg_match('/<v\b[^>]*>(.*?)<\/v>/is', $cellXml, $valueMatch);
        $value = $valueMatch[1] ?? '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }
        if ($type === 'inlineStr' && preg_match('/<t\b[^>]*>(.*?)<\/t>/is', $cellXml, $inlineMatch)) {
            return $this->cleanSpreadsheetCell($inlineMatch[1]);
        }

        return $this->normalizeSpreadsheetValue(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function guardImportExtension(string $extension): void
    {
        if (!in_array(strtolower($extension), ['xls', 'xlsx', 'csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo debe ser .xls, .xlsx o .csv.'],
            ]);
        }
    }

    private function readHtmlTable(string $html): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $tableRows);
        $headers = [];
        $rows = [];
        $headerFound = false;

        foreach ($tableRows[1] ?? [] as $rowIndex => $tr) {
            $cells = [];
            preg_match_all('/<t[hd]\b[^>]*>(.*?)<\/t[hd]>/is', $tr, $cellMatches);
            foreach ($cellMatches[1] ?? [] as $cell) {
                $cells[] = $this->cleanSpreadsheetCell($cell);
            }

            $normalizedCells = array_map(fn ($value) => $this->normalizeImportHeader($value), $cells);
            if (!$headerFound && $this->looksLikeUserImportHeader($normalizedCells)) {
                $headers = $normalizedCells;
                $headerFound = true;
                continue;
            }
            if (!$headerFound) continue;
            $row = $this->combineSpreadsheetRow($headers, $cells);
            if (!$this->spreadsheetRowHasImportData($row)) continue;
            $rows[] = $row;
        }

        return $rows;
    }

    private function cleanSpreadsheetCell(string $cell): string
    {
        $cell = preg_replace('/<br\s*\/?>/i', "\n", $cell);
        $cell = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cell = str_replace("\xc2\xa0", ' ', $cell);
        $cell = preg_replace('/\s+/u', ' ', $cell);

        return trim($cell);
    }

    private function combineSpreadsheetRow(array $headers, array $values): array
    {
        $values = array_map(fn ($value) => $this->normalizeSpreadsheetValue($value), $values);
        $row = [];

        foreach ($headers as $index => $header) {
            $header = trim((string) $header);
            if ($header === '') continue;
            $row[$header] = $values[$index] ?? '';
        }

        return $row;
    }

    private function normalizeSpreadsheetValue($value): string
    {
        $value = str_replace("\xc2\xa0", ' ', (string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function spreadsheetRowHasImportData(array $row): bool
    {
        foreach (['id', 'nombres', 'password', 'perfil_id'] as $key) {
            if ($this->normalizeSpreadsheetValue($row[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function looksLikeUserImportHeader(array $headers): bool
    {
        $headers = array_filter($headers);
        $matches = array_intersect(['id', 'nombres', 'password', 'perfil_id'], $headers);

        return count($matches) >= 3;
    }

    private function normalizeImportHeader($header): string
    {
        $key = trim((string) $header);
        $key = preg_replace('/^\xEF\xBB\xBF/u', '', $key);
        $key = strtolower($key);
        $key = strtr($key, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $key = str_replace([' ', '-', '/', '.'], '_', $key);
        $aliases = [
            'matricula' => 'id',
            'nomina' => 'id',
            'matricula_nomina' => 'id',
            'apellido_paterno' => 'apa',
            'apellido_materno' => 'ama',
            'telefono' => 'telefonos',
            'telefonos' => 'telefonos',
            'perfil' => 'perfil_id',
            'tipo' => 'perfil_id',
            'rol' => 'perfil_id',
            'contrasena' => 'password',
            'contraseña' => 'password',
            'confirmar_password' => 'password_confirmation',
            'confirmar_contrasena' => 'password_confirmation',
            'confirmar_contraseña' => 'password_confirmation',
            'contrasena_confirmacion' => 'password_confirmation',
            'contraseña_confirmacion' => 'password_confirmation',
            'password_confirmacion' => 'password_confirmation',
            'confirmacion_password' => 'password_confirmation',
            'confirmacion_contrasena' => 'password_confirmation',
            'confirmacion_contraseña' => 'password_confirmation',
        ];

        return $aliases[$key] ?? $key;
    }

    private function parseProfileValue($value): int
    {
        $text = strtolower($this->normalizeSpreadsheetValue($value));
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);

        if (is_numeric($text)) {
            return (int) $text;
        }

        return match ($text) {
            'administrador', 'admin', 'administrativo', 'administrativa' => 1,
            'docente', 'profesor', 'maestro', 'teacher' => 2,
            'estudiante', 'alumno', 'student' => 3,
            'jefe de carrera', 'jefe_carrera', 'jefatura' => 5,
            'asistente de jefe de carrera', 'asistente_jefe_carrera', 'asistente de jefatura' => 6,
            'coordinador de proyectos', 'coordinador_proyectos', 'coordinador' => 7,
            default => 0,
        };
    }

    private function importValidationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un numero valido.',
            'boolean' => 'El campo :attribute debe ser 1 o 0.',
            'email' => 'El campo :attribute debe ser un correo valido.',
            'max' => 'El campo :attribute no debe exceder :max caracteres.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'in' => 'El valor seleccionado para :attribute no es valido.',
            'unique' => 'El valor de :attribute ya existe en el sistema.',
            'confirmed' => 'La confirmacion de :attribute no coincide.',
            'regex' => 'El campo :attribute tiene un formato invalido.',
        ];
    }

    private function importValidationAttributes(): array
    {
        return [
            'id' => 'matricula/nomina',
            'nombres' => 'nombres',
            'apa' => 'apellido paterno',
            'ama' => 'apellido materno',
            'email' => 'correo',
            'password' => 'contrasena',
            'password_confirmation' => 'confirmacion de contrasena',
            'perfil_id' => 'perfil',
            'semestre' => 'semestre',
            'grupo' => 'grupo',
            'curp' => 'CURP',
            'direccion' => 'direccion',
            'telefonos' => 'telefono',
            'activo' => 'activo',
        ];
    }

    private function parseBooleanValue($value): bool
    {
        $text = strtolower(trim((string) $value));
        if (in_array($text, ['0', 'false', 'no', 'inactivo'], true)) {
            return false;
        }

        return true;
    }
}
