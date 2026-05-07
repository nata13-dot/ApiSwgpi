<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id', 'nombres', 'apa', 'ama', 'email', 'password', 
        'curp', 'direccion', 'telefonos', 'perfil_id', 'activo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'activo' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'perfil_id' => 'integer',
    ];

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
        return $this->belongsToMany(Project::class, 'project_user', 'user_id', 'project_id')
                    ->withPivot('rol_asesor');
    }

    public function projectsCreated(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by', 'id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'submitted_by', 'id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'comentado_por', 'id');
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
