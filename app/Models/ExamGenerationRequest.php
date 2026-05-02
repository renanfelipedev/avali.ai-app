<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamGenerationRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'questions_count',
        'topics',
        'supporting_materials',
        'status',
        'error_message',
        'generated_exam_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topics' => 'json',
            'supporting_materials' => 'json',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generatedExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'generated_exam_id');
    }
}
