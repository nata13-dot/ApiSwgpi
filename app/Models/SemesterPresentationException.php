<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterPresentationException extends Model
{
    protected $table = 'autorizaciones_excepcionales';
    public $timestamps = false;

    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'tipo',
        'valor',
        'motivo',
        'vigente_hasta',
        'autorizada_por',
        'activa',
    ];

    protected $appends = [
        'periodo_id',
        'semestre_presentacion',
        'activo',
    ];

    protected $casts = [
        'proyecto_id' => 'integer',
        'activa' => 'boolean',
        'vigente_hasta' => 'datetime',
        'creada_en' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    public function getPeriodoIdAttribute(): ?int
    {
        $value = $this->decodedValue()['period_id'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function getSemestrePresentacionAttribute(): ?int
    {
        $value = $this->decodedValue()['semester'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function getActivoAttribute(): bool
    {
        return (bool) $this->activa;
    }

    public static function encodedValue(int $periodId, int $semester): string
    {
        return json_encode([
            'period_id' => $periodId,
            'semester' => $semester,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function decodedValue(): array
    {
        $decoded = json_decode((string) $this->valor, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return is_numeric($this->valor)
            ? ['semester' => (int) $this->valor]
            : [];
    }
}
