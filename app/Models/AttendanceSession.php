<?php

namespace App\Models;

use App\Mail\AttendanceReportMail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[Fillable([
    'uuid',
    'user_id',
    'classroom_id',
    'class_name',
    'is_active',
    'duration_hours',
    'duration_minutes',
    'expires_at',
    'require_geolocation',
    'latitude',
    'longitude',
    'radius_meters',
    'require_pin',
    'pin_code',
    'only_enrolled',
])]
class AttendanceSession extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_hours' => 'integer',
            'duration_minutes' => 'integer',
            'expires_at' => 'datetime',
            'require_geolocation' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'radius_meters' => 'integer',
            'require_pin' => 'boolean',
            'pin_code' => 'string',
            'only_enrolled' => 'boolean',
        ];
    }

    protected $attributes = [
        'is_active' => true,
        'require_geolocation' => false,
        'require_pin' => false,
        'only_enrolled' => false,
    ];

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_minutes) {
            if ($this->duration_minutes < 60) {
                return "{$this->duration_minutes} min";
            }
            $hours = floor($this->duration_minutes / 60);
            $min = $this->duration_minutes % 60;
            return $min > 0 ? "{$hours}h {$min}min" : "{$hours}h";
        }
        if ($this->duration_hours) {
            return "{$this->duration_hours}h";
        }
        return 'Sem limite';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function closeIfExpired(): bool
    {
        if ($this->is_active && $this->isExpired()) {
            $affected = static::where('id', $this->id)
                ->where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['is_active' => false]);

            if ($affected > 0) {
                $this->is_active = false;
                $this->loadMissing(['user', 'records']);

                if ($this->user?->email) {
                    try {
                        Mail::to($this->user->email)
                            ->send(new AttendanceReportMail($this));
                    } catch (\Throwable $e) {
                        Log::error("Erro ao enviar e-mail de fechamento da chamada {$this->id}: ".$e->getMessage());
                    }
                }

                return true;
            }
        }

        return false;
    }

    public static function closeAllExpired(): int
    {
        $expiredSessions = static::with(['user', 'records'])
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $closedCount = 0;
        foreach ($expiredSessions as $session) {
            if ($session->closeIfExpired()) {
                $closedCount++;
            }
        }

        return $closedCount;
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
