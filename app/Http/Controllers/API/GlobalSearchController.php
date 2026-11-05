<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use App\Models\CareerModule;
use App\Models\Deliverable;
use App\Models\Empresa;
use App\Models\EvaluationRoom;
use App\Models\Project;
use App\Models\RepositoryDocument;
use App\Models\User;
use App\Support\CareerContext;
use App\Support\TextSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Buscador general: registros que el usuario ya puede consultar en sus pantallas, agrupados por tipo.
 * Cada grupo aplica el mismo alcance que su listado y respeta los módulos de la carrera activa.
 */
class GlobalSearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        if (mb_strlen($term) < 2) {
            return response()->json(['term' => $term, 'groups' => []]);
        }

        $user = auth('api')->user();
        $enabled = CareerModule::query()->where('habilitado', true)->pluck('modulo')->all();
        $groups = [];
        foreach ([
            ['users', 'Personas', 'usuarios', fn () => $this->users($term, $user)],
            ['projects', 'Proyectos y tesis', 'proyectos', fn () => $this->projects($term, $user)],
            ['deliverables', 'Entregables', 'entregables', fn () => $this->deliverables($term, $user)],
            ['rooms', 'Salas de evaluación', 'evaluaciones', fn () => $this->rooms($term, $user)],
            ['documents', 'Repositorio', 'repositorio', fn () => $this->documents($term, $user)],
            ['companies', 'Empresas', 'proyectos', fn () => $this->companies($term, $user)],
            ['subjects', 'Asignaturas', 'academico', fn () => $this->subjects($term, $user)],
        ] as [$type, $label, $module, $search]) {
            if (!in_array($module, $enabled, true)) {
                continue;
            }
            $items = $search();
            if ($items !== []) {
                $groups[] = ['type' => $type, 'label' => $label, 'items' => $items];
            }
        }

        return response()->json(['term' => $term, 'groups' => $groups]);
    }

    private function users(string $term, User $user): array
    {
        // La vista simulada del administrador general no muestra datos personales.
        if (!$user->canManageProjects() || app(CareerContext::class)->simulatedProfile()) {
            return [];
        }

        return TextSearch::apply(User::query()->where('activo', true), $term, ['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'telefono'])
            ->orderBy('apellido_paterno')->orderBy('nombres')->limit(self::PER_GROUP)
            ->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo'])
            ->map(fn (User $person) => $this->item($person->id, $person->getFullName(), trim("{$person->id} · {$person->correo}", ' ·'), $person->id))
            ->all();
    }

    private function projects(string $term, User $user): array
    {
        return TextSearch::apply(Project::query()->visibleInListing($user), $term, ['titulo', 'descripcion'], function ($scope, $pattern) {
            $scope->orWhereHas('empresa', fn ($company) => $company->where('nombre', 'like', $pattern))
                ->orWhereHas('students', fn ($people) => $people->where(fn ($match) => $match
                    ->where('usuarios.id', 'like', $pattern)
                    ->orWhere('usuarios.nombres', 'like', $pattern)
                    ->orWhere('usuarios.apellido_paterno', 'like', $pattern)
                    ->orWhere('usuarios.apellido_materno', 'like', $pattern)));
        })
            ->orderByDesc('creado_en')->limit(self::PER_GROUP)
            ->get(['id', 'titulo', 'tipo', 'modalidad'])
            ->map(fn (Project $project) => $this->item($project->id, $project->titulo, $project->tipo === 'tesis' ? 'Tesis' : 'Proyecto', $project->titulo))
            ->all();
    }

    private function deliverables(string $term, User $user): array
    {
        $query = Deliverable::query()->where('activo', true);
        if ((int) $user->perfil_id === 3) {
            $query->where('estado', 'publicado')->whereIn('curso_id', DB::table('curso_estudiantes')
                ->where('estudiante_id', $user->id)->where('activo', true)->select('curso_id'));
        } elseif (!$user->canManageAcademics()) {
            $query->whereIn('curso_id', DB::table('curso_docentes')
                ->where('docente_id', $user->id)->where('activo', true)->select('curso_id'));
        }

        return TextSearch::apply($query, $term, ['nombre', 'descripcion'])
            ->orderBy('nombre')->limit(self::PER_GROUP)
            ->get(['id', 'nombre', 'fecha_limite'])
            ->map(fn (Deliverable $deliverable) => $this->item(
                $deliverable->id,
                $deliverable->nombre,
                $deliverable->fecha_limite ? 'Fecha límite: '.\Illuminate\Support\Carbon::parse($deliverable->fecha_limite)->format('d/m/Y') : 'Sin fecha límite',
                $deliverable->nombre
            ))
            ->all();
    }

    private function rooms(string $term, User $user): array
    {
        if ((int) $user->perfil_id === 3) {
            return [];
        }
        $query = EvaluationRoom::query();
        if (!$user->canManageProjects()) {
            $query->where(fn ($scope) => $scope
                ->whereHas('teachers', fn ($teachers) => $teachers->where('usuarios.id', $user->id))
                ->orWhere('responsible_teacher_id', $user->id));
        }

        return TextSearch::apply($query, $term, ['nombre', 'salon'])
            ->orderByDesc('fecha_evaluacion')->limit(self::PER_GROUP)->get()
            ->map(fn (EvaluationRoom $room) => $this->item(
                $room->id,
                $room->nombre,
                trim(($room->salon ? "{$room->salon} · " : '').optional($room->fecha_evaluacion)->format('d/m/Y H:i'), ' ·'),
                $room->nombre
            ))
            ->all();
    }

    private function documents(string $term, User $user): array
    {
        $query = RepositoryDocument::query()->where('activo', true)
            ->where('categoria', '!=', RepositoryDocument::CATEGORY_DELIVERABLE);
        // Mismo criterio que RepositoryController::canAccessDocument.
        if (!$user->canManageProjects()) {
            $query->where(function ($scope) use ($user) {
                $scope->where(fn ($public) => $public->where('visibilidad', RepositoryDocument::VISIBILITY_PUBLIC)->whereNotNull('publicado_en'));
                if ((int) $user->perfil_id === 2) {
                    $scope->orWhereNull('proyecto_id')
                        ->orWhereHas('project.advisors', fn ($advisors) => $advisors->where('usuarios.id', $user->id));
                } elseif ((int) $user->perfil_id === 3) {
                    $scope->orWhere('subido_por', $user->id)
                        ->orWhereHas('project.students', fn ($students) => $students->where('usuarios.id', $user->id));
                }
            });
        }

        return TextSearch::apply($query, $term, ['titulo', 'descripcion', 'autor_nombre'], function ($scope, $pattern) {
            $scope->orWhereHas('tags', fn ($tags) => $tags->where('etiquetas.nombre', 'like', $pattern));
        })
            ->orderByDesc('creado_en')->limit(self::PER_GROUP)
            ->get(['id', 'titulo', 'autor_nombre'])
            ->map(fn (RepositoryDocument $document) => $this->item($document->id, $document->titulo, $document->autor_nombre ?: 'Documento', $document->titulo))
            ->all();
    }

    private function companies(string $term, User $user): array
    {
        if (!$user->canManageProjects()) {
            return [];
        }

        return TextSearch::apply(Empresa::query(), $term, ['nombre', 'rfc', 'giro'])
            ->orderBy('nombre')->limit(self::PER_GROUP)
            ->get(['id', 'nombre', 'giro'])
            ->map(fn (Empresa $company) => $this->item($company->id, $company->nombre, $company->giro ?: 'Empresa', $company->nombre))
            ->all();
    }

    private function subjects(string $term, User $user): array
    {
        if (!$user->canManageAcademics()) {
            return [];
        }

        return TextSearch::apply(Asignatura::query(), $term, ['nombre', 'clave'])
            ->orderBy('nombre')->limit(self::PER_GROUP)
            ->get(['id', 'nombre', 'clave'])
            ->map(fn (Asignatura $subject) => $this->item($subject->id, $subject->nombre, $subject->clave ?: 'Asignatura', $subject->nombre))
            ->all();
    }

    /** `filter` es el texto con el que la pantalla de destino localiza el registro. */
    private function item(int|string $id, ?string $title, string $subtitle, ?string $filter): array
    {
        return ['id' => $id, 'title' => $title, 'subtitle' => $subtitle, 'filter' => $filter];
    }
}
