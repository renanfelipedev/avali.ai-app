<?php

namespace App\Models;

use Database\Factories\GeminiApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'key',
    'is_active',
    'is_default',
    'priority',
    'status',
    'rate_limited_until',
    'last_used_at',
    'last_tested_at',
    'last_error_message',
    'total_requests',
    'successful_requests',
    'failed_requests',
])]
#[Hidden(['key'])]
class GeminiApiKey extends Model
{
    /** @use HasFactory<GeminiApiKeyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer',
            'rate_limited_until' => 'datetime',
            'last_used_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'total_requests' => 'integer',
            'successful_requests' => 'integer',
            'failed_requests' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (GeminiApiKey $key) {
            if ($key->is_default) {
                // Se esta chave foi marcada como padrão, desmarca as outras
                static::where('id', '!=', $key->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Retorna a chave de forma mascarada para exibição segura.
     * Ex: AIzaSy...Ab12
     */
    public function maskedKey(): string
    {
        try {
            $rawKey = $this->key;
            if (empty($rawKey)) {
                return '—';
            }

            $length = strlen($rawKey);
            if ($length <= 10) {
                return Str::mask($rawKey, '*', 2, -2);
            }

            return substr($rawKey, 0, 6).'...'.substr($rawKey, -4);
        } catch (\Throwable) {
            return '••••••••••••';
        }
    }

    /**
     * Verifica se a chave está disponível para uso imediato.
     */
    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->status === 'invalid') {
            return false;
        }

        if ($this->rate_limited_until && $this->rate_limited_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Scope para chaves ativas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para chaves disponíveis (ativas e sem bloqueio de cota ativo).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('rate_limited_until')
                    ->orWhere('rate_limited_until', '<=', now());
            })
            ->where('status', '!=', 'invalid');
    }

    /**
     * Registra o sucesso de uma requisição.
     */
    public function recordSuccess(): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'status' => 'active',
            'rate_limited_until' => null,
            'last_error_message' => null,
            'total_requests' => $this->total_requests + 1,
            'successful_requests' => $this->successful_requests + 1,
        ])->saveQuietly();
    }

    /**
     * Registra uma falha de requisição.
     */
    public function recordFailure(string $errorMessage, bool $isRateLimit = false, bool $isInvalid = false): void
    {
        $status = $this->status;
        $rateLimitedUntil = $this->rate_limited_until;

        if ($isInvalid) {
            $status = 'invalid';
        } elseif ($isRateLimit) {
            $status = 'rate_limited';
            $rateLimitedUntil = now()->addMinutes(5);
        } else {
            $status = 'error';
        }

        $this->forceFill([
            'last_used_at' => now(),
            'status' => $status,
            'rate_limited_until' => $rateLimitedUntil,
            'last_error_message' => Str::limit($errorMessage, 1000),
            'total_requests' => $this->total_requests + 1,
            'failed_requests' => $this->failed_requests + 1,
        ])->saveQuietly();
    }
}
