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
        'basic_semesters_enabled',
    ];

    public static function all(): array
    {
        $careerId = app(CareerContext::class)->careerId();

        return $careerId ? static::allForCareer($careerId) : [];
    }

    // Estas claves no son JSON libre: viven en su propia tabla o columna.
    private const TABLE_BACKED = ['evaluation_manager_teacher_ids', 'rubric_score_modes'];
    public const STORED_KEYS = ['system_notices', 'proposal_registration_enabled', 'max_project_members'];

    private static array $memoized = [];

    public static function allForCareer(int $careerId): array
    {
        return self::$memoized[$careerId] ??= DB::table('configuraciones_carrera')
            ->where('carrera_id', $careerId)
            ->whereIn('clave', array_diff(self::KEYS, self::TABLE_BACKED))
            ->get(['clave', 'valor'])
            ->mapWithKeys(fn ($row) => [$row->clave => static::decode($row->valor)])
            ->all() + [
                'evaluation_manager_teacher_ids' => DB::table('carrera_responsables_evaluacion')
                    ->where('carrera_id', $careerId)->orderBy('docente_id')->pluck('docente_id')->all(),
                'rubric_score_modes' => DB::table('rubricas')->where('carrera_id', $careerId)->whereNotNull('semestre')
                    ->orderBy('semestre')->pluck('modo_puntaje', 'semestre')->mapWithKeys(fn ($mode, $semester) => [(string) $semester => $mode])->all(),
            ];
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

        static::setValueForCareer($careerId, $key, $value, $type, $description);
    }

    // El administrador general ajusta carreras distintas a la activa (p. ej. habilitar 1°–4°).
    public static function setValueForCareer(int $careerId, string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException("La clave {$key} no es una configuración de carrera.");
        }

        unset(self::$memoized[$careerId]);
        if (in_array($key, self::TABLE_BACKED, true)) {
            static::setTableBackedValue($careerId, $key, (array) $value);
            return;
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

    private static function setTableBackedValue(int $careerId, string $key, array $value): void
    {
        DB::transaction(function () use ($careerId, $key, $value): void {
            if ($key === 'rubric_score_modes') {
                foreach ($value as $semester => $mode) {
                    DB::table('rubricas')->where('carrera_id', $careerId)->where('semestre', (int) $semester)->update(['modo_puntaje' => $mode]);
                }
                return;
            }

            $teacherIds = array_values(array_unique(array_map('strval', $value)));
            DB::table('carrera_responsables_evaluacion')->where('carrera_id', $careerId)->whereNotIn('docente_id', $teacherIds)->delete();
            DB::table('carrera_responsables_evaluacion')->insertOrIgnore(array_map(
                fn ($teacherId) => ['carrera_id' => $careerId, 'docente_id' => $teacherId],
                $teacherIds
            ));
        });
    }

    private static function decode(mixed $value): mixed
    {
        $decoded = is_string($value) ? json_decode($value, true) : (array) $value;

        return $decoded['data'] ?? null;
    }
}
