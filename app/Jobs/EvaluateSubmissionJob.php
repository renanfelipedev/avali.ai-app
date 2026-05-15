<?php

namespace App\Jobs;

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Services\ExamGradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
    public function handle(ExamGradingService $service): void
    {
        // Service already handles internal status updates and transient error re-throws
        $service->evaluateSubmission($this->evaluation, $this->submission);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("EvaluateSubmissionJob FAILED for Submission #{$this->submission->id}: " . $exception->getMessage(), [
            'submission_id' => $this->submission->id,
            'trace' => $exception->getTraceAsString()
        ]);

        // Only update if not already marked as error by the service
        if ($this->submission->status !== 'error') {
            $this->submission->update([
                'status' => 'error',
                'error_message' => "Falha crítica no worker: " . $exception->getMessage(),
                'status_message' => 'Falha técnica no processamento.'
            ]);
        }
    }
}
