<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    use HasLegacyAliases;

    private static ?array $memoizedSettings = null;
    private const CACHE_KEY = 'system-settings:all:v1';

    protected $table = 'configuraciones_sistema';

    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'creada_en';
    const UPDATED_AT = 'actualizada_en';

    protected array $legacyAliases = [
        'key' => 'clave',
        'value' => 'valor',
        'type' => 'tipo',
        'description' => 'descripcion',
        'created_at' => 'creada_en',
        'updated_at' => 'actualizada_en',
    ];

    protected $fillable = ['key', 'value', 'type', 'description'];

    protected $casts = [
        'valor' => 'array',
        'valor_booleano' => 'boolean',
        'valor_decimal' => 'decimal:6',
    ];

    public const DEFAULTS = [
        'session_timeout_minutes' => 30,
        'default_theme' => 'system',
        'active_academic_period' => '2026-1',
        'max_file_size_mb' => 50,
        'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'webp'],
        'max_project_members' => 4,
        'global_notice' => '',
        'proposal_registration_enabled' => true,
        'evaluation_manager_teacher_ids' => [],
        'rubric_score_modes' => [],
        'font_scale' => 100,
        'grayscale_mode' => false,
        'system_notices' => [],
    ];

    public static function allWithDefaults(): array
    {
        if (self::$memoizedSettings !== null) {
            return self::$memoizedSettings;
        }

        try {
            self::$memoizedSettings = Cache::store(config('auth.activity_cache_store', 'file'))
                ->remember(self::CACHE_KEY, now()->addSeconds(60), fn () => static::loadWithDefaults());
        } catch (\Throwable) {
            self::$memoizedSettings = static::loadWithDefaults();
        }

        return self::$memoizedSettings;
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        return static::allWithDefaults()[$key] ?? $default;
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        if (static::usesTypedValueColumns()) {
            static::setTypedValue($key, $value, $type, $description);
            self::$memoizedSettings = null;
            static::clearCache();

            return;
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => ['data' => $value], 'type' => $type, 'description' => $description]
        );
        self::$memoizedSettings = null;
        static::clearCache();
    }

    private static function clearCache(): void
    {
        try {
            Cache::store(config('auth.activity_cache_store', 'file'))->forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // La persistencia del ajuste ya fue completada.
        }
    }

    private static function loadWithDefaults(): array
    {
        try {
            $settings = static::usesTypedValueColumns()
                ? static::loadTypedValues()
                : static::loadJsonValues();
        } catch (\Throwable) {
            $settings = [];
        }

        return array_replace(static::DEFAULTS, array_filter($settings, fn ($value) => $value !== null));
    }

    private static function loadJsonValues(): array
    {
        if (! Schema::hasColumn((new static())->getTable(), 'valor')) {
            return [];
        }

        return static::query()
            ->get(['clave', 'valor'])
            ->mapWithKeys(fn ($item) => [$item->key => $item->value['data'] ?? null])
            ->all();
    }

    private static function loadTypedValues(): array
    {
        return DB::table((new static())->getTable())
            ->get(['clave', 'tipo', 'valor_texto', 'valor_entero', 'valor_booleano', 'valor_decimal'])
            ->mapWithKeys(fn ($item) => [$item->clave => static::extractTypedValue($item)])
            ->all();
    }

    private static function extractTypedValue(object $item): mixed
    {
        return match ($item->tipo) {
            'entero', 'integer' => $item->valor_entero === null ? null : (int) $item->valor_entero,
            'booleano', 'boolean' => $item->valor_booleano === null ? null : (bool) $item->valor_booleano,
            'decimal' => $item->valor_decimal === null ? null : (float) $item->valor_decimal,
            default => static::decodeTextValue($item->valor_texto),
        };
    }

    private static function decodeTextValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private static function setTypedValue(string $key, mixed $value, string $type, ?string $description = null): void
    {
        $storedType = static::typedStorageType($type, $value);
        $row = [
            'clave' => $key,
            'tipo' => $storedType,
            'valor_texto' => null,
            'valor_entero' => null,
            'valor_booleano' => null,
            'valor_decimal' => null,
            'descripcion' => $description,
            'actualizada_en' => now(),
        ];

        match ($storedType) {
            'entero' => $row['valor_entero'] = (int) $value,
            'booleano' => $row['valor_booleano'] = (bool) $value,
            'decimal' => $row['valor_decimal'] = $value,
            default => $row['valor_texto'] = is_array($value) || is_object($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : $value,
        };

        $exists = DB::table((new static())->getTable())->where('clave', $key)->exists();

        if ($exists) {
            DB::table((new static())->getTable())->where('clave', $key)->update($row);
            return;
        }

        $row['creada_en'] = now();
        DB::table((new static())->getTable())->insert($row);
    }

    private static function typedStorageType(string $type, mixed $value): string
    {
        return match ($type) {
            'integer', 'entero' => 'entero',
            'boolean', 'booleano' => 'booleano',
            'decimal' => 'decimal',
            default => is_bool($value) ? 'booleano' : (is_int($value) ? 'entero' : 'texto'),
        };
    }

    private static function usesTypedValueColumns(): bool
    {
        $table = (new static())->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'valor_texto')
            && Schema::hasColumn($table, 'valor_entero')
            && Schema::hasColumn($table, 'valor_booleano')
            && Schema::hasColumn($table, 'valor_decimal');
    }
}
