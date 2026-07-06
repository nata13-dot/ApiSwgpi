<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Pivots\ProjectMemberPivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasLegacyAliases;

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

    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    public function getPasswordAttribute()
    {
        return $this->contrasena;
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['contrasena'] = $value;
    }

    public function getEmailAttribute()
    {
        return $this->correo;
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['correo'] = $value;
    }

    public function getApaAttribute()
    {
        return $this->apellido_paterno;
    }

    public function setApaAttribute($value): void
    {
        $this->attributes['apellido_paterno'] = $value;
    }

    public function getAmaAttribute()
    {
        return $this->apellido_materno;
    }

    public function setAmaAttribute($value): void
    {
        $this->attributes['apellido_materno'] = $value;
    }

    public function getPhotoPathAttribute()
    {
        return $this->foto_ruta;
    }

    public function setPhotoPathAttribute($value): void
    {
        $this->attributes['foto_ruta'] = $value;
    }

    public function getProfileCompletedAtAttribute()
    {
        return $this->perfil_completado_en;
    }

    public function setProfileCompletedAtAttribute($value): void
    {
        $this->attributes['perfil_completado_en'] = $value;
    }

    public function getTelefonosAttribute(): string
    {
        if (!$this->relationLoaded('phoneNumbers')) {
            return (string) ($this->attributes['telefono'] ?? '');
        }

        return $this->phoneNumbers->pluck('telefono')->filter()->join(', ');
    }

    public function getSemestreAttribute(): ?int
    {
        if (array_key_exists('semestre', $this->attributes)) {
            return $this->attributes['semestre'] === null ? null : (int) $this->attributes['semestre'];
        }

        $value = \Illuminate\Support\Facades\DB::table('grupo_estudiantes')
            ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
            ->where('grupo_estudiantes.estudiante_id', $this->id)
            ->where('grupo_estudiantes.activo', true)
            ->where('grupos_academicos.activo', true)
            ->orderByDesc('grupo_estudiantes.inscrito_en')
            ->value('grupos_academicos.semestre');

        return $value === null ? null : (int) $value;
    }

    public function getGrupoAttribute(): ?string
    {
        if (array_key_exists('grupo', $this->attributes)) {
            return $this->attributes['grupo'];
        }

        return \Illuminate\Support\Facades\DB::table('grupo_estudiantes')
            ->join('grupos_academicos', 'grupos_academicos.id', '=', 'grupo_estudiantes.grupo_id')
            ->where('grupo_estudiantes.estudiante_id', $this->id)
            ->where('grupo_estudiantes.activo', true)
            ->where('grupos_academicos.activo', true)
            ->orderByDesc('grupo_estudiantes.inscrito_en')
            ->value('grupos_academicos.clave_grupo');
    }

    // JWT SUBJECT METHODS
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'perfil_id' => $this->perfil_id,
            'nombres' => $this->nombres,
        ];
    }

    // RELACIONES
    public function projectsAsAdvisor(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'proyecto_integrantes', 'usuario_id', 'proyecto_id')
                    ->using(ProjectMemberPivot::class)
                    ->withPivot('rol');
    }

    public function teacherGroupAssignments(): HasMany
    {
        return $this->hasMany(TeacherGroupAssignment::class, 'docente_id', 'id');
    }

    public function projectsCreated(): HasMany
    {
        return $this->hasMany(Project::class, 'creado_por', 'id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'enviado_por', 'id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'comentado_por', 'id');
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(UserPhone::class, 'usuario_id', 'id');
    }

    // MÉTODOS HELPER
    public function isAdmin(): bool { return $this->perfil_id === 1; }
    public function isTeacher(): bool { return $this->perfil_id === 2; }
    public function isStudent(): bool { return $this->perfil_id === 3; }
    
    public function getFullName(): string
    {
        return trim("{$this->nombres} {$this->apa} {$this->ama}");
    }

    // SCOPES
    public function scopeAdmins($query) { return $query->where('perfil_id', 1); }
    public function scopeTeachers($query) { return $query->where('perfil_id', 2); }
    public function scopeStudents($query) { return $query->where('perfil_id', 3); }
    public function scopeActivos($query) { return $query->where('activo', true); }
}
