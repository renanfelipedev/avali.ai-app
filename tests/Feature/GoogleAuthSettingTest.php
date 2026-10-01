<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('google login button is visible when google_login_enabled is true', function () {
    SystemSetting::set('google_login_enabled', true);

    $loginResp = $this->get(route('login'));
    $loginResp->assertOk();
    $loginResp->assertSee('Entrar com o Google');

    $registerResp = $this->get(route('cadastro'));
    $registerResp->assertOk();
    $registerResp->assertSee('Cadastrar com o Google');
});

test('google login button is hidden when google_login_enabled is false', function () {
    SystemSetting::set('google_login_enabled', false);

    $loginResp = $this->get(route('login'));
    $loginResp->assertOk();
    $loginResp->assertDontSee('Entrar com o Google');

    $registerResp = $this->get(route('cadastro'));
    $registerResp->assertOk();
    $registerResp->assertDontSee('Cadastrar com o Google');
});

test('accessing google auth redirect is blocked when google_login_enabled is false', function () {
    SystemSetting::set('google_login_enabled', false);

    $response = $this->get(route('auth.google'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email']);
});

test('accessing google auth callback is blocked when google_login_enabled is false', function () {
    SystemSetting::set('google_login_enabled', false);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email']);
});
