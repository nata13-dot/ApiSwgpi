<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    use BelongsToCareer;

    protected $table = 'cursos';
    public $timestamps = false;
    protected $fillable = ['carrera_id', 'grupo_id', 'asignatura_id', 'activo', 'es_seguimiento_proyecto', 'clave_autoregistro', 'clave_actualizada_en', 'clave_actualizada_por'];
    protected $hidden = ['clave_autoregistro'];
    protected $casts = ['activo' => 'boolean', 'es_seguimiento_proyecto' => 'boolean', 'clave_actualizada_en' => 'datetime'];

    public function subject(): BelongsTo { return $this->belongsTo(Asignatura::class, 'asignatura_id'); }
    public function group(): BelongsTo { return $this->belongsTo(SubjectGroup::class, 'grupo_id'); }
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_estudiantes', 'curso_id', 'estudiante_id')
            ->withPivot(['inscrito_en', 'activo']);
    }
}
