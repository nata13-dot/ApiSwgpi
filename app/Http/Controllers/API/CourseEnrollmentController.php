<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CourseEnrollmentController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        $coursesQuery = Course::with(['subject:id,clave,nombre', 'group:id,nombre,semestre,grupo,periodo_id'])
            ->where('activo', true)
            ->whereHas('group', fn ($query) => $query->where('activo', true));
        if ($user->isStudent()) $coursesQuery->where('es_seguimiento_proyecto', true);
        if ($user->isTeacher()) {
            $coursesQuery->whereExists(fn ($query) => $query->selectRaw('1')->from('curso_docentes')
                ->whereColumn('curso_docentes.curso_id', 'cursos.id')->where('curso_docentes.docente_id', $user->id)->where('curso_docentes.activo', true));
        }
        $courses = $coursesQuery->orderBy('grupo_id')->get();

        $enrolled = DB::table('curso_estudiantes')->where('estudiante_id', $user->id)
            ->where('activo', true)->pluck('curso_id')->map(fn ($id) => (int) $id)->all();

        return response()->json(['data' => $courses->map(function (Course $course) use ($enrolled) {
            $course->setAttribute('inscrito', in_array((int) $course->id, $enrolled, true));
            $course->setAttribute('clave_configurada', !empty($course->getRawOriginal('clave_autoregistro')));
            return $course;
        })]);
    }

    public function enroll(Request $request, Course $course)
    {
        $validated = $request->validate(['password' => 'required|string|max:100']);
        $user = auth('api')->user();
        if (!$course->activo || !$course->es_seguimiento_proyecto) {
            return response()->json(['message' => 'La materia no admite autoregistro.'], 422);
        }
        $hash = $course->getRawOriginal('clave_autoregistro');
        if (!$hash || !Hash::check($validated['password'], $hash)) {
            throw ValidationException::withMessages(['password' => ['La contraseña de autoregistro no es correcta.']]);
        }
        DB::table('curso_estudiantes')->updateOrInsert(
            ['curso_id' => $course->id, 'estudiante_id' => $user->id],
            ['inscrito_en' => now(), 'activo' => true]
        );
        return response()->json(['message' => 'Te inscribiste correctamente a la materia de seguimiento.']);
    }

    public function updateAccess(Request $request, Course $course)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'password' => 'nullable|required_if:enabled,true|string|min:6|max:100|confirmed',
        ]);
        $user = auth('api')->user();
        $assigned = DB::table('curso_docentes')->where('curso_id', $course->id)
            ->where('docente_id', $user->id)->where('activo', true)->exists();
        if (!$assigned && !$user->canManageAcademics()) abort(403, 'No impartes este curso.');

        $course->es_seguimiento_proyecto = $validated['enabled'];
        if (!empty($validated['password'])) $course->clave_autoregistro = Hash::make($validated['password']);
        if (!$validated['enabled']) $course->clave_autoregistro = null;
        $course->clave_actualizada_en = now();
        $course->clave_actualizada_por = $user->id;
        $course->save();
        return response()->json(['message' => 'Acceso de autoregistro actualizado.']);
    }
}
