<?php

namespace App\Http\Controllers\API;

use App\Mail\UserCredentialsMail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
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

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($scope) use ($search) {
                $scope->where('id', 'like', "%{$search}%")
                    ->orWhere('nombres', 'like', "%{$search}%")
                    ->orWhere('apa', 'like', "%{$search}%")
                    ->orWhere('ama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefonos', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apa, ''), ' ', COALESCE(ama, '')) LIKE ?", ["%{$search}%"]);
            });
        }

        if ($request->boolean('without_project')) {
            $query->where('perfil_id', 3)
                ->whereDoesntHave('projectsAsAdvisor', fn ($q) => $q->whereNull('project_user.rol_asesor'));
        }

        $perPage = min((int) $request->query('per_page', $compact ? 100 : 15), $compact ? 500 : 100);
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
                'user_ids.*' => ['string', 'max:10', 'exists:users,id'],
                'perfil_id' => 'nullable|integer|in:1,2,3',
                'perfil_ids' => 'nullable|array|max:3',
                'perfil_ids.*' => 'integer|in:1,2,3',
                'status' => 'nullable|in:active,inactive,all',
                'semestre' => 'nullable|integer|in:5,6,7,8',
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
            if (!$currentAdmin || (int) $currentAdmin->perfil_id !== 1) {
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
            'perfil: usa 1=Administrador, 2=Docente, 3=Estudiante.',
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
            $created = 0;
            $errors = [];

            if (empty($rows)) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo no contiene filas para importar.'],
                ]);
            }

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $profileId = $this->parseProfileValue($row['perfil_id'] ?? 0);
                $semesterValue = $this->normalizeSpreadsheetValue($row['semestre'] ?? '');
                $data = [
                    'id' => trim((string) ($row['id'] ?? '')),
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
                        'id' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:users,id'],
                        'nombres' => 'required|string|max:200',
                        'email' => 'nullable|email|unique:users,email',
                        'password' => 'required|string|min:6|max:72|confirmed',
                        'perfil_id' => 'required|integer|in:1,2,3',
                        'semestre' => 'nullable|integer|in:5,6,7,8',
                        'grupo' => 'nullable|string|max:20',
                        'apa' => 'nullable|string|max:100',
                        'ama' => 'nullable|string|max:100',
                        'curp' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'unique:users,curp'],
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

                if ($data['perfil_id'] !== 3) {
                    $data['semestre'] = null;
                    $data['grupo'] = null;
                } elseif (!empty($data['grupo'])) {
                    $data['grupo'] = strtoupper(trim($data['grupo']));
                }
                if (!empty($data['direccion'])) {
                    $data['direccion'] = $this->normalizeAddress($data['direccion']);
                }
                $data['password'] = Hash::make($data['password']);
                unset($data['password_confirmation']);

                try {
                    User::create($data);
                    $created++;
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = ['fila' => $line, 'errores' => ['No se pudo crear el usuario: ' . $e->getMessage()]];
                }
            }

            return response()->json([
                'message' => 'Importacion procesada',
                'created' => $created,
                'errors' => $errors,
            ], $errors ? 207 : 201);
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
                $query->whereIn('perfil_id', array_map('intval', $request->input('perfil_ids', [])));
            } elseif ($request->filled('perfil_id')) {
                $query->where('perfil_id', $request->input('perfil_id'));
            }

            if ($request->filled('semestre')) {
                $query->where('semestre', $request->input('semestre'));
            }

            if ($request->filled('grupo')) {
                $query->where('grupo', strtoupper(trim((string) $request->input('grupo'))));
            }

            if ($request->filled('q')) {
                $search = trim((string) $request->input('q'));
                $query->where(function ($scope) use ($search) {
                    $scope->where('id', 'like', "%{$search}%")
                        ->orWhere('nombres', 'like', "%{$search}%")
                        ->orWhere('apa', 'like', "%{$search}%")
                        ->orWhere('ama', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apa, ''), ' ', COALESCE(ama, '')) LIKE ?", ["%{$search}%"]);
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
