<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class SystemSetting extends Model
{
    /**
     * Recupera o valor de uma configuração.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();
            if (! $setting || $setting->value === null) {
                return $default;
            }

            $decoded = json_decode($setting->value, true);

            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $setting->value;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Salva ou atualiza o valor de uma configuração.
     */
    public static function set(string $key, mixed $value): void
    {
        $serialized = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $serialized]
        );
    }
}
