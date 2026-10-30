# SemesterManagementService

Fuente: [app/Services/SemesterManagementService.php](../../../app/Services/SemesterManagementService.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\SemesterPresentationException;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class SemesterManagementService
{





}
```

## activePeriod

Visibilidad: `public`. Líneas originales 15–18.

```php
    public function activePeriod(): ?AcademicPeriod
    {
        return app(AcademicWorkflowService::class)->period();
    }
```

## syncCurrentPeriod

Visibilidad: `public`. Líneas originales 20–24.

```php
    public function syncCurrentPeriod(): ?AcademicPeriod
    {
        // Scheduler prepares work only; it must never activate or promote students.
        return app(\App\Support\CareerContext::class)->careerId() ? $this->activePeriod() : null;
    }
```

## activate

Visibilidad: `public`. Líneas originales 26–30.

```php
    public function activate(AcademicPeriod $period, bool $allowAutomaticPromotion = false): AcademicPeriod
    {
        app(AcademicPeriodLifecycleService::class)->transition($period, 'abierto');
        return $period->fresh();
    }
```

## semestersForPeriod

Visibilidad: `public`. Líneas originales 32–35.

```php
    public function semestersForPeriod(AcademicPeriod $period): array
    {
        return [5, 6, 7, 8, 9];
    }
```

## presentationSemester

Visibilidad: `public`. Líneas originales 37–43.

```php
    public function presentationSemester(int $projectId, ?int $academicSemester = null, ?AcademicPeriod $period = null): ?int
    {
        $period ??= $this->activePeriod();
        if (!$period) return $academicSemester;
        $authorization = app(AcademicAuthorizationService::class)->valid('presentacion_semestre', $projectId, $period->id)->first();
        return $authorization ? (int) $authorization->semestre_destino : $academicSemester;
    }
```

## presentationSemestersForProjects

Visibilidad: `public`. Líneas originales 45–51.

```php
    public function presentationSemestersForProjects(Collection $projects, ?AcademicPeriod $period = null): array
    {
        $period ??= $this->activePeriod();
        return $projects->mapWithKeys(fn ($project) => [$project->id => $this->presentationSemester(
            $project->id, $period ? app(AcademicWorkflowService::class)->projectSemester($project, $period->id) : null, $period
        )])->all();
    }
```
