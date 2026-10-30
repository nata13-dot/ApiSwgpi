# User

Fuente: [app/Models/User.php](../../../../../app/Models/User.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Concerns\ScopedToCareerMembership;
use App\Models\Pivots\ProjectMemberPivot;
use App\Support\CareerContext;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasLegacyAliases, ScopedToCareerMembership;

    protected $table = 'usuarios';
    public $incrementing = false;
    protected $keyType = 'string';

    protected array $legacyAliases = [
        'apa' => 'apellido_paterno',
        'ama' => 'apellido_materno',
        'email' => 'correo',
        'password' => 'contrasena',
        'photo_path' => 'foto_ruta',
        'profile_completed_at' => 'perfil_completado_en',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = [
        'semestre',
        'grupo',
        'telefonos',
    ];

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'contrasena',
        'telefono', 'curp', 'direccion', 'foto_ruta', 'perfil_completado_en', 'perfil_id', 'activo',
        'apa', 'ama', 'email', 'password', 'photo_path', 'profile_completed_at',
    ];

    protected $hidden = ['contrasena', 'password', 'remember_token'];

    protected $casts = [
        'activo' => 'boolean',
        'email_verified_at' => 'datetime',
        'contrasena' => 'hashed',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'perfil_id' => 'integer',
        'semestre' => 'integer',
        'perfil_completado_en' => 'datetime',
    ];

    protected $appends = [
        'apa',
        'ama',
        'email',
        'photo_path',
        'profile_completed_at',
        'semestre',
        'grupo',
        'telefonos',
    ];



















    // JWT SUBJECT METHODS


    // RELACIONES









    // MÉTODOS HELPER
    

    // SCOPES
}
```

## getAuthPassword

Visibilidad: `public`. Líneas originales 74–77.

```php
    public function getAuthPassword()
    {
        return $this->contrasena;
    }
```

## getPerfilIdAttribute

Visibilidad: `public`. Líneas originales 79–93.

```php
    public function getPerfilIdAttribute($value): int
    {
        $context = app(CareerContext::class);
        if ($context->isGeneralAdmin() && $context->user()?->id === $this->id) {
            return 1;
        }
        if (array_key_exists('career_profile_id', $this->attributes) && $this->attributes['career_profile_id'] !== null) {
            return (int) $this->attributes['career_profile_id'];
        }
        if ($context->user()?->id === $this->id && $context->profileId()) {
            return (int) $context->profileId();
        }

        return (int) $value;
    }
```

## globalProfileId

Visibilidad: `public`. Líneas originales 95–98.

```php
    public function globalProfileId(): int
    {
        return (int) ($this->getRawOriginal('perfil_id') ?? $this->attributes['perfil_id'] ?? 0);
    }
```

## getPasswordAttribute

Visibilidad: `public`. Líneas originales 100–103.

```php
    public function getPasswordAttribute()
    {
        return $this->contrasena;
    }
```

## setPasswordAttribute

Visibilidad: `public`. Líneas originales 105–108.

```php
    public function setPasswordAttribute($value): void
    {
        $this->attributes['contrasena'] = $value;
    }
```

## getEmailAttribute

Visibilidad: `public`. Líneas originales 110–113.

```php
    public function getEmailAttribute()
    {
        return $this->correo;
    }
```

## setEmailAttribute

Visibilidad: `public`. Líneas originales 115–118.

```php
    public function setEmailAttribute($value): void
    {
        $this->attributes['correo'] = $value;
    }
```

## getApaAttribute

Visibilidad: `public`. Líneas originales 120–123.

```php
    public function getApaAttribute()
    {
        return $this->apellido_paterno;
    }
```

## setApaAttribute

Visibilidad: `public`. Líneas originales 125–128.

```php
    public function setApaAttribute($value): void
    {
        $this->attributes['apellido_paterno'] = $value;
    }
```

## getAmaAttribute

Visibilidad: `public`. Líneas originales 130–133.

```php
    public function getAmaAttribute()
    {
        return $this->apellido_materno;
    }
```

## setAmaAttribute

Visibilidad: `public`. Líneas originales 135–138.

```php
    public function setAmaAttribute($value): void
    {
        $this->attributes['apellido_materno'] = $value;
    }
```

## getPhotoPathAttribute

Visibilidad: `public`. Líneas originales 140–143.

```php
    public function getPhotoPathAttribute()
    {
        return $this->foto_ruta;
    }
```

## setPhotoPathAttribute

Visibilidad: `public`. Líneas originales 145–148.

```php
    public function setPhotoPathAttribute($value): void
    {
        $this->attributes['foto_ruta'] = $value;
    }
```

## getProfileCompletedAtAttribute

Visibilidad: `public`. Líneas originales 150–153.

```php
    public function getProfileCompletedAtAttribute()
    {
        return $this->perfil_completado_en;
    }
```

## setProfileCompletedAtAttribute

Visibilidad: `public`. Líneas originales 155–158.

```php
    public function setProfileCompletedAtAttribute($value): void
    {
        $this->attributes['perfil_completado_en'] = $value;
    }
```

## getTelefonosAttribute

Visibilidad: `public`. Líneas originales 160–167.

```php
    public function getTelefonosAttribute(): string
    {
        if (!$this->relationLoaded('phoneNumbers')) {
            return (string) ($this->attributes['telefono'] ?? '');
        }

        return $this->phoneNumbers->pluck('telefono')->filter()->join(', ');
    }
```

## getSemestreAttribute

Visibilidad: `public`. Líneas originales 169–193.

```php
    public function getSemestreAttribute(): ?int
    {
        if (app(CareerContext::class)->careerId() && \Illuminate\Support\Facades\Schema::hasTable('matriculas_academicas')) {
            $enrollment = app(\App\Services\AcademicWorkflowService::class)->enrollment($this->id);
            return $enrollment?->semestre;
        }

        if (array_key_exists('semestre', $this->attributes)) {
            return $this->attributes['semestre'] === null ? null : (int) $this->attributes['semestre'];
        }

        $query = \Illuminate\Support\Facades\DB::table('grupo_estudiantes')
            ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
            ->where('grupo_estudiantes.estudiante_id', $this->id)
            ->where('grupo_estudiantes.activo', true)
            ->where('grupos_academicos.activo', true);
        if ($careerId = app(CareerContext::class)->careerId()) {
            $query->where('grupos_academicos.carrera_id', $careerId);
        }
        $value = $query
            ->orderByDesc('grupo_estudiantes.inscrito_en')
            ->value('grupos_academicos.semestre');

        return $value === null ? null : (int) $value;
    }
```

## getGrupoAttribute

Visibilidad: `public`. Líneas originales 195–218.

```php
    public function getGrupoAttribute(): ?string
    {
        if (app(CareerContext::class)->careerId() && \Illuminate\Support\Facades\Schema::hasTable('matriculas_academicas')) {
            $enrollment = app(\App\Services\AcademicWorkflowService::class)->enrollment($this->id);
            return $enrollment?->clave_grupo;
        }

        if (array_key_exists('grupo', $this->attributes)) {
            return $this->attributes['grupo'];
        }

        $query = \Illuminate\Support\Facades\DB::table('grupo_estudiantes')
            ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
            ->where('grupo_estudiantes.estudiante_id', $this->id)
            ->where('grupo_estudiantes.activo', true)
            ->where('grupos_academicos.activo', true);
        if ($careerId = app(CareerContext::class)->careerId()) {
            $query->where('grupos_academicos.carrera_id', $careerId);
        }

        return $query
            ->orderByDesc('grupo_estudiantes.inscrito_en')
            ->value('grupos_academicos.clave_grupo');
    }
```

## getJWTIdentifier

Visibilidad: `public`. Líneas originales 221–224.

```php
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
```

## getJWTCustomClaims

Visibilidad: `public`. Líneas originales 226–232.

```php
    public function getJWTCustomClaims()
    {
        return [
            'perfil_id' => $this->perfil_id,
            'nombres' => $this->nombres,
        ];
    }
```

## projectsAsAdvisor

Visibilidad: `public`. Líneas originales 235–240.

```php
    public function projectsAsAdvisor(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'proyecto_integrantes', 'usuario_id', 'proyecto_id')
                    ->using(ProjectMemberPivot::class)
                    ->withPivot('rol');
    }
```

## teacherGroupAssignments

Visibilidad: `public`. Líneas originales 242–245.

```php
    public function teacherGroupAssignments(): HasMany
    {
        return $this->hasMany(TeacherGroupAssignment::class, 'docente_id', 'id');
    }
```

## projectsCreated

Visibilidad: `public`. Líneas originales 247–250.

```php
    public function projectsCreated(): HasMany
    {
        return $this->hasMany(Project::class, 'creado_por', 'id');
    }
```

## deliveries

Visibilidad: `public`. Líneas originales 252–255.

```php
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'enviado_por', 'id');
    }
```

## deliverables

Visibilidad: `public`. Líneas originales 257–261.

```php
    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'entregas', 'enviado_por', 'entregable_id')
            ->withPivot(['id', 'proyecto_id', 'documento_id', 'entregado_en', 'calificacion', 'comentarios_docente']);
    }
```

## feedbacks

Visibilidad: `public`. Líneas originales 263–266.

```php
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'comentado_por', 'id');
    }
```

## phoneNumbers

Visibilidad: `public`. Líneas originales 268–271.

```php
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(UserPhone::class, 'usuario_id', 'id');
    }
```

## careers

Visibilidad: `public`. Líneas originales 273–278.

```php
    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class, 'usuario_carrera', 'usuario_id', 'carrera_id')
            ->withPivot(['perfil_id', 'es_principal', 'activo', 'asignado_por'])
            ->withTimestamps('creado_en', 'actualizado_en');
    }
```

## careerMemberships

Visibilidad: `public`. Líneas originales 280–283.

```php
    public function careerMemberships(): HasMany
    {
        return $this->hasMany(UserCareer::class, 'usuario_id', 'id');
    }
```

## isAdmin

Visibilidad: `public`. Líneas originales 286–286.

```php
    public function isAdmin(): bool { return in_array($this->perfil_id, [1, 5], true); }
```

## isGeneralAdmin

Visibilidad: `public`. Líneas originales 287–287.

```php
    public function isGeneralAdmin(): bool { return $this->globalProfileId() === 4; }
```

## isTeacher

Visibilidad: `public`. Líneas originales 288–288.

```php
    public function isTeacher(): bool { return $this->perfil_id === 2; }
```

## isStudent

Visibilidad: `public`. Líneas originales 289–289.

```php
    public function isStudent(): bool { return $this->perfil_id === 3; }
```

## isCareerHead

Visibilidad: `public`. Líneas originales 290–290.

```php
    public function isCareerHead(): bool { return $this->perfil_id === 5; }
```

## isCareerHeadAssistant

Visibilidad: `public`. Líneas originales 291–291.

```php
    public function isCareerHeadAssistant(): bool { return $this->perfil_id === 6; }
```

## isProjectCoordinator

Visibilidad: `public`. Líneas originales 292–292.

```php
    public function isProjectCoordinator(): bool { return $this->perfil_id === 7; }
```

## canManageAcademics

Visibilidad: `public`. Líneas originales 293–293.

```php
    public function canManageAcademics(): bool { return in_array($this->perfil_id, [1, 5, 6], true); }
```

## canManageProjects

Visibilidad: `public`. Líneas originales 294–294.

```php
    public function canManageProjects(): bool { return in_array($this->perfil_id, [1, 5, 6, 7], true); }
```

## getFullName

Visibilidad: `public`. Líneas originales 296–299.

```php
    public function getFullName(): string
    {
        return trim("{$this->nombres} {$this->apa} {$this->ama}");
    }
```

## scopeAdmins

Visibilidad: `public`. Líneas originales 302–302.

```php
    public function scopeAdmins($query) { return $query->withCareerProfiles([1, 5]); }
```

## scopeTeachers

Visibilidad: `public`. Líneas originales 303–303.

```php
    public function scopeTeachers($query) { return $query->withCareerProfiles(2); }
```

## scopeStudents

Visibilidad: `public`. Líneas originales 304–304.

```php
    public function scopeStudents($query) { return $query->withCareerProfiles(3); }
```

## scopeActivos

Visibilidad: `public`. Líneas originales 305–305.

```php
    public function scopeActivos($query) { return $query->where('activo', true); }
```
