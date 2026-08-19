<?php

namespace App\Models;

use App\Support\CareerContext;
use Illuminate\Support\Facades\DB;

final class CareerSetting
{
    public const KEYS = [
        'system_notices',
        'proposal_registration_enabled',
        'max_project_members',
        'evaluation_manager_teacher_ids',
        'rubric_score_modes',
    ];

    public static function all(): array
    {
        $careerId = app(CareerContext::class)->careerId();

        return $careerId ? static::allForCareer($careerId) : [];
    }

    public static function allForCareer(int $careerId): array
    {
        return DB::table('configuraciones_carrera')
            ->where('carrera_id', $careerId)
            ->whereIn('clave', self::KEYS)
            ->get(['clave', 'valor'])
            ->mapWithKeys(fn ($row) => [$row->clave => static::decode($row->valor)])
            ->all();
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function valueForCareer(?int $careerId, string $key, mixed $default = null): mixed
    {
        return $careerId ? (static::allForCareer($careerId)[$key] ?? $default) : $default;
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException("La clave {$key} no es una configuración de carrera.");
        }

        $careerId = app(CareerContext::class)->careerId();
        if (!$careerId) {
            throw new \LogicException('No existe una carrera activa.');
        }

        $identity = ['carrera_id' => $careerId, 'clave' => $key];
        $values = [
            'valor' => json_encode(['data' => $value], JSON_UNESCAPED_UNICODE),
            'tipo' => $type,
            'descripcion' => $description,
            'actualizada_en' => now(),
        ];

        if (DB::table('configuraciones_carrera')->where($identity)->exists()) {
            DB::table('configuraciones_carrera')->where($identity)->update($values);
            return;
        }

        DB::table('configuraciones_carrera')->insert($identity + $values + ['creada_en' => now()]);
    }

    private static function decode(mixed $value): mixed
    {
        $decoded = is_string($value) ? json_decode($value, true) : (array) $value;

        return $decoded['data'] ?? null;
    }
}
