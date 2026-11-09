<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Services\AppSessionService;
use App\Support\CareerContext;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Archivo histórico: usuarios, proyectos, propuestas y grupos que dejan de verse en el sistema
 * hasta que alguien los restaura. El repositorio público no depende de esto y sigue igual.
 */
class ArchiveController extends Controller
{
    private const TYPES = ['usuarios', 'proyectos', 'propuestas', 'grupos'];

    public function index(Request $request)
    {
        $this->ensureReady();
        $data = $request->validate([
            'tipo' => ['required', Rule::in(self::TYPES)],
            'buscar' => 'nullable|string|max:100',
        ]);
        $type = $data['tipo'];
        $query = $this->archivedQuery($type);
        if (!empty($data['buscar'])) {
            $columns = match ($type) {
                'usuarios' => ['id', 'nombres', 'apellido_paterno', 'apellido_materno'],
                'grupos' => ['nombre', 'clave_grupo'],
                default => ['titulo', 'descripcion'],
            };
            \App\Support\TextSearch::apply($query, $data['buscar'], $columns);
        }

        $page = $query->orderByDesc($query->getModel()->getTable() . '.archivado_en')->paginate(20);
        $reasons = DB::table('archivo_historico')->where('tipo', $type)->where('accion', 'archivado')
            ->whereIn('registro_id', $page->getCollection()->map(fn ($row) => (string) $row->getKey()))
            ->orderBy('id')->get(['registro_id', 'motivo'])->keyBy('registro_id');
        $names = User::withoutGlobalScopes()->whereIn('id', $page->getCollection()->pluck('archivado_por')->filter()->unique())
            ->get(['id', 'nombres', 'apellido_paterno'])->keyBy('id');

        $page->setCollection($page->getCollection()->map(fn ($row) => $this->present($type, $row) + [
            'archived_at' => $row->archivado_en,
            'archived_by' => ($by = $names->get($row->archivado_por)) ? trim("{$by->nombres} {$by->apa}") : null,
            'reason' => $reasons->get((string) $row->getKey())?->motivo,
        ]));

        return response()->json($page);
    }

    public function archive(Request $request)
    {
        return $this->change($request, true);
    }

    public function restore(Request $request)
    {
        return $this->change($request, false);
    }

    private function change(Request $request, bool $archive)
    {
        $this->ensureReady();
        $data = $request->validate([
            'tipo' => ['required', Rule::in(self::TYPES)],
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'required|string|max:30',
            'motivo' => 'nullable|string|max:500',
        ]);
        $type = $data['tipo'];
        $ids = array_values(array_unique($data['ids']));
        $query = $archive ? $this->activeQuery($type) : $this->archivedQuery($type);
        $model = $query->getModel();
        $rows = $query->whereIn($model->getTable() . '.' . $model->getKeyName(), $ids)->get();
        abort_if($rows->count() !== count($ids), 404, $archive
            ? 'Algún registro no existe, ya está archivado o pertenece a otra carrera.'
            : 'Algún registro no está en el archivo histórico de esta carrera.');
        if ($archive) {
            $rows->each(fn ($row) => $this->guardArchivable($type, $row));
        }

        $actor = (string) auth('api')->id();
        $careerId = app(CareerContext::class)->careerId();
        DB::transaction(function () use ($rows, $type, $archive, $actor, $careerId, $data) {
            foreach ($rows as $row) {
                DB::table($row->getTable())->where($row->getKeyName(), $row->getKey())->update([
                    'archivado_en' => $archive ? now() : null,
                    'archivado_por' => $archive ? $actor : null,
                ]);
                DB::table('archivo_historico')->insert([
                    'tipo' => $type,
                    'registro_id' => (string) $row->getKey(),
                    'carrera_id' => $careerId,
                    'accion' => $archive ? 'archivado' : 'restaurado',
                    'motivo' => $data['motivo'] ?? null,
                    'usuario_id' => $actor,
                    'creado_en' => now(),
                ]);
            }
        });
        // Una cuenta archivada no puede seguir usando la app.
        if ($archive && $type === 'usuarios') {
            $rows->each(fn ($user) => app(AppSessionService::class)->revokeAllForUser((string) $user->getKey()));
        }

        $count = $rows->count();
        return response()->json(['message' => $archive
            ? ($count === 1 ? 'Se envió al archivo histórico.' : "Se enviaron {$count} registros al archivo histórico.")
            : ($count === 1 ? 'Se restauró del archivo histórico.' : "Se restauraron {$count} registros del archivo histórico.")]);
    }

    private function guardArchivable(string $type, $row): void
    {
        $actor = auth('api')->user();
        if ($type === 'usuarios') {
            abort_if($row->getKey() === $actor->getKey(), 422, 'No puedes archivar tu propia cuenta.');
            abort_if($row->globalProfileId() === 4, 422, "{$row->nombres} es administrador general: retírale ese nivel antes de archivarlo.");
            // Archivar oculta la cuenta en todas las carreras: la jefatura sólo archiva a quien es únicamente de la suya.
            if ($actor->globalProfileId() !== 4) {
                $elsewhere = $row->careerMemberships()->where('carrera_id', '!=', app(CareerContext::class)->careerId())->where('activo', true)->exists();
                abort_if($elsewhere, 422, "{$row->nombres} también pertenece a otra carrera; sólo la administración general puede archivarlo.");
            }
            return;
        }
        if ($type === 'grupos') {
            $open = DB::table('carrera_periodos')->where('carrera_id', $row->carrera_id)->whereIn('estado', ['abierto', 'en_cierre'])->value('periodo_id');
            abort_if($open && (int) $open === (int) $row->periodo_id, 422, "El grupo {$row->nombre} es del periodo en curso; archívalo cuando el periodo cierre.");
        }
    }

    /** Registros vigentes de la carrera activa, del tipo pedido. */
    private function activeQuery(string $type): Builder
    {
        return match ($type) {
            'usuarios' => User::inCareer(app(CareerContext::class)->careerId()),
            'grupos' => SubjectGroup::query(),
            'propuestas' => $this->onlyProposals(Project::query()),
            default => $this->onlyProjects(Project::query()),
        };
    }

    private function archivedQuery(string $type): Builder
    {
        $careerId = app(CareerContext::class)->careerId();
        return match ($type) {
            // Las membresías de un usuario archivado se conservan: se busca por cualquiera de la carrera.
            'usuarios' => User::archived()->withoutGlobalScope('careerMembership')
                ->whereHas('careerMemberships', fn ($membership) => $membership->where('carrera_id', $careerId)),
            'grupos' => SubjectGroup::archived()->with('academicPeriod:id,nombre'),
            'propuestas' => $this->onlyProposals(Project::archived())->with('subjectGroup:id,nombre'),
            default => $this->onlyProjects(Project::archived())->with('subjectGroup:id,nombre'),
        };
    }

    // Una propuesta aprobada ya es un proyecto: se archiva y se consulta como tal.
    private function onlyProjects(Builder $query): Builder
    {
        return $query->where(fn ($scope) => $scope->where('proyectos.tipo', '!=', 'propuesta')->orWhere('proyectos.estado', 'aprobado'));
    }

    private function onlyProposals(Builder $query): Builder
    {
        return $query->where('proyectos.tipo', 'propuesta')->where('proyectos.estado', '!=', 'aprobado');
    }

    private function present(string $type, $row): array
    {
        return match ($type) {
            'usuarios' => ['id' => $row->id, 'name' => trim("{$row->nombres} {$row->apa} {$row->ama}"), 'detail' => $row->email],
            'grupos' => ['id' => (string) $row->id, 'name' => $row->nombre, 'detail' => $row->academicPeriod?->nombre],
            default => ['id' => (string) $row->id, 'name' => $row->title, 'detail' => trim(($row->subjectGroup?->nombre ?? '') . ($row->es_residencia ? ' · Residencia' : ''), ' ·')],
        };
    }

    private function ensureReady(): void
    {
        abort_unless(SchemaCache::hasTable('archivo_historico'), 503, 'Falta aplicar la migración del archivo histórico en el servidor.');
    }
}
