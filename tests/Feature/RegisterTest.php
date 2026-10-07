<?php

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.recaptcha.secret_key', 'test-secret-key');
    Config::set('services.recaptcha.site_key', 'test-site-key');
    Config::set('services.recaptcha.min_score', 0.5);
});

test('cadastro screen can be rendered', function () {
    $response = $this->get(route('cadastro'));

    $response->assertOk();
    $response->assertSee('Cadastre-se');
    $response->assertSee('g-recaptcha-response');
});

test('user can register with valid recaptcha', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
        ]),
    ]);

    $response = $this->post(route('cadastro'), [
        'name' => 'Novo Usuário',
        'email' => 'novo@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'g-recaptcha-response' => 'valid-recaptcha-token',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');

    $this->assertDatabaseHas('users', [
        'email' => 'novo@example.com',
        'name' => 'Novo Usuário',
    ]);
});

test('registration fails if recaptcha verification fails', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $response = $this->post(route('cadastro'), [
        'name' => 'Bot Test',
        'email' => 'bot@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'g-recaptcha-response' => 'fake-token',
    ]);

    $response->assertSessionHasErrors(['g-recaptcha-response']);
    $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
});

test('registration fails if recaptcha score is below threshold', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.2,
            'action' => 'register',
        ]),
    ]);

    $response = $this->post(route('cadastro'), [
        'name' => 'Bot Suspeito',
        'email' => 'bot-suspeito@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'g-recaptcha-response' => 'low-score-token',
    ]);

    $response->assertSessionHasErrors(['g-recaptcha-response']);
    $this->assertDatabaseMissing('users', ['email' => 'bot-suspeito@example.com']);
});

test('honeypot silently drops bot registration without creating user', function () {
    $response = $this->post(route('cadastro'), [
        'name' => 'Bot Honeypot',
        'email' => 'honeypot-bot@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'website_hp' => 'http://spam-site.com',
        'g-recaptcha-response' => 'token',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    $this->assertDatabaseMissing('users', ['email' => 'honeypot-bot@example.com']);
});

test('user can register without recaptcha token if recaptcha is disabled via system settings', function () {
    SystemSetting::set('recaptcha_enabled', false);

    $response = $this->post(route('cadastro'), [
        'name' => 'Usuário Sem Recaptcha',
        'email' => 'sem-recaptcha@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('users', [
        'email' => 'sem-recaptcha@example.com',
    ]);
});

test('registration is blocked when allow_registration is disabled in settings', function () {
    SystemSetting::set('allow_registration', false);

    $getResp = $this->get(route('cadastro'));
    $getResp->assertRedirect(route('login'));
    $getResp->assertSessionHas('warning');

    $postResp = $this->post(route('cadastro'), [
        'name' => 'Tentativa Bloqueada',
        'email' => 'bloqueado@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);
    $postResp->assertRedirect(route('login'));
    $postResp->assertSessionHas('warning');

    $this->assertDatabaseMissing('users', ['email' => 'bloqueado@example.com']);
});

test('registered user is active when auto_activate_users is enabled and receives teacher role only', function () {
    SystemSetting::set('recaptcha_enabled', false);
    SystemSetting::set('auto_activate_users', true);

    Role::firstOrCreate(['slug' => 'teacher'], ['name' => 'Professor', 'description' => 'Professor']);
    Role::firstOrCreate(['slug' => 'student'], ['name' => 'Estudante', 'description' => 'Estudante']);
    Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador', 'description' => 'Administrador']);

    $response = $this->post(route('cadastro'), [
        'name' => 'Professor Ativo',
        'email' => 'ativo@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Cadastro realizado com sucesso! Sua conta já está ativa.');

    $user = User::where('email', 'ativo@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole(UserRole::TEACHER))->toBeTrue();
    expect($user->hasRole(UserRole::STUDENT))->toBeFalse();
    expect($user->hasRole(UserRole::ADMIN))->toBeFalse();
});

test('registered user is inactive when auto_activate_users is disabled and receives teacher role', function () {
    SystemSetting::set('recaptcha_enabled', false);
    SystemSetting::set('auto_activate_users', false);

    Role::firstOrCreate(['slug' => 'teacher'], ['name' => 'Professor', 'description' => 'Professor']);

    $response = $this->post(route('cadastro'), [
        'name' => 'Professor Pendente',
        'email' => 'pendente@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');

    $user = User::where('email', 'pendente@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->is_active)->toBeFalse();
    expect($user->hasRole(UserRole::TEACHER))->toBeTrue();
});
