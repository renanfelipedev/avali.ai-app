<?php

use App\Jobs\EvaluateSubmissionJob;
use App\Jobs\GenerateExamJob;
use App\Models\Exam;
use App\Models\ExamEvaluation;
use App\Models\ExamGenerationRequest;
use App\Models\ExamSubmission;
use App\Models\GeminiApiKey;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiService;
use App\Services\ExamGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('gemini-api rate limiter is registered and enforces limits', function () {
    $limiter = RateLimiter::limiter('gemini-api');
    expect($limiter)->not->toBeNull();

    $limit = $limiter((object) []);
    expect($limit->maxAttempts)->toBe(20);
});

test('generate exam and evaluate submission jobs have rate limited middleware and backoff configured', function () {
    $request = new ExamGenerationRequest;
    $generateJob = new GenerateExamJob($request);

    expect($generateJob->tries)->toBe(5)
        ->and($generateJob->backoff)->toBe([15, 45, 90, 180, 300]);

    $middleware = $generateJob->middleware();
    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class);

    $evaluation = new ExamEvaluation;
    $submission = new ExamSubmission;
    $evaluateJob = new EvaluateSubmissionJob($evaluation, $submission);

    expect($evaluateJob->tries)->toBe(5)
        ->and($evaluateJob->backoff)->toBe([30, 60, 120, 240, 300]);

    $evalMiddleware = $evaluateJob->middleware();
    expect($evalMiddleware)->toHaveCount(1)
        ->and($evalMiddleware[0])->toBeInstanceOf(RateLimited::class);
});

test('exam generation service re-throws transient quota and high demand errors for automatic queue retry', function () {
    $user = User::factory()->create();

    $request = ExamGenerationRequest::create([
        'user_id' => $user->id,
        'title' => 'Prova Teste',
        'questions_count' => 5,
        'topics' => ['Matemática'],
        'supporting_materials' => [],
        'status' => 'pending',
    ]);

    $mockAiService = mock(AiService::class);
    $mockAiService->shouldReceive('generateContent')
        ->once()
        ->andThrow(new Exception('Resource has been exhausted (e.g. check quota) - 429 Too Many Requests'));

    $service = new ExamGenerationService($mockAiService);

    // Deve relançar a exceção para que o Worker do Laravel acione o retry com backoff
    expect(fn () => $service->generateExam($request))->toThrow(Exception::class);

    $request->refresh();
    expect($request->status)->toBe('processing')
        ->and($request->error_message)->toContain('Alta demanda momentânea');
});

test('teacher cannot exceed daily exam limit per teacher unless admin', function () {
    SystemSetting::set('daily_exam_limit_per_teacher', 2);

    $teacher = User::factory()->create();

    // Cria 2 exames hoje para o professor
    Exam::create([
        'user_id' => $teacher->id,
        'title' => 'Exame 1',
        'description' => 'Desc',
        'created_at' => now(),
    ]);

    Exam::create([
        'user_id' => $teacher->id,
        'title' => 'Exame 2',
        'description' => 'Desc',
        'created_at' => now(),
    ]);

    // Tenta criar o 3º exame
    Livewire::actingAs($teacher)
        ->test('pages::exams.create')
        ->set('title', 'Exame 3')
        ->set('objective_count', 2)
        ->set('discursive_count', 0)
        ->set('topics', 'História')
        ->call('save')
        ->assertHasErrors(['title']);

    // Admin pode criar além do limite
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin->roles()->attach($adminRole);

    Exam::create([
        'user_id' => $admin->id,
        'title' => 'Exame Admin 1',
        'description' => 'Desc',
        'created_at' => now(),
    ]);

    Exam::create([
        'user_id' => $admin->id,
        'title' => 'Exame Admin 2',
        'description' => 'Desc',
        'created_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::exams.create')
        ->set('title', 'Exame Admin 3')
        ->set('objective_count', 2)
        ->set('discursive_count', 0)
        ->set('topics', 'História')
        ->call('save')
        ->assertHasNoErrors(['title']);
});

test('gemini api key is quarantined for 90 seconds on rate limit failure', function () {
    $key = GeminiApiKey::create([
        'name' => 'Chave Teste',
        'key' => 'AIzaSyFakeKey12345678901234567890',
        'is_active' => true,
        'status' => 'active',
    ]);

    $key->recordFailure('Quota exceeded: 429', isRateLimit: true, quarantineSeconds: 90);

    expect($key->status)->toBe('rate_limited')
        ->and($key->rate_limited_until)->not->toBeNull()
        ->and($key->rate_limited_until->diffInSeconds(now()->addSeconds(90)))->toBeLessThan(5);
});
