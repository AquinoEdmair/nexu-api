<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class SystemSetting extends Model
{
    protected $primaryKey = 'key';
    public    $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'description'];

    public static function get(string $key, string $default = ''): string
    {
        return Cache::remember("system_setting:{$key}", 300, function () use ($key, $default): string {
            return (string) (static::find($key)?->value ?? $default);
        });
    }

    public static function set(string $key, ?string $value, string $description = ''): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description],
        );

        Cache::forget("system_setting:{$key}");
    }
}
