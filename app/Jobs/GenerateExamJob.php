<?php

namespace App\Jobs;

use App\Models\ExamGenerationRequest;
use App\Services\ExamGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateExamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    protected $request;

    public function __construct(ExamGenerationRequest $request)
    {
        $this->request = $request;
    }

    public function handle(ExamGenerationService $service): void
    {
        $service->generateExam($this->request);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->request->update([
            'status' => 'error',
            'error_message' => 'O processamento expirou ou falhou: ' . $exception->getMessage(),
        ]);
    }
}
