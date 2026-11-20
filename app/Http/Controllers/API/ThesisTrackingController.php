<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Models\RepositoryDocument;
use App\Models\SystemSetting;
use App\Services\AcademicWorkflowService;
use App\Services\ActivityNotificationService;
use App\Services\ProtectedDownloadService;
use App\Services\StoredFileService;
use App\Services\ThesisTrackingService;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Seguimiento de tesis: curso de un alumno sobre su tesis, con capítulos, n docentes revisores, entregas
 * planeadas, versiones del documento (PDF) que cubren capítulos, puntaje y comentario por capítulo y notas
 * por página. Jefatura, asistencia, administración o el director de la tesis crean y administran el curso.
 */
class ThesisTrackingController extends Controller
{
    private const ROLES = ['director', 'codirector', 'revisor', 'invitado'];
    // En una tesis el director se registra como "asesor" y los revisores como revisor_1/revisor_2 (sp_validar_integrante).
    private const PROJECT_ROLES = ['asesor' => 'director', 'revisor_1' => 'revisor', 'revisor_2' => 'revisor'];

    public function __construct(private ThesisTrackingService $thesis, private AcademicWorkflowService $workflow) {}

    private function career(): int
    {
        abort_unless($this->thesis->ready(), 503, 'Falta instalar la actualización de seguimiento de tesis.');

        return $this->workflow->careerId();
    }

    private function me(): string
    {
        return (string) auth('api')->id();
    }

    private function isStaff(): bool
    {
        return auth('api')->user()->isGeneralAdmin() || in_array((int) app(CareerContext::class)->profileId(), [1, 5, 6], true);
    }

    private function course(int $id): object
    {
        $course = DB::table('tesis_cursos')->where('id', $id)->where('carrera_id', $this->career())->first();
        abort_unless($course, 404, 'Curso de tesis no encontrado.');

        return $course;
    }

    /** Administra: jefatura, asistencia, administración o el director del curso. */
    private function canManage(object $course): bool
    {
        return $this->isStaff() || DB::table('tesis_revisores')->where('curso_id', $course->id)->where('docente_id', $this->me())->where('rol', 'director')->where('activo', true)->exists();
    }

    private function role(object $course): string
    {
        if ((string) $course->estudiante_id === $this->me()) return 'student';
        if ($this->thesis->isReviewer($course->id, $this->me())) return 'reviewer';
        abort_unless($this->isStaff(), 403, 'No participas en este curso de tesis.');

        return 'staff';
    }

    // ------------------------------------------------------------------ Consulta

    public function index()
    {
        $career = $this->career();
        $user = auth('api')->user();
        $query = DB::table('tesis_cursos as c')->join('usuarios as u', 'u.id', '=', 'c.estudiante_id')->where('c.carrera_id', $career);
        if ($user->isStudent()) $query->where('c.estudiante_id', $this->me());
        elseif (!$this->isStaff()) $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('tesis_revisores as r')->whereColumn('r.curso_id', 'c.id')
            ->where('r.docente_id', $this->me())->where('r.activo', true));
        $courses = $query->orderByRaw("FIELD(c.estado, 'activo', 'liberada', 'cerrado')")->orderBy('c.titulo')
            ->get(['c.*', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno']);

        return response()->json([
            'data' => $courses->map(function ($course) use ($user) {
                $reviewers = $this->thesis->reviewers($course->id);
                $chapters = $this->thesis->chapters($course->id, $reviewers);
                $next = DB::table('tesis_plan_entregas as p')->where('p.curso_id', $course->id)->whereNotNull('p.fecha_limite')->where('p.fecha_limite', '>=', now())
                    ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('tesis_envios as e')->whereColumn('e.plan_id', 'p.id'))
                    ->orderBy('p.fecha_limite')->first(['p.titulo', 'p.fecha_limite']);
                $pending = $user->isStudent() ? 0 : $chapters->filter(fn ($c) => $c['envio_id'] && !collect($c['revisiones'])->contains('docente_id', $this->me())
                    && $reviewers->contains('id', $this->me()))->count();

                return [
                    'id' => $course->id, 'titulo' => $course->titulo, 'estado' => $course->estado,
                    'alumno' => trim("{$course->nombres} {$course->apellido_paterno} {$course->apellido_materno}"),
                    'capitulos' => $chapters->count(), 'aprobados' => $chapters->where('estado', 'aprobado')->count(),
                    'general' => ThesisTrackingService::general($chapters), 'por_revisar' => $pending,
                    'proxima' => $next ? ['titulo' => $next->titulo, 'fecha_limite' => Carbon::parse($next->fecha_limite)->toIso8601String()] : null,
                    'revisores' => $reviewers->pluck('nombre')->values(),
                ];
            })->values(),
            'can_create' => $this->isStaff() || DB::table('proyecto_integrantes')->where('usuario_id', $this->me())->where('rol', 'asesor')->exists(),
        ]);
    }

    /** Tesis disponibles para crear un curso (con sus alumnos y docentes) y docentes de la carrera. */
    public function options()
    {
        $career = $this->career();
        $projects = DB::table('proyectos as p')->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')
            ->where('g.carrera_id', $career)->where('p.activo', true)->where('p.tipo', 'tesis')
            ->when(!$this->isStaff(), fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('proyecto_integrantes as i')
                ->whereColumn('i.proyecto_id', 'p.id')->where('i.usuario_id', $this->me())->where('i.rol', 'asesor')))
            ->orderBy('p.titulo')->get(['p.id', 'p.titulo']);
        abort_if(!$this->isStaff() && $projects->isEmpty(), 403, 'Sólo jefatura o el director de una tesis crean cursos de tesis.');
        $members = DB::table('proyecto_integrantes as i')->join('usuarios as u', 'u.id', '=', 'i.usuario_id')->whereIn('i.proyecto_id', $projects->pluck('id'))
            ->get(['i.proyecto_id', 'i.rol', 'u.id', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno'])->groupBy('proyecto_id');
        $existing = DB::table('tesis_cursos')->whereIn('proyecto_id', $projects->pluck('id'))->get(['proyecto_id', 'estudiante_id'])
            ->map(fn ($row) => "{$row->proyecto_id}:{$row->estudiante_id}")->all();
        $name = fn ($u) => trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}");

        return response()->json(['data' => [
            'projects' => $projects->map(fn ($p) => [
                'id' => $p->id, 'titulo' => $p->titulo,
                'alumnos' => ($members->get($p->id) ?? collect())->whereIn('rol', ['lider', 'integrante'])->map(fn ($u) => [
                    'id' => (string) $u->id, 'nombre' => $name($u), 'con_curso' => in_array("{$p->id}:{$u->id}", $existing, true),
                ])->values(),
                'docentes' => ($members->get($p->id) ?? collect())->whereIn('rol', array_keys(self::PROJECT_ROLES))->map(fn ($u) => [
                    'id' => (string) $u->id, 'nombre' => $name($u), 'rol' => self::PROJECT_ROLES[$u->rol],
                ])->values(),
            ])->values(),
            'teachers' => DB::table('usuario_carrera as uc')->join('usuarios as u', 'u.id', '=', 'uc.usuario_id')->where('uc.carrera_id', $career)
                ->where('uc.activo', true)->where('u.activo', true)->whereIn('uc.perfil_id', [1, 2, 5, 6, 7])->orderBy('u.apellido_paterno')
                ->get(['u.id', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno'])->unique('id')->map(fn ($u) => ['id' => (string) $u->id, 'nombre' => $name($u)])->values(),
        ]]);
    }

    public function show(int $id)
    {
        $course = $this->course($id);
        $role = $this->role($course);
        $student = $role === 'student';
        $reviewers = $this->thesis->reviewers($course->id);
        $names = $reviewers->pluck('nombre', 'id');
        $chapters = $this->thesis->chapters($course->id, $reviewers);
        $plan = DB::table('tesis_plan_entregas')->where('curso_id', $course->id)->orderBy('numero')->get();
        $envios = DB::table('tesis_envios as e')->join('documento_versiones as v', function ($join) {
            $join->on('v.documento_id', '=', 'e.documento_id')->whereRaw('v.numero_version = (SELECT MAX(x.numero_version) FROM documento_versiones x WHERE x.documento_id = e.documento_id)');
        })->where('e.curso_id', $course->id)->orderByDesc('e.numero')->get(['e.*', 'v.nombre_archivo']);
        $covered = DB::table('tesis_envio_capitulos')->whereIn('envio_id', $envios->pluck('id'))->get()->groupBy('envio_id');
        $reviews = DB::table('tesis_revisiones')->whereIn('envio_id', $envios->pluck('id'))->get()->groupBy('envio_id');
        $notes = DB::table('tesis_anotaciones')->whereIn('envio_id', $envios->pluck('id'))->orderBy('pagina')->get()->groupBy('envio_id');
        $latestByChapter = $chapters->pluck('envio_id', 'id');
        $student_row = DB::table('usuarios')->where('id', $course->estudiante_id)->first(['nombres', 'apellido_paterno', 'apellido_materno']);
        $teacherName = fn ($id) => $names[(string) $id] ?? (DB::table('usuarios')->where('id', $id)->selectRaw("TRIM(CONCAT(nombres, ' ', apellido_paterno)) as n")->value('n') ?: 'Docente');

        return response()->json(['data' => [
            'course' => [
                'id' => $course->id, 'titulo' => $course->titulo, 'descripcion' => $course->descripcion, 'estado' => $course->estado,
                'liberada_en' => $course->liberada_en ? Carbon::parse($course->liberada_en)->toIso8601String() : null,
                'alumno' => ['id' => $course->estudiante_id, 'nombre' => trim("{$student_row?->nombres} {$student_row?->apellido_paterno} {$student_row?->apellido_materno}")],
                'proyecto' => DB::table('proyectos')->where('id', $course->proyecto_id)->first(['id', 'titulo']),
            ],
            'me' => [
                'role' => $role, 'can_manage' => $this->canManage($course), 'can_review' => $role === 'reviewer' && $course->estado === 'activo',
                'can_submit' => $student && $course->estado === 'activo', 'id' => $this->me(),
            ],
            'general' => ThesisTrackingService::general($chapters),
            'aprobatoria' => ThesisTrackingService::PASSING,
            'puede_liberar' => $course->estado === 'activo' && ThesisTrackingService::allApproved($chapters) && $this->canManage($course),
            // El alumno no ve el puntaje de cada docente ni el de cada capítulo: sólo el general y los comentarios.
            'capitulos' => $chapters->map(fn ($c) => [
                'id' => $c['id'], 'numero' => $c['numero'], 'titulo' => $c['titulo'], 'estado' => $c['estado'], 'envio_id' => $c['envio_id'],
                'revisados' => $c['revisados'], 'revisores' => $c['revisores'],
                'promedio' => $student ? null : $c['promedio'],
                'revisiones' => $student ? [] : array_map(fn ($r) => $r + ['nombre' => $teacherName($r['docente_id'])], $c['revisiones']),
            ])->values(),
            'revisores' => $reviewers->values(),
            'plan' => $plan->map(fn ($p) => [
                'id' => $p->id, 'numero' => $p->numero, 'titulo' => $p->titulo,
                'fecha_limite' => $p->fecha_limite ? Carbon::parse($p->fecha_limite)->toIso8601String() : null,
                'capitulos' => json_decode($p->capitulos ?? '[]', true) ?: [],
                'entregado' => $envios->contains('plan_id', $p->id),
            ])->values(),
            'envios' => $envios->map(fn ($e) => [
                'id' => $e->id, 'numero' => $e->numero, 'plan_id' => $e->plan_id, 'comentario' => $e->comentario, 'archivo' => $e->nombre_archivo,
                'enviado_en' => Carbon::parse($e->enviado_en)->toIso8601String(),
                'capitulos' => ($covered->get($e->id) ?? collect())->pluck('capitulo_id')->map(fn ($v) => (int) $v)->values(),
                'vigentes' => ($covered->get($e->id) ?? collect())->pluck('capitulo_id')->filter(fn ($c) => (int) ($latestByChapter[$c] ?? 0) === (int) $e->id)->map(fn ($v) => (int) $v)->values(),
                'revisiones' => ($reviews->get($e->id) ?? collect())->map(fn ($r) => [
                    'capitulo_id' => (int) $r->capitulo_id, 'docente_id' => (string) $r->docente_id, 'nombre' => $teacherName($r->docente_id),
                    'puntaje' => $student ? null : (float) $r->puntaje, 'comentario' => $r->comentario,
                    'fecha' => Carbon::parse($r->actualizado_en ?? $r->creado_en)->toIso8601String(),
                ])->values(),
                'anotaciones' => ($notes->get($e->id) ?? collect())->map(fn ($n) => [
                    'id' => $n->id, 'pagina' => (int) $n->pagina, 'texto' => $n->texto, 'capitulo_id' => $n->capitulo_id, 'nombre' => $teacherName($n->docente_id),
                    'mia' => (string) $n->docente_id === $this->me(),
                ])->values(),
            ])->values(),
        ]]);
    }

    // ------------------------------------------------------------------ Administración

    public function store(Request $request)
    {
        $career = $this->career();
        $data = $this->validateCourse($request, true);
        $project = DB::table('proyectos as p')->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')
            ->where('p.id', $data['proyecto_id'])->where('g.carrera_id', $career)->where('p.tipo', 'tesis')->first(['p.id', 'p.titulo']);
        abort_unless($project, 422, 'Elige una tesis activa de la carrera.');
        abort_unless($this->isStaff() || DB::table('proyecto_integrantes')->where('proyecto_id', $project->id)->where('usuario_id', $this->me())->where('rol', 'asesor')->exists(),
            403, 'Sólo jefatura o el director de la tesis crean su curso.');
        abort_unless(DB::table('proyecto_integrantes')->where('proyecto_id', $project->id)->where('usuario_id', $data['estudiante_id'])->whereIn('rol', ['lider', 'integrante'])->exists(),
            422, 'El alumno no es integrante de esa tesis.');
        abort_if(DB::table('tesis_cursos')->where('proyecto_id', $project->id)->where('estudiante_id', $data['estudiante_id'])->exists(), 422, 'El alumno ya tiene curso en esta tesis.');
        abort_unless(collect($data['revisores'])->contains('rol', 'director') || DB::table('proyecto_integrantes')->where('proyecto_id', $project->id)->where('rol', 'asesor')->exists(), 422, 'Indica quién dirige la tesis.');

        $id = DB::transaction(function () use ($data, $career, $project) {
            $id = DB::table('tesis_cursos')->insertGetId([
                'carrera_id' => $career, 'proyecto_id' => $project->id, 'estudiante_id' => $data['estudiante_id'],
                'titulo' => ($data['titulo'] ?? null) ?: $project->titulo, 'descripcion' => $data['descripcion'] ?? null,
                'creado_por' => $this->me(), 'creado_en' => now(), 'actualizado_en' => now(),
            ]);
            $chapters = [];
            foreach (array_values($data['capitulos']) as $i => $chapter) {
                $chapters[$i + 1] = DB::table('tesis_capitulos')->insertGetId(['curso_id' => $id, 'numero' => $i + 1, 'titulo' => $chapter['titulo']]);
            }
            $this->saveReviewers($id, $data['revisores']);
            $this->savePlan($id, $data['plan'] ?? [], count($chapters));
            $this->workflow->audit('tesis.curso_creado', 'tesis_curso', $id, null, $data);

            return $id;
        });
        $this->notify($id, DB::table('tesis_revisores')->where('curso_id', $id)->where('activo', true)->pluck('docente_id')->push($data['estudiante_id']),
            'Curso de tesis creado', "Se abrió el seguimiento de la tesis «".(($data['titulo'] ?? null) ?: $project->titulo)."».");

        return response()->json(['message' => 'Curso de tesis creado.', 'id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        $course = $this->course($id);
        abort_unless($this->canManage($course), 403, 'Sólo jefatura o el director administran el curso.');
        $data = $this->validateCourse($request, false);
        DB::transaction(function () use ($course, $data) {
            DB::table('tesis_cursos')->where('id', $course->id)->update(['titulo' => ($data['titulo'] ?? null) ?: $course->titulo, 'descripcion' => $data['descripcion'] ?? null, 'actualizado_en' => now()]);
            // Los capítulos conservan su id (y sus revisiones); los quitados se desactivan.
            $kept = [];
            foreach (array_values($data['capitulos']) as $i => $chapter) {
                $values = ['numero' => $i + 1, 'titulo' => $chapter['titulo'], 'activo' => true];
                if (!empty($chapter['id']) && DB::table('tesis_capitulos')->where('id', $chapter['id'])->where('curso_id', $course->id)->exists()) {
                    DB::table('tesis_capitulos')->where('id', $chapter['id'])->update($values); $kept[] = (int) $chapter['id'];
                } else $kept[] = DB::table('tesis_capitulos')->insertGetId($values + ['curso_id' => $course->id]);
            }
            DB::table('tesis_capitulos')->where('curso_id', $course->id)->whereNotIn('id', $kept)->update(['activo' => false]);
            if (array_key_exists('plan', $data)) {
                DB::table('tesis_plan_entregas')->where('curso_id', $course->id)->whereNotIn('id', collect($data['plan'])->pluck('id')->filter()->all())
                    ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('tesis_envios as e')->whereColumn('e.plan_id', 'tesis_plan_entregas.id'))->delete();
                $this->savePlan($course->id, $data['plan'], count($kept));
            }
            $this->workflow->audit('tesis.curso', 'tesis_curso', $course->id, $course, $data);
        });

        return response()->json(['message' => 'Curso de tesis actualizado.']);
    }

    public function updateReviewers(Request $request, int $id)
    {
        $course = $this->course($id);
        abort_unless($this->canManage($course), 403, 'Sólo jefatura o el director administran el curso.');
        $data = $request->validate(['revisores' => 'required|array|min:1|max:15'] + $this->reviewerRules());
        abort_unless(collect($data['revisores'])->contains('rol', 'director'), 422, 'El curso necesita un director.');
        $before = $this->thesis->reviewers($course->id);
        DB::transaction(function () use ($course, $data) {
            DB::table('tesis_revisores')->where('curso_id', $course->id)->update(['activo' => false]);
            $this->saveReviewers($course->id, $data['revisores']);
        });
        $this->workflow->audit('tesis.revisores', 'tesis_curso', $course->id, $before, $data['revisores']);
        $new = collect($data['revisores'])->pluck('docente_id')->diff($before->pluck('id'));
        if ($new->isNotEmpty()) $this->notify($course->id, $new, 'Revisión de tesis asignada', "Te agregaron como revisor de la tesis «{$course->titulo}».");

        return response()->json(['message' => 'Docentes revisores actualizados.']);
    }

    public function release(int $id)
    {
        $course = $this->course($id);
        abort_unless($this->canManage($course), 403);
        abort_unless($course->estado === 'activo', 422, 'El curso no está activo.');
        abort_unless(ThesisTrackingService::allApproved($this->thesis->chapters($course->id)), 422, 'Todos los capítulos deben estar aprobados por todos los docentes.');
        DB::table('tesis_cursos')->where('id', $course->id)->update(['estado' => 'liberada', 'liberada_en' => now(), 'liberada_por' => $this->me(), 'actualizado_en' => now()]);
        $this->workflow->audit('tesis.liberada', 'tesis_curso', $course->id, $course, ['estado' => 'liberada']);
        $this->notify($course->id, $this->thesis->reviewers($course->id)->pluck('id')->push($course->estudiante_id), 'Tesis liberada',
            "La tesis «{$course->titulo}» quedó liberada para su evaluación de titulación.");

        return response()->json(['message' => 'Tesis liberada para titulación.']);
    }

    public function reopen(int $id)
    {
        $course = $this->course($id);
        abort_unless($this->canManage($course), 403);
        DB::table('tesis_cursos')->where('id', $course->id)->update(['estado' => 'activo', 'liberada_en' => null, 'liberada_por' => null, 'actualizado_en' => now()]);
        $this->workflow->audit('tesis.reabierta', 'tesis_curso', $course->id, $course, ['estado' => 'activo']);

        return response()->json(['message' => 'Curso reabierto: el alumno puede volver a entregar.']);
    }

    // ------------------------------------------------------------------ Alumno

    public function submit(Request $request, int $id, StoredFileService $files)
    {
        $course = $this->course($id);
        abort_unless((string) $course->estudiante_id === $this->me(), 403, 'Sólo el alumno del curso entrega.');
        abort_unless($course->estado === 'activo', 422, 'El curso no está activo.');
        $maxMb = (int) SystemSetting::valueFor('max_file_size_mb', 50);
        $data = $request->validate([
            'archivo' => 'required|file|max:'.($maxMb * 1024),
            'capitulos' => 'required|array|min:1',
            'capitulos.*' => ['integer', Rule::exists('tesis_capitulos', 'id')->where('curso_id', $course->id)->where('activo', true)],
            'plan_id' => ['nullable', 'integer', Rule::exists('tesis_plan_entregas', 'id')->where('curso_id', $course->id)],
            'comentario' => 'nullable|string|max:2000',
        ]);
        // Sólo PDF: los docentes lo leen y anotan por página dentro del sistema.
        $prepared = $files->prepare($request->file('archivo'), ['pdf'], $maxMb);
        $stored = null;
        try {
            $stored = $files->promote($prepared, sprintf('thesis/career-%d/course-%d', $course->carrera_id, $course->id));
            $envio = DB::transaction(function () use ($course, $data, $prepared, $stored) {
                DB::table('tesis_cursos')->where('id', $course->id)->lockForUpdate()->first();
                $number = (int) DB::table('tesis_envios')->where('curso_id', $course->id)->max('numero') + 1;
                $user = auth('api')->user();
                $document = RepositoryDocument::create([
                    'carrera_id' => $course->carrera_id, 'project_id' => $course->proyecto_id, 'nombre' => "Tesis · entrega {$number}",
                    'descripcion' => $data['comentario'] ?? null, 'autores' => trim($user->getFullName()) ?: $this->me(),
                    'document_category' => RepositoryDocument::CATEGORY_THESIS_GENERAL, 'visibility' => RepositoryDocument::VISIBILITY_PRIVATE,
                    'estado' => 'borrador', 'uploaded_by' => $this->me(), 'activo' => true,
                ]);
                $attributes = ['document_id' => $document->id, 'version_number' => 1, 'file_name' => $prepared['file_name'], 'file_path' => $stored['path'],
                    'extension' => $prepared['extension'], 'mime_type' => $prepared['mime_type'], 'size_bytes' => $prepared['size_bytes'],
                    'descripcion' => "Entrega {$number} de tesis", 'uploaded_by' => $this->me(), 'created_at' => now()];
                if (\App\Support\SchemaCache::hasColumn('documento_versiones', 'nombre_original')) $attributes['original_name'] = $prepared['original_name'];
                if (\App\Support\SchemaCache::hasColumn('documento_versiones', 'disco')) $attributes['disk'] = $stored['disk'];
                if (\App\Support\SchemaCache::hasColumn('documento_versiones', 'checksum_sha256')) $attributes['checksum'] = $prepared['checksum_sha256'];
                DocumentVersion::create($attributes);
                $envio = DB::table('tesis_envios')->insertGetId(['curso_id' => $course->id, 'plan_id' => $data['plan_id'] ?? null, 'documento_id' => $document->id,
                    'numero' => $number, 'comentario' => $data['comentario'] ?? null, 'enviado_por' => $this->me(), 'enviado_en' => now()]);
                foreach (array_unique($data['capitulos']) as $chapter) DB::table('tesis_envio_capitulos')->insert(['envio_id' => $envio, 'capitulo_id' => $chapter]);
                $this->workflow->audit('tesis.entrega', 'tesis_curso', $course->id, null, ['envio' => $envio, 'capitulos' => $data['capitulos']]);

                return $envio;
            });
        } catch (\Throwable $e) {
            $files->discardStored($stored);
            $files->discardPrepared($prepared);
            throw $e;
        }
        $files->discardPrepared($prepared);
        $numbers = DB::table('tesis_capitulos')->whereIn('id', $data['capitulos'])->orderBy('numero')->pluck('numero')->implode(', ');
        $this->notify($course->id, $this->thesis->reviewers($course->id)->pluck('id'), 'Nueva entrega de tesis', "Llegó una entrega de «{$course->titulo}» con los capítulos {$numbers}.");

        return response()->json(['message' => 'Entrega recibida. Tus docentes recibirán un aviso.', 'id' => $envio], 201);
    }

    public function file(int $envio, ProtectedDownloadService $downloads)
    {
        $row = DB::table('tesis_envios')->where('id', $envio)->first();
        abort_unless($row, 404);
        $this->role($this->course((int) $row->curso_id));
        $version = DocumentVersion::where('documento_id', $row->documento_id)->orderByDesc('numero_version')->firstOrFail();

        return $downloads->download($version, $version->original_name ?: $version->file_name, true);
    }

    // ------------------------------------------------------------------ Docentes

    public function review(Request $request, int $envio, int $chapter)
    {
        [$row, $course] = $this->reviewable($envio);
        abort_unless(DB::table('tesis_envio_capitulos')->where('envio_id', $row->id)->where('capitulo_id', $chapter)->exists(), 422, 'Esa entrega no incluye el capítulo.');
        $latest = DB::table('tesis_envio_capitulos as ec')->join('tesis_envios as e', 'e.id', '=', 'ec.envio_id')->where('e.curso_id', $course->id)->where('ec.capitulo_id', $chapter)->max('e.id');
        abort_unless((int) $latest === (int) $row->id, 422, 'Hay una versión más reciente de este capítulo; revisa ésa.');
        $data = $request->validate(['puntaje' => 'required|numeric|min:0|max:100', 'comentario' => 'nullable|string|max:5000']);
        $key = ['envio_id' => $row->id, 'capitulo_id' => $chapter, 'docente_id' => $this->me()];
        $before = DB::table('tesis_revisiones')->where($key)->first();
        $before ? DB::table('tesis_revisiones')->where($key)->update(['puntaje' => $data['puntaje'], 'comentario' => $data['comentario'] ?? null, 'actualizado_en' => now()])
            : DB::table('tesis_revisiones')->insert($key + ['puntaje' => $data['puntaje'], 'comentario' => $data['comentario'] ?? null, 'creado_en' => now()]);
        $this->workflow->audit('tesis.revision', 'tesis_envio', $row->id, $before, $data + ['capitulo_id' => $chapter]);
        if (!$before) {
            // El aviso no incluye el puntaje: viaja por servidores externos.
            $this->notify($course->id, [$course->estudiante_id], 'Revisión de tu tesis', 'Un docente revisó un capítulo de tu entrega. Consulta sus comentarios en SGPI.');
        }
        $chapters = $this->thesis->chapters($course->id);
        if (ThesisTrackingService::allApproved($chapters)) {
            $managers = DB::table('tesis_revisores')->where('curso_id', $course->id)->where('rol', 'director')->where('activo', true)->pluck('docente_id');
            $this->notify($course->id, $managers, 'Tesis lista para liberar', "Todos los capítulos de «{$course->titulo}» están aprobados.");
        }

        return response()->json(['message' => 'Revisión guardada.']);
    }

    public function annotate(Request $request, int $envio)
    {
        [$row, $course] = $this->reviewable($envio);
        $data = $request->validate([
            'pagina' => 'required|integer|min:1|max:2000', 'texto' => 'required|string|max:2000',
            'capitulo_id' => ['nullable', 'integer', Rule::exists('tesis_envio_capitulos', 'capitulo_id')->where('envio_id', $row->id)],
        ]);
        DB::table('tesis_anotaciones')->insert(['envio_id' => $row->id, 'docente_id' => $this->me(), 'pagina' => $data['pagina'], 'texto' => $data['texto'],
            'capitulo_id' => $data['capitulo_id'] ?? null, 'creado_en' => now()]);

        return response()->json(['message' => 'Nota agregada.'], 201);
    }

    public function removeAnnotation(int $id)
    {
        $note = DB::table('tesis_anotaciones')->where('id', $id)->first();
        abort_unless($note && (string) $note->docente_id === $this->me(), 404);
        DB::table('tesis_anotaciones')->where('id', $id)->delete();

        return response()->json(['message' => 'Nota eliminada.']);
    }

    // ------------------------------------------------------------------ Apoyo

    private function reviewable(int $envio): array
    {
        $row = DB::table('tesis_envios')->where('id', $envio)->first();
        abort_unless($row, 404);
        $course = $this->course((int) $row->curso_id);
        abort_unless($this->thesis->isReviewer($course->id, $this->me()), 403, 'No eres revisor de esta tesis.');
        abort_unless($course->estado === 'activo', 422, 'El curso no está activo.');

        return [$row, $course];
    }

    private function reviewerRules(): array
    {
        return [
            'revisores.*.docente_id' => ['required', 'string', 'distinct', Rule::exists('usuarios', 'id')->where('activo', true)],
            'revisores.*.rol' => ['required', Rule::in(self::ROLES)],
        ];
    }

    private function validateCourse(Request $request, bool $creating): array
    {
        return $request->validate(($creating ? [
            'proyecto_id' => 'required|integer|exists:proyectos,id',
            'estudiante_id' => 'required|string|max:20',
            'revisores' => 'required|array|min:1|max:15',
        ] + $this->reviewerRules() : []) + [
            'titulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:3000',
            'capitulos' => 'required|array|min:1|max:30',
            'capitulos.*.id' => 'nullable|integer',
            'capitulos.*.titulo' => 'required|string|max:200',
            'plan' => 'sometimes|array|max:30',
            'plan.*.id' => 'nullable|integer',
            'plan.*.titulo' => 'required|string|max:150',
            'plan.*.fecha_limite' => 'nullable|date',
            'plan.*.capitulos' => 'nullable|array',
            'plan.*.capitulos.*' => 'integer|min:1|max:30',
        ]);
    }

    private function saveReviewers(int $courseId, array $reviewers): void
    {
        $career = $this->career();
        foreach ($reviewers as $reviewer) {
            abort_unless(DB::table('usuario_carrera')->where('usuario_id', $reviewer['docente_id'])->where('carrera_id', $career)->where('activo', true)
                ->whereIn('perfil_id', [1, 2, 5, 6, 7])->exists(), 422, 'Los revisores deben ser docentes activos de la carrera.');
            DB::table('tesis_revisores')->updateOrInsert(['curso_id' => $courseId, 'docente_id' => $reviewer['docente_id']],
                ['rol' => $reviewer['rol'], 'activo' => true, 'agregado_en' => now()]);
        }
    }

    private function savePlan(int $courseId, array $plan, int $chapters): void
    {
        foreach (array_values($plan) as $i => $item) {
            $values = ['numero' => $i + 1, 'titulo' => $item['titulo'], 'fecha_limite' => !empty($item['fecha_limite']) ? Carbon::parse($item['fecha_limite']) : null,
                'capitulos' => json_encode(array_values(array_filter(array_unique(array_map('intval', $item['capitulos'] ?? [])), fn ($n) => $n >= 1 && $n <= $chapters)))];
            if (!empty($item['id']) && DB::table('tesis_plan_entregas')->where('id', $item['id'])->where('curso_id', $courseId)->exists()) DB::table('tesis_plan_entregas')->where('id', $item['id'])->update($values);
            else DB::table('tesis_plan_entregas')->insert($values + ['curso_id' => $courseId]);
        }
    }

    private function notify(int $courseId, iterable $users, string $title, string $message): void
    {
        ActivityNotificationService::send($users, $this->me(), 'tesis', $title, $message, '/thesis?course='.$courseId);
    }
}
