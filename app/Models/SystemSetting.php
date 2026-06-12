<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        static::updateOrCreate(
            ['key' => $key],
            ['value' => ['data' => $value], 'type' => $type, 'description' => $description]
        );
        self::$memoizedSettings = null;

        try {
            Cache::store(config('auth.activity_cache_store', 'file'))->forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // La persistencia del ajuste ya fue completada.
        }
    }

    private static function loadWithDefaults(): array
    {
        $settings = static::query()
            ->get(['clave', 'valor'])
            ->mapWithKeys(fn ($item) => [$item->key => $item->value['data'] ?? null])
            ->all();

        return array_replace(static::DEFAULTS, array_filter($settings, fn ($value) => $value !== null));
    }
}
