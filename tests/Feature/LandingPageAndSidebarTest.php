<?php

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('landing page loads successfully with updated system information', function () {
    $response = $this->get(route('welcome'));

    $response->assertOk();
    $response->assertSee('avali.ai');
    $response->assertSee('Chamada Online Anti-Fraude com Geofencing GPS');
    $response->assertSee('PDF e Word (.docx)');
    $response->assertSee('Correção Automatizada de Avaliações com Visão Computacional');
    $response->assertSee('Geração de Provas Personalizadas com Suporte Multimodal');
    $response->assertSee('Gestão Ágil de Turmas e Alunos com Importação em Lote');
    $response->assertSee('Gemini 3.8 Flash');
    $response->assertSee('Dúvidas Frequentes');
});

test('sidebar contains navigation items for turmas, alunos, provas and chamadas', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertSee('Turmas');
    $response->assertSee('Alunos');
    $response->assertSee('Chamada Online');
    $response->assertSee('Ver Provas');
    $response->assertSee('Nova Prova');
});

test('teacher can register a new student directly from students index component', function () {
    $user = User::factory()->create();
    $classroom = Classroom::create([
        'user_id' => $user->id,
        'name' => 'Turma de Teste 101',
    ]);

    Livewire::actingAs($user)
        ->test('pages::students.index')
        ->set('name', 'joao da silva')
        ->set('email', 'joao@escola.com')
        ->set('institution', 'Escola Modelo')
        ->set('selectedClassrooms', [$classroom->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false);

    $this->assertDatabaseHas('students', [
        'user_id' => $user->id,
        'email' => 'joao@escola.com',
        'institution' => 'Escola Modelo',
    ]);

    $student = Student::where('email', 'joao@escola.com')->first();
    expect($student)->not->toBeNull();
    expect($student->classrooms)->toHaveCount(1);
    expect($student->classrooms->first()->id)->toBe($classroom->id);
});

test('students index component opens create modal when query param criar is present', function () {
    $user = User::factory()->create();

    // With criar query param
    Livewire::actingAs($user)
        ->withQueryParams(['criar' => '1'])
        ->test('pages::students.index')
        ->assertSet('showCreateModal', true);
});

test('classrooms index component opens create modal when query param criar is present', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['criar' => '1'])
        ->test('pages::classrooms.index')
        ->assertSet('showCreateModal', true);
});

test('home page renders teacher metrics and quick registration action cards', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertSee('Suas Turmas');
    $response->assertSee('Alunos Cadastrados');
    $response->assertSee('Provas Geradas');
    $response->assertSee('Ações Rápidas de Cadastro');
    $response->assertSee('Cadastrar Nova Turma');
    $response->assertSee('Cadastrar Novo Aluno');
    $response->assertSee('Iniciar Chamada Online');
});
