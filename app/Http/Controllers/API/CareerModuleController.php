<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerIndicator;
use App\Models\CareerModule;
use App\Models\CareerModuleRecord;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CareerModuleController extends Controller
{
    public function __construct(private readonly CareerContext $careerContext)
    {
    }

    public function index()
    {
        $modules = CareerModule::query()
            ->orderByDesc('habilitado')
            ->orderBy('modulo')
            ->get();
        $counts = CareerModuleRecord::query()
            ->where('activo', true)
            ->selectRaw('modulo, COUNT(*) total')
            ->groupBy('modulo')
            ->pluck('total', 'modulo');

        return response()->json([
            'career' => $this->careerContext->career(),
            'modules' => $modules->map(function (CareerModule $module) use ($counts) {
                return array_merge($module->toArray(), [
                    'label' => $module->configuracion['label'] ?? str($module->modulo)->replace('_', ' ')->title()->toString(),
                    'icon' => $module->configuracion['icon'] ?? 'bi-grid',
                    'records_count' => (int) ($counts[$module->modulo] ?? 0),
                ]);
            }),
            'indicators' => CareerIndicator::query()->where('activo', true)->orderBy('id')->get(),
        ]);
    }

    public function records(Request $request)
    {
        $validated = $request->validate([
            'modulo' => 'nullable|string|max:80',
            'estado' => 'nullable|string|max:40',
        ]);

        $query = CareerModuleRecord::with('responsible:id,nombres,apellido_paterno,apellido_materno')
            ->when($validated['modulo'] ?? null, fn ($builder, $module) => $builder->where('modulo', $module))
            ->when($validated['estado'] ?? null, fn ($builder, $status) => $builder->where('estado', $status))
            ->orderByDesc('activo')
            ->orderBy('titulo');

        return response()->json($query->paginate(25));
    }

    public function storeRecord(Request $request)
    {
        $validated = $this->validateRecord($request);
        $this->guardEnabledModule($validated['modulo']);
        $record = CareerModuleRecord::create($validated);

        return response()->json(['record' => $record->load('responsible')], 201);
    }

    public function updateRecord(Request $request, CareerModuleRecord $record)
    {
        $this->guardOwnedByActiveCareer($record);
        $validated = $this->validateRecord($request, $record);
        $this->guardEnabledModule($validated['modulo'] ?? $record->modulo);
        $record->update($validated);

        return response()->json(['record' => $record->fresh()->load('responsible')]);
    }

    public function destroyRecord(CareerModuleRecord $record)
    {
        $this->guardOwnedByActiveCareer($record);
        $record->update(['activo' => false, 'estado' => 'inactivo']);

        return response()->json(['message' => 'Registro desactivado correctamente.']);
    }

    public function updateModule(Request $request, CareerModule $module)
    {
        $this->guardOwnedByActiveCareer($module);
        $validated = $request->validate([
            'habilitado' => 'required|boolean',
            'configuracion' => 'nullable|array',
        ]);
        if (in_array($module->modulo, ['evaluaciones', 'entregables'], true) && !$validated['habilitado']) {
            throw ValidationException::withMessages([
                'habilitado' => ['Evaluaciones y entregables son funciones compartidas obligatorias para todas las carreras.'],
            ]);
        }
        $module->update($validated);

        return response()->json(['module' => $module->fresh()]);
    }

    public function storeIndicator(Request $request)
    {
        $validated = $this->validateIndicator($request);
        $indicator = CareerIndicator::create($validated);

        return response()->json(['indicator' => $indicator], 201);
    }

    public function updateIndicator(Request $request, CareerIndicator $indicator)
    {
        $this->guardOwnedByActiveCareer($indicator);
        $validated = $this->validateIndicator($request, $indicator);
        $indicator->update($validated);

        return response()->json(['indicator' => $indicator->fresh()]);
    }

    public function destroyIndicator(CareerIndicator $indicator)
    {
        $this->guardOwnedByActiveCareer($indicator);
        $indicator->update(['activo' => false]);

        return response()->json(['message' => 'Indicador desactivado correctamente.']);
    }

    private function validateRecord(Request $request, ?CareerModuleRecord $record = null): array
    {
        return $request->validate([
            'modulo' => 'sometimes|required|string|max:80',
            'clave' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('registros_modulo_carrera', 'clave')
                    ->where('carrera_id', $this->careerContext->careerId())
                    ->where('modulo', $request->input('modulo', $record?->modulo))
                    ->ignore($record?->id),
            ],
            'titulo' => 'sometimes|required|string|max:180',
            'descripcion' => 'nullable|string|max:3000',
            'estado' => 'nullable|string|max:40',
            'responsable_id' => 'nullable|string|exists:usuarios,id',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'datos' => 'nullable|array',
            'activo' => 'sometimes|boolean',
        ]);
    }

    private function validateIndicator(Request $request, ?CareerIndicator $indicator = null): array
    {
        return $request->validate([
            'modulo' => 'sometimes|required|string|max:80',
            'clave' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('indicadores_carrera', 'clave')
                    ->where('carrera_id', $this->careerContext->careerId())
                    ->ignore($indicator?->id),
            ],
            'nombre' => 'sometimes|required|string|max:180',
            'descripcion' => 'nullable|string|max:3000',
            'unidad' => 'sometimes|required|string|max:30',
            'valor_actual' => 'nullable|numeric',
            'valor_meta' => 'nullable|numeric',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icono' => 'nullable|string|max:80',
            'activo' => 'sometimes|boolean',
        ]);
    }

    private function guardEnabledModule(string $module): void
    {
        $enabled = CareerModule::where('modulo', $module)->where('habilitado', true)->exists();
        if (!$enabled) {
            throw ValidationException::withMessages([
                'modulo' => ['El módulo no está habilitado para la carrera activa.'],
            ]);
        }
    }

    private function guardOwnedByActiveCareer($model): void
    {
        abort_unless(
            (int) $model->carrera_id === (int) $this->careerContext->careerId(),
            404,
            'Registro no encontrado.'
        );
    }
}
