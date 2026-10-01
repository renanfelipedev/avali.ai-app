<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class SystemSetting extends Model
{
    /**
     * Prefix used for caching settings.
     */
    protected const CACHE_PREFIX = 'system_setting:';

    /**
     * Retrieve a setting value by key with a fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            return $setting !== null && $setting->value !== null ? $setting->value : $default;
        });
    }

    /**
     * Set or update a setting value and invalidate the cache.
     */
    public static function set(string $key, mixed $value): static
    {
        $stringValue = is_bool($value)
            ? ($value ? '1' : '0')
            : (is_array($value) ? json_encode($value) : (string) $value);

        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue]
        );

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    /**
     * Get a setting cast as a boolean.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key, $default);

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a setting cast as a float.
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $val = static::get($key, $default);

        return (float) $val;
    }

    /**
     * Get a setting cast as an array.
     *
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public static function getArray(string $key, array $default = []): array
    {
        $val = static::get($key, $default);

        if (is_array($val)) {
            return $val;
        }

        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $default;
    }

    /**
     * Forget/delete a setting by key and invalidate the cache.
     */
    public static function forget(string $key): bool
    {
        Cache::forget(self::CACHE_PREFIX.$key);

        return (bool) static::query()->where('key', $key)->delete();
    }
}
