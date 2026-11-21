<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerSetting;
use App\Models\Project;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Services\AcademicWorkflowService;
use App\Services\ActivityNotificationService;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Registro de tesis por los alumnos, igual que el de propuestas: la jefatura abre una ventana para ciertos semestres
 * (9° por defecto); el alumno registra su tesis (sola o con un compañero que acepta la invitación); la valida la jefatura
 * o un docente validador de tesis (aprobar, pedir cambios con plazo o rechazar). Al aprobar se asignan director y
 * revisores y se abre el curso de Seguimiento de tesis de cada integrante.
 */
class ThesisRegistrationController extends Controller
{
    private const CHAPTERS = ['Introducción', 'Marco teórico', 'Metodología', 'Desarrollo', 'Resultados', 'Conclusiones'];

    public function __construct(private AcademicWorkflowService $workflow) {}

    private function me(): User
    {
        return auth('api')->user();
    }

    private function window(): array
    {
        $until = CareerSetting::valueFor('thesis_registration_until');
        $closes = $until ? Carbon::parse($until) : null;
        $semesters = array_values(array_map('intval', (array) CareerSetting::valueFor('thesis_semesters', [9])));

        return ['open' => (bool) $closes?->isFuture(), 'closes_at' => $closes?->toIso8601String(), 'semesters' => $semesters ?: [9],
            'max_members' => max(1, min(3, (int) CareerSetting::valueFor('thesis_max_members', 2)))];
    }

    private function isManager(): bool
    {
        return $this->me()->isGeneralAdmin() || in_array((int) app(CareerContext::class)->profileId(), [1, 5, 6], true);
    }

    private function isValidator(): bool
    {
        return $this->isManager() || in_array((string) $this->me()->id, array_map('strval', (array) CareerSetting::valueFor('thesis_validator_teacher_ids', [])), true);
    }

    /** Tesis vigente del alumno (registrada o aprobada; una rechazada ya no lo ata). */
    private function studentThesis(string $studentId): ?Project
    {
        return Project::where('activo', true)->where('tipo', 'tesis')->where('estado', '!=', 'rechazado')
            ->whereHas('students', fn ($q) => $q->where('usuarios.id', $studentId))->orderByDesc('creado_en')->first();
    }

    // ------------------------------------------------------------------ Alumno

    public function status()
    {
        $student = $this->me();
        abort_unless($student->isStudent(), 403, 'Sólo alumnos.');
        $window = $this->window();
        $enrollment = $this->workflow->enrollment($student->id);
        $group = $enrollment ? SubjectGroup::find($enrollment->grupo_id) : null;
        $thesis = $this->studentThesis((string) $student->id);
        $rejected = $thesis ? null : Project::where('tipo', 'tesis')->where('estado', 'rechazado')
            ->whereHas('students', fn ($q) => $q->where('usuarios.id', $student->id))->orderByDesc('revisado_en')->first();
        $invitations = DB::table('tesis_invitaciones as i')->join('proyectos as p', 'p.id', '=', 'i.proyecto_id')->leftJoin('usuarios as u', 'u.id', '=', 'p.creado_por')
            ->where('i.estudiante_id', $student->id)->where('i.estado', 'pendiente')
            ->get(['i.id', 'p.titulo', 'u.nombres', 'u.apellido_paterno', 'i.creada_en']);
        $eligible = $group && in_array((int) $group->semestre, $window['semesters'], true);

        return response()->json(['data' => [
            'window' => $window,
            'semestre' => $group?->semestre,
            'eligible' => $eligible,
            'can_register' => $window['open'] && $eligible && !$thesis && $invitations->isEmpty(),
            'thesis' => $thesis ? $this->payload($thesis, true) : null,
            'last_rejected' => $rejected ? ['titulo' => $rejected->titulo, 'comentario' => $rejected->comentario_revision] : null,
            'invitations' => $invitations->map(fn ($i) => ['id' => $i->id, 'titulo' => $i->titulo, 'de' => trim("{$i->nombres} {$i->apellido_paterno}")])->values(),
        ]]);
    }

    public function register(Request $request)
    {
        $student = $this->me();
        abort_unless($student->isStudent(), 403, 'Sólo alumnos registran su tesis.');
        $data = $request->validate($this->rules(), [], $this->attributes());
        $group = $this->guardCanRegister($student);
        $this->workflow->requireOpen((int) $group->periodo_id);

        $project = DB::transaction(function () use ($data, $student, $group) {
            $project = Project::create([
                'title' => trim($data['titulo']), 'description' => trim($data['planteamiento']), 'objetivo_general' => trim($data['objetivo_general']),
                'subject_group_id' => $group->id, 'proposal_status' => 'pendiente', 'tipo' => 'tesis', 'modalidad' => 'proyecto_integrador', 'created_by' => $student->id,
            ]);
            $this->saveDetails($project->id, $data, (string) $student->id);
            DB::table('proyecto_integrantes')->insert(['proyecto_id' => $project->id, 'usuario_id' => $student->id, 'rol' => 'lider', 'agregado_en' => now()]);
            $this->workflow->audit('tesis.registrada', 'proyecto', $project->id, null, ['titulo' => $data['titulo']]);

            return $project;
        });
        ActivityNotificationService::send($this->validatorIds(), (string) $student->id, 'propuesta_registrada', 'Tesis por validar',
            "«{$project->titulo}» ({$group->nombre}) espera validación.", '/thesis');

        return response()->json(['message' => 'Tesis registrada. Quedó pendiente de validación.', 'thesis' => $this->payload($project->fresh(), true)], 201);
    }

    /** Corrección mientras siga abierta la ventana de cambios que dio el validador. */
    public function update(Request $request)
    {
        $student = $this->me();
        $project = $this->studentThesis((string) $student->id);
        abort_unless($project, 404, 'No tienes una tesis registrada.');
        abort_unless($project->estado === 'requiere_cambios' && $project->revision_permitida_hasta && now()->lessThan($project->revision_permitida_hasta), 422,
            'Tu tesis no tiene una ventana activa de corrección.');
        $data = $request->validate($this->rules(), [], $this->attributes());
        DB::transaction(function () use ($project, $data, $student) {
            $project->update(['title' => trim($data['titulo']), 'description' => trim($data['planteamiento']), 'objetivo_general' => trim($data['objetivo_general']),
                'proposal_status' => 'pendiente', 'proposal_review_comment' => null, 'proposal_reviewed_by' => null, 'proposal_reviewed_at' => null, 'revision_allowed_until' => null]);
            $this->saveDetails($project->id, $data, (string) $student->id);
        });
        ActivityNotificationService::send($this->validatorIds(), (string) $student->id, 'propuesta_corregida', 'Tesis corregida',
            "«{$project->titulo}» se corrigió y espera validación.", '/thesis');

        return response()->json(['message' => 'Corrección enviada.', 'thesis' => $this->payload($project->fresh(), true)]);
    }

    /** Invita a un compañero (por número de control); debe aceptar la invitación. */
    public function invite(Request $request)
    {
        $student = $this->me();
        $project = $this->studentThesis((string) $student->id);
        abort_unless($project && in_array($project->estado, ['pendiente', 'requiere_cambios'], true), 422, 'Sólo puedes invitar mientras tu tesis está en revisión.');
        $data = $request->validate(['estudiante_id' => 'required|string|max:20']);
        $window = $this->window();
        $members = $project->students()->count() + DB::table('tesis_invitaciones')->where('proyecto_id', $project->id)->where('estado', 'pendiente')->count();
        abort_if($members >= $window['max_members'], 422, "La tesis admite hasta {$window['max_members']} integrante(s).");
        $partner = User::students()->where('activo', true)->find($data['estudiante_id']);
        abort_unless($partner && (string) $partner->id !== (string) $student->id, 422, 'No encontramos a ese alumno en tu carrera.');
        $enrollment = $this->workflow->enrollment($partner->id);
        $group = $enrollment ? SubjectGroup::find($enrollment->grupo_id) : null;
        abort_unless($group && in_array((int) $group->semestre, $window['semesters'], true), 422, 'Tu compañero no está en un semestre habilitado para registrar tesis.');
        abort_if($this->studentThesis((string) $partner->id), 422, 'Tu compañero ya tiene una tesis registrada.');
        abort_if(DB::table('tesis_invitaciones')->where('estudiante_id', $partner->id)->where('estado', 'pendiente')->exists(), 422, 'Tu compañero ya tiene una invitación pendiente.');
        DB::table('tesis_invitaciones')->insert(['proyecto_id' => $project->id, 'estudiante_id' => $partner->id, 'estado' => 'pendiente', 'creada_en' => now()]);
        ActivityNotificationService::send([$partner->id], (string) $student->id, 'propuesta_registrada', 'Invitación a una tesis',
            "{$student->nombres} te invitó a su tesis «{$project->titulo}».", '/thesis');

        return response()->json(['message' => 'Invitación enviada. Tu compañero debe aceptarla.'], 201);
    }

    public function answerInvitation(Request $request, int $id)
    {
        $student = $this->me();
        $data = $request->validate(['decision' => 'required|in:aceptada,rechazada']);
        $invitation = DB::table('tesis_invitaciones')->where('id', $id)->where('estudiante_id', $student->id)->where('estado', 'pendiente')->first();
        abort_unless($invitation, 404, 'La invitación ya no está disponible.');
        if ($data['decision'] === 'aceptada') {
            abort_if($this->studentThesis((string) $student->id), 422, 'Ya tienes una tesis registrada.');
            $project = Project::findOrFail($invitation->proyecto_id);
            abort_unless(in_array($project->estado, ['pendiente', 'requiere_cambios'], true), 422, 'Esa tesis ya fue revisada.');
            DB::transaction(function () use ($invitation, $student) {
                DB::table('proyecto_integrantes')->insert(['proyecto_id' => $invitation->proyecto_id, 'usuario_id' => $student->id, 'rol' => 'integrante', 'agregado_en' => now()]);
                DB::table('tesis_invitaciones')->where('id', $invitation->id)->update(['estado' => 'aceptada', 'respondida_en' => now()]);
                DB::table('tesis_invitaciones')->where('estudiante_id', $student->id)->where('estado', 'pendiente')->update(['estado' => 'cancelada', 'respondida_en' => now()]);
            });
        } else {
            DB::table('tesis_invitaciones')->where('id', $invitation->id)->update(['estado' => 'rechazada', 'respondida_en' => now()]);
        }
        ActivityNotificationService::send(DB::table('proyecto_integrantes')->where('proyecto_id', $invitation->proyecto_id)->where('usuario_id', '!=', $student->id)->pluck('usuario_id'),
            (string) $student->id, 'propuesta_registrada', $data['decision'] === 'aceptada' ? 'Invitación aceptada' : 'Invitación rechazada',
            "{$student->nombres} ".($data['decision'] === 'aceptada' ? 'se unió a tu tesis.' : 'no aceptó la invitación a tu tesis.'), '/thesis');

        return response()->json(['message' => $data['decision'] === 'aceptada' ? 'Te uniste a la tesis.' : 'Invitación rechazada.']);
    }

    public function cancelInvitation(int $id)
    {
        $student = $this->me();
        $invitation = DB::table('tesis_invitaciones as i')->join('proyecto_integrantes as m', 'm.proyecto_id', '=', 'i.proyecto_id')
            ->where('i.id', $id)->where('i.estado', 'pendiente')->where('m.usuario_id', $student->id)->first(['i.id']);
        abort_unless($invitation, 404, 'La invitación ya no está pendiente.');
        DB::table('tesis_invitaciones')->where('id', $id)->update(['estado' => 'cancelada', 'respondida_en' => now()]);

        return response()->json(['message' => 'Invitación cancelada.']);
    }

    // ------------------------------------------------------------------ Validación

    public function index(Request $request)
    {
        abort_unless($this->isValidator(), 403, 'No validas registros de tesis.');
        $status = $request->query('status');
        $projects = Project::with(['students:id,nombres,apellido_paterno,apellido_materno', 'subjectGroup', 'objectives', 'proposalReviewer:id,nombres,apellido_paterno'])
            ->where('tipo', 'tesis')->where('activo', true)
            ->whereIn('id', DB::table('tesis_registros')->select('proyecto_id'))
            ->when($status, fn ($q) => $q->where('estado', $status))
            ->orderByRaw("FIELD(estado, 'pendiente', 'requiere_cambios', 'aprobado', 'rechazado')")->orderByDesc('creado_en')->get();

        return response()->json(['data' => $projects->map(fn ($p) => $this->payload($p))->values(), 'teachers' => $this->teachers(), 'manager' => $this->isManager()]);
    }

    public function review(Request $request, int $id)
    {
        abort_unless($this->isValidator(), 403, 'No validas registros de tesis.');
        $project = Project::where('tipo', 'tesis')->whereIn('id', DB::table('tesis_registros')->select('proyecto_id'))->findOrFail($id);
        abort_unless(in_array($project->estado, ['pendiente', 'requiere_cambios'], true), 422, 'Esta tesis ya fue resuelta.');
        $data = $request->validate([
            'decision' => 'required|in:aprobado,requiere_cambios,rechazado',
            'comentario' => 'nullable|required_unless:decision,aprobado|string|max:3000',
            'revision_allowed_until' => 'nullable|required_if:decision,requiere_cambios|date|after:now',
            'director_id' => ['nullable', 'required_if:decision,aprobado', 'string', Rule::in($this->teachers()->pluck('id')->all())],
            'revisores' => 'nullable|array|max:4',
            'revisores.*' => ['string', 'distinct', Rule::in($this->teachers()->pluck('id')->all())],
        ], [], ['comentario' => 'observaciones', 'revision_allowed_until' => 'fecha límite de corrección', 'director_id' => 'director']);
        $reviewers = array_values(array_diff($data['revisores'] ?? [], [$data['director_id'] ?? '']));

        DB::transaction(function () use ($project, $data, $reviewers) {
            $project->update([
                'proposal_status' => $data['decision'], 'proposal_review_comment' => $data['comentario'] ?? null,
                'revision_allowed_until' => $data['decision'] === 'requiere_cambios' ? $data['revision_allowed_until'] : null,
                'proposal_reviewed_by' => $this->me()->id, 'proposal_reviewed_at' => now(),
            ]);
            if ($data['decision'] === 'rechazado') {
                DB::table('tesis_invitaciones')->where('proyecto_id', $project->id)->where('estado', 'pendiente')->update(['estado' => 'cancelada', 'respondida_en' => now()]);
            }
            if ($data['decision'] === 'aprobado') $this->openDevelopment($project, $data['director_id'], $reviewers);
            $this->workflow->audit('tesis.revisada', 'proyecto', $project->id, null, $data);
        });

        $labels = ['aprobado' => 'Tesis aprobada: inicia tu seguimiento', 'requiere_cambios' => 'Tu tesis requiere cambios', 'rechazado' => 'Tesis rechazada'];
        ActivityNotificationService::send($project->students()->pluck('usuarios.id'), (string) $this->me()->id, 'propuesta_revisada', $labels[$data['decision']], $project->titulo, '/thesis');
        if ($data['decision'] === 'aprobado') {
            ActivityNotificationService::send(array_merge([$data['director_id']], $reviewers), (string) $this->me()->id, 'tesis', 'Tesis asignada',
                "Te asignaron a la tesis «{$project->titulo}». Ya puedes dar seguimiento a sus capítulos.", '/thesis');
        }

        return response()->json(['message' => $data['decision'] === 'aprobado' ? 'Tesis aprobada: se asignaron director y revisores y se abrió su seguimiento.' : 'Revisión registrada.']);
    }

    /** Director (asesor) y revisores en la tesis, y un curso de Seguimiento de tesis por integrante con los capítulos estándar. */
    private function openDevelopment(Project $project, string $director, array $reviewers): void
    {
        $career = $this->workflow->careerId();
        DB::table('proyecto_integrantes')->where('proyecto_id', $project->id)->whereIn('rol', ['asesor', 'revisor_1', 'revisor_2'])->delete();
        DB::table('proyecto_integrantes')->insert(['proyecto_id' => $project->id, 'usuario_id' => $director, 'rol' => 'asesor', 'agregado_en' => now()]);
        foreach (array_slice($reviewers, 0, 2) as $i => $reviewer) {
            DB::table('proyecto_integrantes')->insert(['proyecto_id' => $project->id, 'usuario_id' => $reviewer, 'rol' => 'revisor_'.($i + 1), 'agregado_en' => now()]);
        }
        if (!\App\Support\SchemaCache::hasTable('tesis_cursos')) return;
        foreach ($project->students()->pluck('usuarios.id') as $student) {
            if (DB::table('tesis_cursos')->where('proyecto_id', $project->id)->where('estudiante_id', $student)->exists()) continue;
            $course = DB::table('tesis_cursos')->insertGetId(['carrera_id' => $career, 'proyecto_id' => $project->id, 'estudiante_id' => $student,
                'titulo' => $project->titulo, 'descripcion' => $project->descripcion, 'creado_por' => $this->me()->id, 'creado_en' => now(), 'actualizado_en' => now()]);
            foreach (self::CHAPTERS as $i => $title) DB::table('tesis_capitulos')->insert(['curso_id' => $course, 'numero' => $i + 1, 'titulo' => $title]);
            DB::table('tesis_revisores')->insert(['curso_id' => $course, 'docente_id' => $director, 'rol' => 'director', 'activo' => true, 'agregado_en' => now()]);
            foreach ($reviewers as $reviewer) DB::table('tesis_revisores')->insert(['curso_id' => $course, 'docente_id' => $reviewer, 'rol' => 'revisor', 'activo' => true, 'agregado_en' => now()]);
        }
    }

    // ------------------------------------------------------------------ Configuración (jefatura)

    public function settings()
    {
        abort_unless($this->isValidator(), 403);

        return response()->json(['data' => $this->window() + [
            'until' => CareerSetting::valueFor('thesis_registration_until'),
            'validators' => array_values(array_map('strval', (array) CareerSetting::valueFor('thesis_validator_teacher_ids', []))),
            'teachers' => $this->teachers(), 'manager' => $this->isManager(),
        ]]);
    }

    public function updateSettings(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Sólo jefatura configura el registro de tesis.');
        $data = $request->validate([
            'until' => 'nullable|date|after:now',
            'semesters' => 'required|array|min:1', 'semesters.*' => 'integer|min:1|max:12',
            'max_members' => 'required|integer|min:1|max:3',
            'validators' => 'nullable|array', 'validators.*' => ['string', Rule::in($this->teachers()->pluck('id')->all())],
        ], [], ['until' => 'cierre', 'semesters' => 'semestres', 'max_members' => 'integrantes']);
        CareerSetting::setValue('thesis_registration_until', $data['until'] ?? null, 'string', 'Cierre del registro de tesis');
        CareerSetting::setValue('thesis_semesters', array_values(array_unique(array_map('intval', $data['semesters']))), 'array', 'Semestres que registran tesis');
        CareerSetting::setValue('thesis_max_members', (int) $data['max_members'], 'integer', 'Integrantes por tesis');
        CareerSetting::setValue('thesis_validator_teacher_ids', array_values($data['validators'] ?? []), 'array', 'Docentes que validan tesis');
        $this->workflow->audit('tesis.registro_configurado', 'carrera', $this->workflow->careerId(), null, $data);

        return response()->json(['message' => !empty($data['until']) ? 'Registro de tesis abierto.' : 'Registro de tesis cerrado.']);
    }

    // ------------------------------------------------------------------ Apoyo

    private function guardCanRegister(User $student): SubjectGroup
    {
        $window = $this->window();
        abort_unless($window['open'], 422, 'El registro de tesis no está abierto.');
        $enrollment = $this->workflow->enrollment($student->id);
        $group = $enrollment ? SubjectGroup::find($enrollment->grupo_id) : null;
        abort_unless($group, 422, 'Necesitas estar inscrito en un grupo del periodo abierto.');
        abort_unless(in_array((int) $group->semestre, $window['semesters'], true), 422,
            'El registro de tesis está abierto para '.implode(', ', array_map(fn ($s) => "{$s}°", $window['semesters']))." semestre; tú estás en {$group->semestre}°.");
        abort_if($this->studentThesis((string) $student->id), 422, 'Ya tienes una tesis registrada.');
        abort_if(DB::table('tesis_invitaciones')->where('estudiante_id', $student->id)->where('estado', 'pendiente')->exists(), 422,
            'Tienes una invitación a una tesis pendiente; acéptala o recházala primero.');

        return $group;
    }

    private function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255', 'planteamiento' => 'required|string|max:5000', 'objetivo_general' => 'required|string|max:3000',
            'objetivos' => 'required|array|min:1|max:12', 'objetivos.*' => 'required|string|max:1000',
            'linea_investigacion' => 'required|string|max:255', 'justificacion' => 'nullable|string|max:3000',
        ];
    }

    private function attributes(): array
    {
        return ['titulo' => 'título', 'planteamiento' => 'planteamiento del problema', 'objetivo_general' => 'objetivo general',
            'objetivos' => 'objetivos específicos', 'objetivos.*' => 'objetivo específico', 'linea_investigacion' => 'línea de investigación', 'justificacion' => 'justificación'];
    }

    private function saveDetails(int $projectId, array $data, string $by): void
    {
        DB::table('tesis_registros')->updateOrInsert(['proyecto_id' => $projectId], ['linea_investigacion' => trim($data['linea_investigacion']),
            'justificacion' => trim((string) ($data['justificacion'] ?? '')) ?: null, 'registrada_por' => $by, 'actualizado_en' => now()]);
        DB::table('proyecto_objetivos')->where('proyecto_id', $projectId)->delete();
        $rows = collect($data['objetivos'])->map(fn ($t) => trim((string) $t))->filter()->values()->map(fn ($t, $i) => ['proyecto_id' => $projectId, 'orden' => $i + 1, 'descripcion' => $t]);
        DB::table('proyecto_objetivos')->insert($rows->all());
    }

    private function payload(Project $project, bool $forMember = false): array
    {
        $project->loadMissing(['students:id,nombres,apellido_paterno,apellido_materno', 'subjectGroup', 'objectives', 'proposalReviewer:id,nombres,apellido_paterno']);
        $details = DB::table('tesis_registros')->where('proyecto_id', $project->id)->first();
        $staff = DB::table('proyecto_integrantes as i')->join('usuarios as u', 'u.id', '=', 'i.usuario_id')->where('i.proyecto_id', $project->id)
            ->whereIn('i.rol', ['asesor', 'revisor_1', 'revisor_2'])->get(['i.rol', 'u.id', 'u.nombres', 'u.apellido_paterno']);

        return [
            'id' => $project->id, 'titulo' => $project->titulo, 'planteamiento' => $project->descripcion, 'objetivo_general' => $project->objetivo_general,
            'objetivos' => $project->objectives->pluck('descripcion')->values(),
            'linea_investigacion' => $details?->linea_investigacion, 'justificacion' => $details?->justificacion,
            'estado' => $project->estado, 'comentario' => $project->comentario_revision,
            'revision_permitida_hasta' => $project->revision_permitida_hasta ? Carbon::parse($project->revision_permitida_hasta)->toIso8601String() : null,
            'revisado_por' => $project->proposalReviewer ? trim("{$project->proposalReviewer->nombres} {$project->proposalReviewer->apellido_paterno}") : null,
            'creado_en' => $project->creado_en ? Carbon::parse($project->creado_en)->toIso8601String() : null,
            'grupo' => $project->subjectGroup ? "{$project->subjectGroup->semestre}° {$project->subjectGroup->clave_grupo}" : null,
            'integrantes' => $project->students->map(fn ($s) => ['id' => (string) $s->id, 'nombre' => trim("{$s->nombres} {$s->apellido_paterno} {$s->apellido_materno}")])->values(),
            'director' => ($d = $staff->firstWhere('rol', 'asesor')) ? ['id' => (string) $d->id, 'nombre' => trim("{$d->nombres} {$d->apellido_paterno}")] : null,
            'revisores' => $staff->whereIn('rol', ['revisor_1', 'revisor_2'])->map(fn ($r) => ['id' => (string) $r->id, 'nombre' => trim("{$r->nombres} {$r->apellido_paterno}")])->values(),
            'invitaciones' => $forMember ? DB::table('tesis_invitaciones as i')->join('usuarios as u', 'u.id', '=', 'i.estudiante_id')
                ->where('i.proyecto_id', $project->id)->where('i.estado', 'pendiente')->get(['i.id', 'u.id as estudiante_id', 'u.nombres', 'u.apellido_paterno'])
                ->map(fn ($i) => ['id' => $i->id, 'nombre' => trim("{$i->nombres} {$i->apellido_paterno}"), 'estudiante_id' => (string) $i->estudiante_id])->values() : [],
            'curso_id' => \App\Support\SchemaCache::hasTable('tesis_cursos') ? DB::table('tesis_cursos')->where('proyecto_id', $project->id)
                ->when($forMember, fn ($q) => $q->where('estudiante_id', $this->me()->id))->value('id') : null,
        ];
    }

    private function teachers()
    {
        return DB::table('usuario_carrera as uc')->join('usuarios as u', 'u.id', '=', 'uc.usuario_id')->where('uc.carrera_id', $this->workflow->careerId())
            ->where('uc.activo', true)->where('u.activo', true)->whereIn('uc.perfil_id', [1, 2, 5, 6, 7])->orderBy('u.apellido_paterno')
            ->get(['u.id', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno'])->unique('id')
            ->map(fn ($u) => ['id' => (string) $u->id, 'nombre' => trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}")])->values();
    }

    private function validatorIds(): array
    {
        return User::withCareerProfiles([5, 6])->where('activo', true)->pluck('id')
            ->merge((array) CareerSetting::valueFor('thesis_validator_teacher_ids', []))->map(fn ($id) => (string) $id)->unique()->values()->all();
    }
}
