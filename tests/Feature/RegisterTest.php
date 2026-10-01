<?php

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
