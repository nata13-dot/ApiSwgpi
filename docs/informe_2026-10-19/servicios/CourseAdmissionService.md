# CourseAdmissionService

Fuente: [app/Services/CourseAdmissionService.php](../../../app/Services/CourseAdmissionService.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php
namespace App\Services;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CourseAdmissionService
{



}
```

## __construct

Visibilidad: `public`. Líneas originales 10–10.

```php
    public function __construct(private AcademicWorkflowService $workflow) {}
```

## check

Visibilidad: `public`. Líneas originales 12–29.

```php
    public function check(Course $course, User $student): void
    {
        abort_unless($student->isStudent() && $student->activo, 403, 'La inscripción corresponde a un alumno activo.');
        $group = $course->group;
        abort_unless($course->activo && $course->es_seguimiento_proyecto && $group?->activo
            && (int) $course->carrera_id === $this->workflow->careerId(), 422, 'Materia no disponible para inscripción.');
        $this->workflow->requireOpen((int) $group->periodo_id);
        abort_unless($course->inscripcion_desde && $course->inscripcion_hasta
            && now()->betweenIncluded($course->inscripcion_desde, $course->inscripcion_hasta), 422, 'La ventana de inscripción está cerrada.');
        $enrollment = $this->workflow->enrollment($student->id, (int) $group->periodo_id);
        abort_unless($enrollment, 422, 'Se requiere matrícula en este periodo.');
        if ((int) $enrollment->semestre !== (int) $group->semestre) {
            abort_unless(DB::table('autorizaciones_academicas')->where('carrera_id', $this->workflow->careerId())
                ->where('periodo_id', $group->periodo_id)->where('estudiante_id', $student->id)->where('curso_id', $course->id)
                ->where('tipo', 'inscripcion_semestre')->where('estado', 'aprobada')->where('vigente_hasta', '>=', now())->exists(),
                422, 'La materia requiere autorización por diferencia de semestre.');
        }
    }
```

## manage

Visibilidad: `public`. Líneas originales 31–36.

```php
    public function manage(Course $course, User $user): void
    {
        abort_unless((int) $course->carrera_id === $this->workflow->careerId(), 404);
        abort_unless($user->canManageAcademics() || DB::table('curso_docentes')->where('curso_id', $course->id)
            ->where('docente_id', $user->id)->where('activo', true)->exists(), 403, 'No impartes esta materia.');
    }
```

## enrolled

Visibilidad: `public`. Líneas originales 38–41.

```php
    public function enrolled(int $course, string $student): bool
    {
        return DB::table('curso_estudiantes')->where('curso_id', $course)->where('estudiante_id', $student)->where('activo', true)->exists();
    }
```
