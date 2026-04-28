<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'scheduled_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
