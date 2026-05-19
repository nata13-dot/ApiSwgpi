<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                    'perfil_id' => (int) ($row['perfil_id'] ?? 0),
                    'semestre' => ($row['semestre'] ?? '') !== '' ? (int) $row['semestre'] : null,
                    'grupo' => trim((string) ($row['grupo'] ?? '')) ?: null,
                    'curp' => trim((string) ($row['curp'] ?? '')) ?: null,
                    'activo' => $this->parseBooleanValue($row['activo'] ?? '1'),
                ];

                $validator = Validator::make($data, [
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
                ]);

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

                User::create($data);
                $created++;
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
            throw ValidationException::withMessages([
                'archivo' => ['Por ahora importa la plantilla .xls generada por el sistema. No guardes el archivo como .xlsx.'],
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
            if (!array_filter($values, fn ($value) => trim((string) $value) !== '')) continue;
            $rows[] = $this->combineSpreadsheetRow($headers, $values);
        }
        fclose($handle);

        return $rows;
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

        foreach ($tableRows[1] ?? [] as $rowIndex => $tr) {
            $cells = [];
            preg_match_all('/<t[hd]\b[^>]*>(.*?)<\/t[hd]>/is', $tr, $cellMatches);
            foreach ($cellMatches[1] ?? [] as $cell) {
                $cells[] = $this->cleanSpreadsheetCell($cell);
            }
            if ($rowIndex === 0) {
                $headers = array_map(fn ($value) => $this->normalizeImportHeader($value), $cells);
                continue;
            }
            if (!array_filter($cells, fn ($value) => trim((string) $value) !== '')) continue;
            $rows[] = $this->combineSpreadsheetRow($headers, $cells);
        }

        return $rows;
    }

    private function cleanSpreadsheetCell(string $cell): string
    {
        $cell = preg_replace('/<br\s*\/?>/i', "\n", $cell);
        return trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function combineSpreadsheetRow(array $headers, array $values): array
    {
        $headers = array_values(array_filter($headers, fn ($header) => trim((string) $header) !== ''));
        $values = array_slice(array_pad($values, count($headers), ''), 0, count($headers));

        return array_combine($headers, $values) ?: [];
    }

    private function normalizeImportHeader($header): string
    {
        $key = strtolower(trim((string) $header));
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
            'confirmar_password' => 'password_confirmation',
            'password_confirmacion' => 'password_confirmation',
            'confirmacion_password' => 'password_confirmation',
        ];

        return $aliases[$key] ?? $key;
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
