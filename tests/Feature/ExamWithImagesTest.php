<?php

use App\Jobs\GenerateExamJob;
use App\Models\Exam;
use App\Models\ExamGenerationRequest;
use App\Models\User;
use App\Services\AiService;
use App\Services\ExamGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('teacher can upload question images when creating an exam request', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $image1 = UploadedFile::fake()->image('tirinha_mafalda.png', 400, 300);
    $image2 = UploadedFile::fake()->image('grafico_funcao.jpg', 500, 350);

    Livewire::actingAs($user)
        ->test('pages::exams.create')
        ->set('title', 'Prova com Tirinhas')
        ->set('objective_count', 3)
        ->set('discursive_count', 1)
        ->set('topics', 'Interpretação de Texto, Charges e Tirinhas')
        ->set('question_images', [$image1, $image2])
        ->call('save')
        ->assertRedirect(route('tasks.index'));

    Queue::assertPushed(GenerateExamJob::class, function ($job) use ($user) {
        $request = ExamGenerationRequest::where('user_id', $user->id)->first();
        expect($request)->not->toBeNull()
            ->and($request->supporting_materials)->toHaveCount(2)
            ->and($request->supporting_materials[0]['is_question_image'])->toBeTrue()
            ->and($request->supporting_materials[0]['original_name'])->toBe('tirinha_mafalda.png')
            ->and($request->supporting_materials[1]['is_question_image'])->toBeTrue()
            ->and($request->supporting_materials[1]['original_name'])->toBe('grafico_funcao.jpg');

        Storage::disk('public')->assertExists($request->supporting_materials[0]['path']);
        Storage::disk('public')->assertExists($request->supporting_materials[1]['path']);

        return true;
    });
});

test('teacher can remove uploaded question images individually before submitting', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $image1 = UploadedFile::fake()->image('img1.png');
    $image2 = UploadedFile::fake()->image('img2.png');

    $component = Livewire::actingAs($user)
        ->test('pages::exams.create')
        ->set('question_images', [$image1, $image2])
        ->assertCount('question_images', 2)
        ->call('removeQuestionImage', 0)
        ->assertCount('question_images', 1)
        ->call('clearQuestionImages')
        ->assertCount('question_images', 0);
});

test('exam generation service associates question images based on image reference in AI response', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    // Create fake image file
    $imagePath = 'exam_images/tirinha_teste.png';
    Storage::disk('public')->put($imagePath, 'fake-image-bytes');

    $request = ExamGenerationRequest::create([
        'user_id' => $user->id,
        'title' => 'Prova de Português',
        'questions_count' => 2,
        'objective_count' => 1,
        'discursive_count' => 1,
        'topics' => ['Interpretação de Tirinha'],
        'supporting_materials' => [
            [
                'path' => $imagePath,
                'original_name' => 'tirinha_teste.png',
                'mime_type' => 'image/png',
                'size' => 1024,
                'is_question_image' => true,
            ],
        ],
        'status' => 'pending',
    ]);

    // Mock Gemini response returning JSON with image_reference
    $mockAiResponse = new class
    {
        public $usageMetadata;

        public function __construct()
        {
            $this->usageMetadata = (object) ['totalTokenCount' => 350];
        }

        public function text()
        {
            return json_encode([
                'title' => 'Prova de Português',
                'questions' => [
                    [
                        'type' => 'objective',
                        'statement' => 'Com base na tirinha acima, qual o efeito de humor gerado?',
                        'image_reference' => 'tirinha_teste.png',
                        'image_caption' => 'Tirinha da Mafalda sobre leitura',
                        'options' => ['A) Opção 1', 'B) Opção 2', 'C) Opção 3', 'D) Opção 4', 'E) Opção 5'],
                        'correct_answer' => 'A',
                        'explanation' => 'Explicação pedagógica...',
                    ],
                    [
                        'type' => 'discursive',
                        'statement' => 'Explique a crítica social presente no diálogo da tirinha.',
                        'image_reference' => 'Imagem 1',
                        'image_caption' => 'Tirinha 1',
                        'expected_answer' => 'Crítica ao consumismo.',
                        'evaluation_criteria' => 'Identificar o tema principal.',
                    ],
                ],
            ]);
        }
    };

    $mockAiService = mock(AiService::class);
    $mockAiService->shouldReceive('generateContent')
        ->once()
        ->andReturn($mockAiResponse);

    $service = new ExamGenerationService($mockAiService);
    $result = $service->generateExam($request);

    expect($result)->toBeTrue();

    $exam = Exam::where('user_id', $user->id)->latest()->first();
    expect($exam)->not->toBeNull()
        ->and($exam->content_json['questions'][0]['image_path'])->toBe($imagePath)
        ->and($exam->content_json['questions'][0]['image_caption'])->toBe('Tirinha da Mafalda sobre leitura')
        ->and($exam->content_json['questions'][1]['image_path'])->toBe($imagePath);

    // Verify parsed JSON data
    $parsed = $exam->parsed_json_data;
    expect($parsed['objective_questions'][0]['image_path'])->toBe($imagePath)
        ->and($parsed['discursive_questions'][0]['image_path'])->toBe($imagePath);
});

test('exam detail view renders question image when present', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $imagePath = 'exam_images/charge_politica.png';
    Storage::disk('public')->put($imagePath, 'fake-image-bytes');

    $exam = Exam::create([
        'user_id' => $user->id,
        'title' => 'Prova com Imagem',
        'description' => 'Descrição',
        'mime_type' => 'application/json',
        'content_json' => [
            'title' => 'Prova com Imagem',
            'questions' => [
                [
                    'type' => 'objective',
                    'statement' => 'Analise a charge e responda:',
                    'image_path' => $imagePath,
                    'image_caption' => 'Charge 1: Reflexão sobre meio ambiente',
                    'options' => ['Opção A', 'Opção B', 'Opção C', 'Opção D', 'Opção E'],
                    'correct_answer' => 'Opção A',
                ],
            ],
        ],
    ]);

    $response = Livewire::actingAs($user)
        ->test('pages::exams.show', ['exam' => $exam]);

    $response->assertOk()
        ->assertSee('Analise a charge e responda:')
        ->assertSee('Charge 1: Reflexão sobre meio ambiente')
        ->assertSee(Storage::disk('public')->url($imagePath));
});
