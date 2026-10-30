<?php

namespace App\Jobs;

use App\Models\ExamGenerationRequest;
use App\Services\ExamGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class GenerateExamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = [15, 45, 90, 180, 300];

    public $timeout = 300;

    protected $request;

    public function __construct(ExamGenerationRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(ExamGenerationService $service): void
    {
        $this->request->refresh();
        if ($this->request->status === 'completed' || ! empty($this->request->generated_exam_id)) {
            return;
        }

        $service->generateExam($this->request);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->request->refresh();
        if ($this->request->status !== 'completed' && empty($this->request->generated_exam_id)) {
            $this->request->update([
                'status' => 'error',
                'error_message' => 'O processamento expirou ou falhou: '.$exception->getMessage(),
            ]);
        }
    }
}
