<?php

use App\Models\Role;
use App\Models\SystemErrorLog;
use App\Models\User;
use App\Services\ErrorLoggingService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleSeeder::class, PlanSeeder::class]);
});

test('only administrator can access system errors page', function () {
    $adminRole = Role::where('slug', 'admin')->first();
    $regularUser = User::factory()->create();
    $adminUser = User::factory()->create();
    $adminUser->roles()->attach($adminRole);

    // Guest redirected to login
    $this->get(route('system-errors.index'))
        ->assertRedirect(route('login'));

    // Regular user forbidden
    $this->actingAs($regularUser)
        ->get(route('system-errors.index'))
        ->assertForbidden();

    // Administrator allowed
    $this->actingAs($adminUser)
        ->get(route('system-errors.index'))
        ->assertOk();
});

test('only administrator can access ai logs page', function () {
    $adminRole = Role::where('slug', 'admin')->first();
    $regularUser = User::factory()->create();
    $adminUser = User::factory()->create();
    $adminUser->roles()->attach($adminRole);

    // Regular user forbidden
    $this->actingAs($regularUser)
        ->get(route('ai-logs.index'))
        ->assertForbidden();

    // Administrator allowed
    $this->actingAs($adminUser)
        ->get(route('ai-logs.index'))
        ->assertOk();
});

test('error logging service captures exceptions with context and user data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $exception = new RuntimeException('Falha simulada na API ou banco de dados');
    $log = ErrorLoggingService::logException($exception, ['action' => 'test_run'], 'critical');

    expect($log)->not->toBeNull()
        ->and($log->message)->toBe('Falha simulada na API ou banco de dados')
        ->and($log->exception_class)->toBe('RuntimeException')
        ->and($log->level)->toBe('critical')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->context)->toBe(['action' => 'test_run']);
});

test('administrator can search, view and clear errors on system errors page', function () {
    $adminRole = Role::where('slug', 'admin')->first();
    $adminUser = User::factory()->create();
    $adminUser->roles()->attach($adminRole);

    $error1 = SystemErrorLog::create([
        'level' => 'error',
        'exception_class' => 'InvalidArgumentException',
        'message' => 'Parâmetro inválido na chamada de teste',
        'file' => 'app/Services/Test.php',
        'line' => 42,
    ]);

    $error2 = SystemErrorLog::create([
        'level' => 'critical',
        'exception_class' => 'QueryException',
        'message' => 'Timeout de conexão com banco de dados',
        'file' => 'vendor/laravel/framework/Database.php',
        'line' => 100,
    ]);

    Livewire::actingAs($adminUser)
        ->test('pages::system-errors.index')
        ->assertOk()
        ->assertSee('Parâmetro inválido na chamada de teste')
        ->assertSee('Timeout de conexão com banco de dados')
        ->set('search', 'Timeout')
        ->assertSee('Timeout de conexão com banco de dados')
        ->assertDontSee('Parâmetro inválido na chamada de teste')
        ->call('viewDetails', $error2->id)
        ->assertSet('showErrorModal', true)
        ->assertSet('selectedErrorId', $error2->id)
        ->call('deleteError', $error2->id);

    expect(SystemErrorLog::find($error2->id))->toBeNull();

    // Clear all
    Livewire::actingAs($adminUser)
        ->test('pages::system-errors.index')
        ->call('clearAllErrors');

    expect(SystemErrorLog::count())->toBe(0);
});
