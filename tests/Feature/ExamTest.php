<?php

use App\Models\Exam;
use App\Models\ExamGenerationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest cannot access exams index', function () {
    $this->get(route('exams.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view exams index', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('exams.index'));

    $response->assertOk();
});

test('authenticated user can view exam detail', function () {
    $user = User::factory()->create();
    $exam = Exam::create([
        'user_id' => $user->id,
        'title' => 'Prova de Teste',
        'description' => 'Descrição',
        'file_path' => 'generated_exams/test_exam.json',
        'original_name' => 'test_exam.json',
        'mime_type' => 'application/json',
        'file_size' => 100,
    ]);

    // Create a dummy JSON file in the public disk
    Storage::disk('public')->put($exam->file_path, json_encode([
        'title' => 'Prova de Teste',
        'objective_questions' => [
            [
                'number' => 1,
                'text' => 'Qual é a resposta correta?',
                'options' => [
                    'a' => 'Opção A',
                    'b' => 'Opção B',
                ],
                'answer' => 'a',
            ],
        ],
        'discursive_questions' => [],
    ]));

    $response = $this->actingAs($user)->get(route('exams.show', $exam));
    $response->assertOk();
});

test('teacher can download exam as markdown or json file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $exam = Exam::create([
        'user_id' => $user->id,
        'title' => 'Prova de Teste',
        'description' => 'Descrição',
        'file_path' => 'generated_exams/test_exam.json',
        'original_name' => 'test_exam.json',
        'mime_type' => 'application/json',
        'file_size' => 100,
    ]);

    Storage::disk('public')->put($exam->file_path, '{"title": "Test JSON"}');

    Livewire::actingAs($user)
        ->test('pages::exams.show', ['exam' => $exam])
        ->call('downloadMarkdown')
        ->assertFileDownloaded('test_exam.json');
});

test('teacher can download exam as pdf file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $exam = Exam::create([
        'user_id' => $user->id,
        'title' => 'Prova de Teste',
        'description' => 'Descrição',
        'file_path' => 'generated_exams/test_exam.json',
        'original_name' => 'test_exam.json',
        'mime_type' => 'application/json',
        'file_size' => 100,
    ]);

    $examJsonContent = json_encode([
        'title' => 'Prova de Teste',
        'objective_questions' => [
            [
                'number' => 1,
                'text' => 'Pergunta 1',
                'options' => [
                    'a' => 'Alt A',
                    'b' => 'Alt B',
                ],
                'answer' => 'a',
            ],
        ],
        'discursive_questions' => [
            [
                'number' => 2,
                'text' => 'Pergunta Discursiva',
                'answer_key' => 'Esperado X',
            ],
        ],
    ]);

    Storage::disk('public')->put($exam->file_path, $examJsonContent);

    $response = Livewire::actingAs($user)
        ->test('pages::exams.show', ['exam' => $exam])
        ->call('downloadPdf');

    $response->assertStatus(200);
});

test('notifications center alerts user when active task completes', function () {
    $user = User::factory()->create();

    // Create an active exam generation task
    $task = ExamGenerationRequest::create([
        'user_id' => $user->id,
        'topics' => ['Tema Teste'],
        'questions_count' => 5,
        'status' => 'processing',
        'supporting_materials' => [],
    ]);

    // Test the livewire component
    $component = Livewire::actingAs($user)
        ->test('components.notifications-center');

    // Currently the task is active, so activeCount > 0
    $component->assertSet('activeGenerationIds', [$task->id]);

    // Mark the task as completed
    $task->update(['status' => 'completed']);

    // Trigger the task check
    $component->call('checkTasks');

    // The active task list should now be empty
    $component->assertSet('activeGenerationIds', []);
});
