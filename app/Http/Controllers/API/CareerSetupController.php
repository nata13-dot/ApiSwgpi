<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod as AcademicPeriodModel;
use App\Models\Asignatura;
use App\Models\SubjectGroup;
use App\Support\AcademicPeriod;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareerSetupController extends Controller
{
    public function importCatalog(Request $request)
    {
        $validated = $request->validate([
            'subjects' => 'required|array|min:1|max:500',
            'subjects.*.code' => 'required|string|max:50',
            'subjects.*.name' => 'required|string|max:255',
            'subjects.*.description' => 'nullable|string|max:2000',
            'groups' => 'present|array|max:100',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.semester' => 'required|integer|in:5,6,7,8,9',
            'groups.*.group' => 'required|string|max:20',
            'groups.*.period' => ['required', 'string', 'regex:/^20\d{2}-[12]$/'],
            'groups.*.subject_codes' => 'required|array|min:1',
            'groups.*.subject_codes.*' => 'required|string|max:50',
        ]);

        $subjects = collect($validated['subjects'])->map(fn ($subject) => [
            'code' => strtoupper(trim($subject['code'])),
            'name' => trim($subject['name']),
            'description' => trim((string) ($subject['description'] ?? '')) ?: null,
        ]);
        $duplicateCodes = $subjects->groupBy('code')->filter(fn ($items) => $items->count() > 1)->keys();
        if ($duplicateCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'subjects' => ['Hay claves de asignatura duplicadas: '.$duplicateCodes->join(', ')],
            ]);
        }

        foreach ($validated['groups'] as $index => $group) {
            $period = AcademicPeriod::normalize($group['period']);
            if (!in_array((int) $group['semester'], AcademicPeriod::semesters($period), true)) {
                throw ValidationException::withMessages([
                    "groups.{$index}.semester" => ["El semestre {$group['semester']} no corresponde al periodo {$period}."],
                ]);
            }
        }

        $careerId = app(CareerContext::class)->careerId();
        $result = DB::transaction(function () use ($careerId, $subjects, $validated) {
            $createdSubjects = 0;
            $updatedSubjects = 0;
            $subjectIds = [];

            foreach ($subjects as $subject) {
                $model = Asignatura::query()->where('clave', $subject['code'])->first();
                $createdSubjects += $model ? 0 : 1;
                $updatedSubjects += $model ? 1 : 0;
                $model = Asignatura::updateOrCreate(
                    ['carrera_id' => $careerId, 'clave' => $subject['code']],
                    ['nombre' => $subject['name'], 'descripcion' => $subject['description'], 'activo' => true]
                );
                $subjectIds[$subject['code']] = $model->id;
            }

            $createdGroups = 0;
            $updatedGroups = 0;
            foreach ($validated['groups'] as $index => $group) {
                $codes = collect($group['subject_codes'])
                    ->map(fn ($code) => strtoupper(trim($code)))
                    ->unique()
                    ->values();
                $missing = $codes->filter(fn ($code) => !isset($subjectIds[$code]));
                if ($missing->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "groups.{$index}.subject_codes" => ['No se encontraron estas asignaturas en el archivo: '.$missing->join(', ')],
                    ]);
                }

                $periodCode = AcademicPeriod::normalize($group['period']);
                $period = AcademicPeriodModel::firstOrCreate(['nombre' => $periodCode], ['activo' => false]);
                $groupCode = strtoupper(trim($group['group']));
                $model = SubjectGroup::query()
                    ->where('periodo_id', $period->id)
                    ->where('semestre', $group['semester'])
                    ->where('grupo', $groupCode)
                    ->first();
                $createdGroups += $model ? 0 : 1;
                $updatedGroups += $model ? 1 : 0;
                $model = SubjectGroup::updateOrCreate(
                    [
                        'carrera_id' => $careerId,
                        'periodo_id' => $period->id,
                        'semestre' => $group['semester'],
                        'grupo' => $groupCode,
                    ],
                    ['nombre' => trim($group['name']), 'activo' => true]
                );
                $model->asignaturas()->sync(
                    $codes->mapWithKeys(fn ($code) => [$subjectIds[$code] => ['carrera_id' => $careerId]])->all()
                );
            }

            return compact('createdSubjects', 'updatedSubjects', 'createdGroups', 'updatedGroups');
        });

        return response()->json([
            'message' => 'Catálogo académico cargado correctamente.',
            'summary' => [
                'subjects_created' => $result['createdSubjects'],
                'subjects_updated' => $result['updatedSubjects'],
                'groups_created' => $result['createdGroups'],
                'groups_updated' => $result['updatedGroups'],
            ],
        ]);
    }
}
