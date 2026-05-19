<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRegistrationWindow;
use App\Models\SubjectGroup;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()
            ->select([
                'id', 'title', 'description', 'created_by', 'created_at', 'activo',
                'semestre', 'subject_group_id', 'year', 'authors',
                'company_name', 'company_giro', 'company_contact_name',
                'company_contact_position', 'company_address',
                'proposal_status', 'proposal_reviewed_by',
            ])
            ->with([
                'creator:id,nombres,apa,ama',
                'advisors:id,nombres,apa,ama,perfil_id',
                'students:id,nombres,apa,ama,semestre,grupo',
                'subjectGroup:id,nombre,semestre,grupo,periodo',
                'asignaturas:id,nombre,clave',
                'proposalReviewer:id,nombres,apa,ama',
            ])
            ->withCount('students')
            ->where('activo', true);

        $user = auth('api')->user();
        if ($user && (int) $user->perfil_id === 2) {
            $query->where(function ($scope) use ($user) {
                $scope->whereHas('advisors', fn ($q) => $q->where('users.id', $user->id));
            });
        }

        if ($user && (int) $user->perfil_id === 3) {
            $query->whereHas('students', fn ($q) => $q->where('users.id', $user->id));
        }

        if ($request->filled('semestre')) {
            $query->where('semestre', $request->semestre);
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($scope) use ($search) {
                $scope->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('authors', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('company_contact_name', 'like', "%{$search}%")
                    ->orWhere('year', 'like', "%{$search}%")
                    ->orWhereHas('students', function ($studentQuery) use ($search) {
                        $studentQuery->where('users.id', 'like', "%{$search}%")
                            ->orWhere('users.nombres', 'like', "%{$search}%")
                            ->orWhere('users.apa', 'like', "%{$search}%")
                            ->orWhere('users.ama', 'like', "%{$search}%");
                    })
                    ->orWhereHas('advisors', function ($advisorQuery) use ($search) {
                        $advisorQuery->where('users.id', 'like', "%{$search}%")
                            ->orWhere('users.nombres', 'like', "%{$search}%")
                            ->orWhere('users.apa', 'like', "%{$search}%")
                            ->orWhere('users.ama', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    public function myProjects()
    {
        $user = auth('api')->user();
        $query = Project::query()
            ->select([
                'id', 'title', 'description', 'created_by', 'created_at', 'activo',
                'semestre', 'subject_group_id', 'year', 'authors',
                'company_name', 'company_contact_name', 'company_contact_position',
                'proposal_status', 'proposal_reviewed_by',
            ])
            ->with([
                'creator:id,nombres,apa,ama',
                'advisors:id,nombres,apa,ama,perfil_id',
                'students:id,nombres,apa,ama',
                'subjectGroup:id,nombre,semestre,grupo,periodo',
                'proposalReviewer:id,nombres,apa,ama',
            ])
            ->withCount('students')
            ->where('activo', true);

        if ((int) $user->perfil_id === 1) {
            return response()->json(['data' => $query->orderByDesc('created_at')->get()]);
        }

        if ((int) $user->perfil_id === 2) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('advisors', fn ($advisorQuery) => $advisorQuery->where('users.id', $user->id));
            });
        } elseif ((int) $user->perfil_id === 3) {
            $query->whereHas('students', fn ($studentQuery) => $studentQuery->where('users.id', $user->id));
        } else {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json(['data' => $query->orderByDesc('created_at')->get()]);
    }
    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            $validated = $request->validate($this->projectRules(true));

            if ((int) $user->perfil_id === 3) {
                if (!SystemSetting::valueFor('proposal_registration_enabled', true)) {
                    throw ValidationException::withMessages(['proposal_registration_enabled' => ['El registro de propuestas esta desactivado temporalmente.']]);
                }
                $this->guardStudentCanSubmitProposal($user, (int) ($validated['subject_group_id'] ?? 0));
                $validated['student_ids'] = array_values(array_unique(array_merge($validated['student_ids'] ?? [], [$user->id])));
                $validated['semestre'] = $user->semestre;
                $validated['year'] = $validated['year'] ?? now()->year;
            }

            $project = Project::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? $validated['descripcion'] ?? null,
                'semestre' => $validated['semestre'] ?? null,
                'subject_group_id' => $validated['subject_group_id'] ?? null,
                'year' => $validated['year'] ?? null,
                'company_name' => $validated['company_name'] ?? null,
                'company_giro' => $validated['company_giro'] ?? null,
                'company_contact_name' => $validated['company_contact_name'] ?? null,
                'company_contact_position' => $validated['company_contact_position'] ?? null,
                'company_address' => $validated['company_address'] ?? null,
                'proposal_status' => 'pendiente',
                'created_by' => $user->id,
            ]);

            $this->syncSubjectsFromGroup($project);
            $this->syncStudents($project, $validated['student_ids'] ?? []);

            return response()->json([
                'message' => 'Proyecto creado',
                'project' => $project->load(['creator', 'students', 'asignaturas', 'subjectGroup.asignaturas', 'proposalReviewer']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $project = Project::with(['creator', 'advisors', 'students', 'asignaturas', 'subjectGroup.asignaturas', 'deliverables', 'proposalReviewer'])->find($id);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }
        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        try {
            $project = Project::find($id);
            if (!$project) {
                return response()->json(['error' => 'Proyecto no encontrado'], 404);
            }

            $user = auth('api')->user();
            $validated = $request->validate($this->projectRules(false));

            if ((int) $user->perfil_id === 3) {
                $this->guardStudentCanEditProposal($user, $project);
                $validated = collect($validated)->except(['student_ids', 'semestre', 'subject_group_id', 'year', 'activo'])->toArray();
                $validated['proposal_status'] = 'pendiente';
                $validated['proposal_review_comment'] = null;
                $validated['proposal_reviewed_by'] = null;
                $validated['proposal_reviewed_at'] = null;
                $validated['revision_allowed_until'] = null;
            }

            $previousGroupId = $project->subject_group_id;
            $projectData = collect($validated)->except('student_ids')->toArray();
            $project->update($projectData);
            if (array_key_exists('subject_group_id', $projectData) && (int) $previousGroupId !== (int) $project->subject_group_id) {
                $this->syncSubjectsFromGroup($project);
            }

            if ((int) $user->perfil_id === 1 && array_key_exists('student_ids', $validated)) {
                $this->syncStudents($project, $validated['student_ids'] ?? []);
            }

            return response()->json([
                'message' => 'Proyecto actualizado',
                'project' => $project->load(['creator', 'students', 'asignaturas', 'subjectGroup.asignaturas', 'proposalReviewer']),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }
        if ((int) auth('api')->user()->perfil_id !== 1) {
            return response()->json(['error' => 'Solo administradores pueden eliminar proyectos'], 403);
        }
        $project->delete();
        return response()->json(['message' => 'Proyecto eliminado']);
    }

    public function addAdvisor(Request $request, $id)
    {
        try {
            $project = Project::find($id);
            if (!$project) {
                return response()->json(['error' => 'Proyecto no encontrado'], 404);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'string', Rule::exists('users', 'id')->where('activo', true)->whereIn('perfil_id', [1, 2])],
                'rol_asesor' => 'required|in:primario,secundario',
                'admin_password' => 'required|string|max:72',
            ]);

            $guard = $this->guardAdvisorModification($request);
            if ($guard) return $guard;

            $advisor = User::where('id', $validated['user_id'])
                ->whereIn('perfil_id', [1, 2])
                ->where('activo', true)
                ->first();
            if (!$advisor) {
                return response()->json(['message' => 'El asesor seleccionado debe ser un docente o administrador activo.'], 422);
            }

            $oppositeRole = $validated['rol_asesor'] === 'primario' ? 'secundario' : 'primario';
            $alreadyInOtherRole = $project->advisors()->where('users.id', $validated['user_id'])->wherePivot('rol_asesor', $oppositeRole)->exists();
            if ($alreadyInOtherRole) {
                return response()->json(['message' => 'La misma persona no puede ser asesor primario y secundario del proyecto.'], 422);
            }

            $project->advisors()->wherePivot('rol_asesor', $validated['rol_asesor'])->detach();
            $project->advisors()->syncWithoutDetaching([$validated['user_id'] => ['rol_asesor' => $validated['rol_asesor']]]);

            return response()->json(['message' => 'Asesor asignado', 'project' => $project->load(['advisors'])]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function removeAdvisor($projectId, $userId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }

        $guard = $this->guardAdvisorModification(request());
        if ($guard) return $guard;

        $project->advisors()->detach($userId);
        return response()->json(['message' => 'Asesor removido']);
    }

    public function syncAsignaturas(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }

        if ((int) auth('api')->user()->perfil_id !== 1) {
            return response()->json(['error' => 'Solo administradores pueden ajustar materias del proyecto'], 403);
        }

        $validated = $request->validate([
            'asignatura_ids' => 'nullable|array',
            'asignatura_ids.*' => 'integer|exists:asignaturas,id',
        ]);

        $project->asignaturas()->sync($validated['asignatura_ids'] ?? []);

        return response()->json([
            'message' => 'Materias del proyecto actualizadas',
            'project' => $project->load(['asignaturas', 'subjectGroup.asignaturas']),
        ]);
    }

    public function projectsExcelTemplate()
    {
        return $this->excelTemplateResponse('plantilla_proyectos.xls', 'Plantilla para carga masiva de proyectos', [
            'titulo',
            'descripcion',
            'semestre',
            'anio',
            'matriculas_estudiantes',
        ], [
            'matriculas_estudiantes acepta una o mas matriculas separadas por coma. Ejemplo: e000001, 0222222',
            'anio es opcional; si queda vacio se usara el año actual.',
            'Puedes usar la plantilla descargada o guardarla como .xlsx antes de importarla.',
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
            if (empty($rows)) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo no contiene filas para importar.'],
                ]);
            }
            $created = 0;
            $errors = [];
            $user = auth('api')->user();

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $studentIds = $this->parseStudentIds($row['student_ids'] ?? '');
                $data = [
                    'title' => trim((string) ($row['title'] ?? '')),
                    'description' => trim((string) ($row['description'] ?? '')),
                    'semestre' => ($row['semestre'] ?? '') !== '' ? (int) $row['semestre'] : null,
                    'year' => ($row['year'] ?? '') !== '' ? (int) $row['year'] : now()->year,
                    'student_ids' => $studentIds,
                ];

                $validator = Validator::make(
                    $data,
                    [
                        'title' => 'required|string|max:255',
                        'description' => 'required|string|max:5000',
                        'semestre' => 'required|integer|in:5,6,7,8',
                        'year' => 'nullable|integer|min:2000|max:2100',
                        'student_ids' => 'required|array|min:1',
                        'student_ids.*' => ['string', Rule::exists('users', 'id')->where('activo', true)->where('perfil_id', 3)],
                    ],
                    $this->importValidationMessages(),
                    $this->importValidationAttributes()
                );
                if ($validator->fails()) {
                    $errors[] = ['fila' => $line, 'errores' => $validator->errors()->all()];
                    continue;
                }

                try {
                    $project = Project::create([
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'semestre' => $data['semestre'],
                        'subject_group_id' => null,
                        'year' => $data['year'],
                        'company_name' => null,
                        'company_giro' => null,
                        'company_contact_name' => null,
                        'company_contact_position' => null,
                        'company_address' => null,
                        'proposal_status' => 'pendiente',
                        'created_by' => $user->id,
                    ]);
                    $this->syncSubjectsFromGroup($project);
                    $this->syncStudents($project, $data['student_ids']);
                    $created++;
                } catch (ValidationException $e) {
                    $errors[] = ['fila' => $line, 'errores' => collect($e->errors())->flatten()->all()];
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

    private function projectRules(bool $creating): array
    {
        return [
            'title' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'description' => [$creating ? 'required_without:descripcion' : 'nullable', 'string', 'max:5000'],
            'descripcion' => [$creating ? 'required_without:description' : 'nullable', 'string', 'max:5000'],
            'semestre' => [$creating ? 'required' : 'nullable', 'integer', 'in:5,6,7,8'],
            'subject_group_id' => [$creating ? 'required' : 'nullable', 'exists:subject_groups,id'],
            'year' => [$creating ? 'required' : 'nullable', 'integer', 'min:2000', 'max:2100'],
            'activo' => 'nullable|boolean',
            'student_ids' => [$creating ? 'required' : 'nullable', 'array', 'min:1'],
            'student_ids.*' => ['string', Rule::exists('users', 'id')->where('activo', true)->where('perfil_id', 3)],
            'company_name' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'company_giro' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'company_contact_name' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'company_contact_position' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'company_address' => [$creating ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    private function guardStudentCanSubmitProposal(User $student, int $subjectGroupId): void
    {
        if (!$student->profile_completed_at) {
            throw ValidationException::withMessages(['profile' => ['Debes completar tu perfil inicial antes de registrar un proyecto.']]);
        }

        $group = SubjectGroup::find($subjectGroupId);
        if (!$group || (int) $group->semestre !== (int) $student->semestre || strtoupper((string) $group->grupo) !== strtoupper((string) $student->grupo)) {
            throw ValidationException::withMessages(['subject_group_id' => ['La carga seleccionada no corresponde a tu semestre y grupo.']]);
        }

        $window = ProjectRegistrationWindow::where('subject_group_id', $subjectGroupId)
            ->where('activo', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->exists();
        if (!$window) {
            throw ValidationException::withMessages(['window' => ['El registro de proyectos no esta habilitado para tu grupo en este momento.']]);
        }

        $already = Project::whereHas('students', fn ($q) => $q->where('users.id', $student->id))->exists();
        if ($already) {
            throw ValidationException::withMessages(['student_ids' => ['Ya estas ligado a un proyecto.']]);
        }
    }

    private function guardStudentCanEditProposal(User $student, Project $project): void
    {
        $belongs = $project->students()->where('users.id', $student->id)->exists();
        if (!$belongs) {
            throw ValidationException::withMessages(['project' => ['No perteneces a este proyecto.']]);
        }
        if ($project->proposal_status !== 'requiere_cambios' || !$project->revision_allowed_until || now()->greaterThan($project->revision_allowed_until)) {
            throw ValidationException::withMessages(['project' => ['Este proyecto no tiene una ventana activa de correccion.']]);
        }
    }

    private function guardAdvisorModification(Request $request)
    {
        $currentAdmin = auth('api')->user();
        if (!$currentAdmin || (int) $currentAdmin->perfil_id !== 1) {
            return response()->json(['error' => 'Solo un administrador puede modificar asesores'], 403);
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

    private function syncStudents(Project $project, array $studentIds): void
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));
        $maxMembers = (int) SystemSetting::valueFor('max_project_members', 4);
        if (count($studentIds) > $maxMembers) {
            throw ValidationException::withMessages(['student_ids' => ["El proyecto puede tener como maximo {$maxMembers} integrantes."]]);
        }

        $students = User::whereIn('id', $studentIds)->where('perfil_id', 3)->where('activo', true)->get(['id', 'nombres', 'apa', 'ama']);
        if (count($studentIds) !== $students->count()) {
            throw ValidationException::withMessages(['student_ids' => ['Solo se pueden agregar estudiantes activos como autores.']]);
        }

        $assignedElsewhere = DB::table('project_user')
            ->join('projects', 'projects.id', '=', 'project_user.project_id')
            ->whereIn('project_user.user_id', $studentIds)
            ->whereNull('project_user.rol_asesor')
            ->where('project_user.project_id', '!=', $project->id)
            ->select('project_user.user_id', 'projects.title')
            ->get();

        if ($assignedElsewhere->isNotEmpty()) {
            $details = $assignedElsewhere->map(fn ($row) => "{$row->user_id} ya pertenece a {$row->title}")->implode(', ');
            throw ValidationException::withMessages(['student_ids' => ["Cada estudiante solo puede ser autor de un proyecto. {$details}"]]);
        }

        DB::table('project_user')->where('project_id', $project->id)->whereNull('rol_asesor')->delete();
        foreach ($students as $student) {
            DB::table('project_user')->insert(['project_id' => $project->id, 'user_id' => $student->id, 'rol_asesor' => null]);
        }

        $project->update(['authors' => $students->map(fn ($student) => trim("{$student->nombres} {$student->apa} {$student->ama}"))->implode(', ')]);
    }

    private function syncSubjectsFromGroup(Project $project): void
    {
        if (!$project->subject_group_id) {
            $project->asignaturas()->detach();
            return;
        }

        $group = SubjectGroup::with('asignaturas')->find($project->subject_group_id);
        if (!$group) {
            $project->asignaturas()->detach();
            return;
        }

        if ($project->semestre !== $group->semestre) {
            $project->update(['semestre' => $group->semestre]);
        }

        $project->asignaturas()->sync($group->asignaturas->pluck('id')->all());
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
            if (!$headerFound && $this->looksLikeProjectImportHeader($normalizedCells)) {
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
            if (!$headerFound && $this->looksLikeProjectImportHeader($normalizedCells)) {
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
        foreach (['title', 'description', 'semestre', 'year', 'student_ids'] as $key) {
            if ($this->normalizeSpreadsheetValue($row[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function looksLikeProjectImportHeader(array $headers): bool
    {
        $headers = array_filter($headers);
        $matches = array_intersect(['title', 'description', 'semestre', 'year', 'student_ids'], $headers);

        return count($matches) >= 4;
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
            'titulo' => 'title',
            'descripcion' => 'description',
            'anio' => 'year',
            'ano' => 'year',
            'year' => 'year',
            'matriculas_estudiantes' => 'student_ids',
            'matricula_estudiantes' => 'student_ids',
            'matriculas' => 'student_ids',
            'matricula' => 'student_ids',
            'estudiantes' => 'student_ids',
            'ids_estudiantes' => 'student_ids',
            'id_estudiantes' => 'student_ids',
            'student_ids' => 'student_ids',
        ];

        return $aliases[$key] ?? $key;
    }

    private function parseStudentIds($value): array
    {
        return collect(preg_split('/\s*,\s*/', (string) $value))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function importValidationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un numero valido.',
            'max' => 'El campo :attribute no debe exceder :max caracteres.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'in' => 'El valor seleccionado para :attribute no es valido.',
            'array' => 'El campo :attribute debe contener una lista valida.',
            'exists' => 'El valor de :attribute no existe o no corresponde a un estudiante activo.',
        ];
    }

    private function importValidationAttributes(): array
    {
        return [
            'title' => 'titulo',
            'description' => 'descripcion',
            'semestre' => 'semestre',
            'year' => 'anio',
            'student_ids' => 'matriculas de estudiantes',
            'student_ids.*' => 'matricula de estudiante',
        ];
    }
}
