# ScopedToCareerMembership

Fuente: [app/Models/Concerns/ScopedToCareerMembership.php](../../../../../../app/Models/Concerns/ScopedToCareerMembership.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models\Concerns;

use App\Models\UserCareer;
use App\Support\CareerContext;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToCareerMembership
{


}
```

## bootScopedToCareerMembership

Visibilidad: `protected`. Líneas originales 11–58.

```php
    protected static function bootScopedToCareerMembership(): void
    {
        static::addGlobalScope('careerMembership', function (Builder $query): void {
            $careerId = app(CareerContext::class)->careerId();
            if (!$careerId) {
                return;
            }

            $query->whereExists(function ($membershipQuery) use ($careerId): void {
                $membershipQuery->selectRaw('1')
                    ->from('usuario_carrera')
                    ->whereColumn('usuario_carrera.usuario_id', 'usuarios.id')
                    ->where('usuario_carrera.carrera_id', $careerId)
                    ->where('usuario_carrera.activo', true);
            });

            $query->addSelect([
                'career_profile_id' => UserCareer::query()
                    ->select('perfil_id')
                    ->whereColumn('usuario_carrera.usuario_id', 'usuarios.id')
                    ->where('usuario_carrera.carrera_id', $careerId)
                    ->where('usuario_carrera.activo', true)
                    ->limit(1),
            ]);
        });

        static::created(function ($user): void {
            $context = app(CareerContext::class);
            if (!$context->careerId() || $user->globalProfileId() === 4) {
                return;
            }

            UserCareer::updateOrCreate(
                [
                    'usuario_id' => $user->id,
                    'carrera_id' => $context->careerId(),
                ],
                [
                    'perfil_id' => $user->globalProfileId(),
                    'es_principal' => !$user->careerMemberships()->exists(),
                    'activo' => array_key_exists('activo', $user->getAttributes())
                        ? (bool) $user->activo
                        : true,
                    'asignado_por' => auth('api')->id(),
                ]
            );
        });
    }
```

## scopeInCareer

Visibilidad: `public`. Líneas originales 60–69.

```php
    public function scopeInCareer(Builder $query, int $careerId, ?array $profileIds = null): Builder
    {
        return $query->withoutGlobalScope('careerMembership')
            ->whereHas('careerMemberships', function ($membershipQuery) use ($careerId, $profileIds): void {
                $membershipQuery->where('carrera_id', $careerId)->where('activo', true);
                if ($profileIds) {
                    $membershipQuery->whereIn('perfil_id', $profileIds);
                }
            });
    }
```

## scopeWithCareerProfiles

Visibilidad: `public`. Líneas originales 71–83.

```php
    public function scopeWithCareerProfiles(Builder $query, int|array $profileIds): Builder
    {
        $careerId = app(CareerContext::class)->careerId();
        $profileIds = array_map('intval', (array) $profileIds);
        if (!$careerId) {
            return $query->whereIn('usuarios.perfil_id', $profileIds);
        }

        return $query->whereHas('careerMemberships', fn ($membershipQuery) => $membershipQuery
            ->where('carrera_id', $careerId)
            ->where('activo', true)
            ->whereIn('perfil_id', $profileIds));
    }
```
