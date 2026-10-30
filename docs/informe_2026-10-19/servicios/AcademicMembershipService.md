# AcademicMembershipService

Fuente: [app/Services/AcademicMembershipService.php](../../../app/Services/AcademicMembershipService.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php
namespace App\Services;

use App\Models\UserCareer;
use Illuminate\Support\Facades\DB;

class AcademicMembershipService
{

}
```

## commitments

Visibilidad: `public`. Líneas originales 9–20.

```php
    public function commitments(string $user, int $career): array
    {
        return [
            'courses' => DB::table('curso_docentes as cd')->join('cursos as c', 'c.id', '=', 'cd.curso_id')
                ->where('c.carrera_id', $career)->where('cd.docente_id', $user)->where('cd.activo', true)->where('c.activo', true)->pluck('c.id')->all(),
            'rooms' => DB::table('sala_docentes as sd')->join('salas_evaluacion as s', 's.id', '=', 'sd.sala_id')
                ->where('s.carrera_id', $career)->where('sd.docente_id', $user)->where('s.estado', '!=', 'finalizada')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->pluck('s.id')->all(),
            'projects' => DB::table('proyecto_integrantes as pi')->join('proyectos as p', 'p.id', '=', 'pi.proyecto_id')
                ->where('p.carrera_id', $career)->where('pi.usuario_id', $user)->whereNotIn('pi.rol', ['lider', 'integrante'])->where('p.activo', true)->pluck('p.id')->all(),
            'residencies' => DB::table('residencias')->where('carrera_id', $career)->where('asesor_id', $user)->where('estado', '!=', 'cerrada')->pluck('id')->all(),
        ];
    }
```

## ensureReleased

Visibilidad: `public`. Líneas originales 22–29.

```php
    public function ensureReleased(UserCareer $membership): void
    {
        DB::table('recursos_agenda')->insertOrIgnore(['id' => 'docente:'.$membership->usuario_id]);
        DB::table('recursos_agenda')->where('id', 'docente:'.$membership->usuario_id)->lockForUpdate()->first();
        $pending = $this->commitments($membership->usuario_id, (int) $membership->carrera_id);
        abort_if(collect($pending)->contains(fn ($items) => count($items) > 0), 409,
            'Reasigna o cancela explícitamente cursos, salas, asesorías y residencias pendientes antes de cambiar esta membresía.');
    }
```
