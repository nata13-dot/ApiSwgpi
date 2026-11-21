<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
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
    use Archivable, HasFactory, Notifiable, HasLegacyAliases, ScopedToCareerMembership;

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
        'perfil_id',
    ];

    // El perfil vive en usuario_carrera; al crear un usuario se guarda aquí hasta registrar su membresía.
    private ?int $pendingProfileId = null;
    private ?int $principalProfileId = null;
    private ?bool $generalAdministrator = null;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'contrasena',
        'telefono', 'especialidad', 'foto_ruta', 'perfil_completado_en', 'perfil_id', 'activo', 'debe_cambiar_contrasena',
        'apa', 'ama', 'email', 'password', 'photo_path', 'profile_completed_at',
    ];

    protected $hidden = ['contrasena', 'password', 'remember_token'];

    protected $casts = [
        'activo' => 'boolean',
        'email_verified_at' => 'datetime',
        'contrasena' => 'hashed',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'es_administrador_general' => 'boolean',
        'admin_general_hasta' => 'datetime',
        'debe_cambiar_contrasena' => 'boolean',
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
        'perfil_id',
    ];

    protected static function booted(): void
    {
        // Cambiar la contraseña cierra las sesiones de la app en otros teléfonos.
        static::updated(function (self $user) {
            if ($user->wasChanged('contrasena') && \App\Support\SchemaCache::hasTable('sesiones_app')) {
                app(\App\Services\AppSessionService::class)->revokeAllForUser((string) $user->getKey(), \App\Services\AppSessionService::current());
            }
        });
    }

    public function getAuthPassword()
    {
        return $this->contrasena;
    }

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
        if ($value !== null) {
            return (int) $value;
        }
        if ($this->pendingProfileId !== null || !$this->exists) {
            return (int) $this->pendingProfileId;
        }

        // Al serializar listas sin scope de carrera: un solo mapa por petición, no una consulta por usuario.
        $profiles = \App\Support\RequestMemo::remember('perfiles-principales', fn () => UserCareer::query()
            ->orderByDesc('activo')->orderByDesc('es_principal')->orderBy('id')
            ->get(['usuario_id', 'perfil_id'])->unique('usuario_id')->pluck('perfil_id', 'usuario_id')->all());

        return (int) ($profiles[$this->getKey()] ?? 0);
    }

    public static function forgetPrincipalProfiles(): void
    {
        \App\Support\RequestMemo::forget('perfiles-principales');
    }

    public function setPerfilIdAttribute($value): void
    {
        $this->pendingProfileId = $value === null || $value === '' ? null : (int) $value;
    }

    public function pendingProfileId(): ?int
    {
        return $this->pendingProfileId;
    }

    public function globalProfileId(): int
    {
        // Mientras el administrador general simula un nivel, todo el sistema lo trata como ese perfil.
        $context = app(CareerContext::class);
        if ($context->simulatedProfile() && $context->user()?->getKey() === $this->getKey()) {
            return $context->simulatedProfile();
        }

        return $this->isGeneralAdministrator() ? 4 : ($this->pendingProfileId ?? $this->principalProfileId());
    }

    private function isGeneralAdministrator(): bool
    {
        // La designación temporal deja de valer al vencer, aunque la tarea programada aún no la haya retirado.
        if (array_key_exists('es_administrador_general', $this->attributes) && array_key_exists('admin_general_hasta', $this->attributes)) {
            return (bool) $this->attributes['es_administrador_general'] && self::stillValid($this->attributes['admin_general_hasta']);
        }

        return $this->generalAdministrator ??= $this->exists && (function () {
            // Antes de la migración (o en bases de prueba) puede no existir la fecha de término.
            $withUntil = \App\Support\SchemaCache::hasColumn('usuarios', 'admin_general_hasta');
            $row = static::withoutGlobalScopes()->whereKey($this->getKey())->toBase()
                ->first($withUntil ? ['es_administrador_general', 'admin_general_hasta'] : ['es_administrador_general']);

            return $row && (bool) ($row->es_administrador_general ?? false) && self::stillValid($row->admin_general_hasta ?? null);
        })();
    }

    private static function stillValid($until): bool
    {
        return $until === null || \Illuminate\Support\Carbon::parse($until)->isFuture();
    }

    private function principalProfileId(): int
    {
        if (!$this->exists) {
            return 0;
        }

        // Mismo orden que ResolveCareerContext: membresía activa, principal primero.
        return $this->principalProfileId ??= (int) UserCareer::where('usuario_id', $this->getKey())
            ->orderByDesc('activo')->orderByDesc('es_principal')->orderBy('id')->value('perfil_id');
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

    // Nombres y apellidos se guardan siempre con formato de nombre propio, venga de donde venga el dato.
    public function setNombresAttribute($value): void
    {
        $this->attributes['nombres'] = \App\Support\PersonName::format($value);
    }

    public function setApellidoPaternoAttribute($value): void
    {
        $this->attributes['apellido_paterno'] = \App\Support\PersonName::format($value);
    }

    public function setApellidoMaternoAttribute($value): void
    {
        $this->attributes['apellido_materno'] = $value === null || trim((string) $value) === '' ? null : \App\Support\PersonName::format($value);
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
        if (app(CareerContext::class)->careerId() && \App\Support\SchemaCache::hasTable('matriculas_academicas')) {
            $group = $this->academicGroup();
            return $group ? (int) $group->semestre : null;
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

    public function getGrupoAttribute(): ?string
    {
        if (app(CareerContext::class)->careerId() && \App\Support\SchemaCache::hasTable('matriculas_academicas')) {
            return $this->academicGroup()?->clave_grupo;
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

    /**
     * Grupo académico del alumno: su matrícula del periodo abierto y, si no tiene (o la carrera no tiene periodo
     * abierto), su matrícula activa más reciente. Misma regla que el filtro de LegacyAliasBuilder.
     */
    public function academicGroup(): ?object
    {
        $careerId = app(CareerContext::class)->careerId();
        if (!$careerId) return null;

        return \App\Support\RequestMemo::remember("grupo-academico:{$careerId}:{$this->id}", function () use ($careerId) {
            $enrollment = app(\App\Services\AcademicWorkflowService::class)->enrollment($this->id);
            if ($enrollment) return (object) ['semestre' => $enrollment->semestre, 'clave_grupo' => $enrollment->clave_grupo];

            $latest = \Illuminate\Support\Facades\DB::table('matriculas_academicas as m')
                ->join('grupos_academicos as g', 'g.id', '=', 'm.grupo_id')
                ->leftJoin('periodos_academicos as p', 'p.id', '=', 'g.periodo_id')
                ->where('m.estudiante_id', $this->id)->where('g.carrera_id', $careerId)->where('m.estado', 'activa')
                ->orderByDesc('p.fecha_inicio')->orderByDesc('m.id')
                ->first(['g.semestre', 'g.clave_grupo']);
            if ($latest || !\App\Support\SchemaCache::hasTable('grupo_estudiantes')) return $latest;

            // Sin matrícula: el grupo de la tabla anterior, hasta que la conciliación del periodo lo pase a matrícula.
            return \Illuminate\Support\Facades\DB::table('grupo_estudiantes as ge')
                ->join('grupos_academicos as g', 'g.id', '=', 'ge.grupo_id')
                ->leftJoin('periodos_academicos as p', 'p.id', '=', 'g.periodo_id')
                ->where('ge.estudiante_id', $this->id)->where('ge.activo', true)->where('g.activo', true)->where('g.carrera_id', $careerId)
                ->orderByDesc('p.fecha_inicio')->orderByDesc('ge.inscrito_en')
                ->first(['g.semestre', 'g.clave_grupo']);
        });
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'enviado_por', 'id');
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'entregas', 'enviado_por', 'entregable_id')
            ->withPivot(['id', 'proyecto_id', 'documento_id', 'entregado_en', 'calificacion', 'comentarios_docente']);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'comentado_por', 'id');
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(UserPhone::class, 'usuario_id', 'id');
    }

    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class, 'usuario_carrera', 'usuario_id', 'carrera_id')
            ->withPivot(['perfil_id', 'es_principal', 'activo', 'asignado_por'])
            ->withTimestamps('creado_en', 'actualizado_en');
    }

    public function careerMemberships(): HasMany
    {
        return $this->hasMany(UserCareer::class, 'usuario_id', 'id');
    }

    // MÉTODOS HELPER
    public function isAdmin(): bool { return in_array($this->perfil_id, [1, 5], true); }
    public function isGeneralAdmin(): bool { return $this->globalProfileId() === 4; }
    public function isTeacher(): bool { return $this->perfil_id === 2; }
    public function isStudent(): bool { return $this->perfil_id === 3; }
    public function isCareerHead(): bool { return $this->perfil_id === 5; }
    public function isCareerHeadAssistant(): bool { return $this->perfil_id === 6; }
    public function isProjectCoordinator(): bool { return $this->perfil_id === 7; }
    public function canManageAcademics(): bool { return in_array($this->perfil_id, [1, 5, 6], true); }
    public function canManageProjects(): bool { return in_array($this->perfil_id, [1, 5, 6, 7], true); }
    
    public function getFullName(): string
    {
        return trim("{$this->nombres} {$this->apa} {$this->ama}");
    }

    // SCOPES
    public function scopeAdmins($query) { return $query->withCareerProfiles([1, 5]); }
    public function scopeTeachers($query) { return $query->withCareerProfiles(2); }
    public function scopeStudents($query) { return $query->withCareerProfiles(3); }
    public function scopeActivos($query) { return $query->where('activo', true); }
}
