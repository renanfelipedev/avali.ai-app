<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['exam_evaluation_id', 'student_name', 'student_file_path', 'final_grade', 'feedback_data', 'transcription', 'status', 'status_message', 'error_message'])]
class ExamSubmission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'final_grade' => 'decimal:2',
            'feedback_data' => 'json',
        ];
    }

    public function evaluation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExamEvaluation::class, 'exam_evaluation_id');
    }
}
