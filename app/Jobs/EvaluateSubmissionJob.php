<?php

namespace App\Jobs;

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Services\ExamGradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateSubmissionJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120, 240, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ExamEvaluation $evaluation,
        public ExamSubmission $submission
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExamGradingService $gradingService): void
    {
        $gradingService->evaluateSubmission($this->evaluation, $this->submission);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->submission->update([
            'status' => 'error',
            'error_message' => 'Falha após múltiplas tentativas: ' . $exception->getMessage(),
        ]);
    }
}
