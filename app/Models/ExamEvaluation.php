<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'student_id', 'title', 'answer_key_file_path', 'exam_file_path', 'grading_criteria', 'status', 'google_course_id', 'google_coursework_id'])]
class ExamEvaluation extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExamSubmission::class);
    }

    protected static function booted()
    {
        static::deleting(function ($evaluation) {
            $evaluation->submissions()->delete();
        });
    }
}
