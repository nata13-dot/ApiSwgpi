<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type', 'description'];

    protected $casts = [
        'value' => 'array',
    ];

    public const DEFAULTS = [
        'session_timeout_minutes' => 30,
        'default_theme' => 'system',
        'active_academic_period' => '2026-1',
        'max_file_size_mb' => 50,
        'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
        'max_project_members' => 4,
        'global_notice' => '',
        'proposal_registration_enabled' => true,
        'font_scale' => 100,
    ];

    public static function allWithDefaults(): array
    {
        $settings = static::query()->get()->mapWithKeys(fn ($item) => [$item->key => $item->value['data'] ?? null])->all();
        return array_replace(static::DEFAULTS, array_filter($settings, fn ($value) => $value !== null));
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
    }
}
