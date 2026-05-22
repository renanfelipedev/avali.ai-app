<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attendance_session_id', 'student_name', 'ip_address', 'user_agent', 'device_id', 'distance_meters', 'is_valid_location'])]
class AttendanceRecord extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_valid_location' => 'boolean',
            'distance_meters' => 'integer',
        ];
    }
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }
}
