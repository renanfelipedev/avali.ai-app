<?php

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'Admin']);
    $this->teacherRole = Role::firstOrCreate(['slug' => 'teacher'], ['name' => 'Teacher', 'description' => 'Teacher']);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->roles()->attach($this->adminRole);

    $this->regularUser = User::factory()->create(['is_active' => true]);
    $this->regularUser->roles()->attach($this->teacherRole);
});

test('guest cannot access settings page', function () {
    $response = $this->get(route('settings.index'));

    $response->assertRedirect(route('login'));
});

test('non-admin user cannot access settings page', function () {
    $response = $this->actingAs($this->regularUser)->get(route('settings.index'));

    $response->assertForbidden();
});

test('admin can access settings page', function () {
    $response = $this->actingAs($this->admin)->get(route('settings.index'));

    $response->assertOk();
    $response->assertSee('Configurações do Sistema');
    $response->assertSee('Google reCAPTCHA v3');
});

test('admin can update security settings via livewire', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::settings.index')
        ->set('recaptcha_enabled', false)
        ->set('recaptcha_site_key', 'custom-site-key')
        ->set('recaptcha_secret_key', 'custom-secret-key')
        ->set('recaptcha_min_score', 0.8)
        ->set('allow_registration', false)
        ->set('google_login_enabled', false)
        ->call('saveSecurity')
        ->assertHasNoErrors();

    expect(SystemSetting::getBool('recaptcha_enabled'))->toBeFalse();
    expect(SystemSetting::get('recaptcha_site_key'))->toBe('custom-site-key');
    expect(SystemSetting::get('recaptcha_secret_key'))->toBe('custom-secret-key');
    expect(SystemSetting::getFloat('recaptcha_min_score'))->toBe(0.8);
    expect(SystemSetting::getBool('allow_registration'))->toBeFalse();
    expect(SystemSetting::getBool('google_login_enabled'))->toBeFalse();
});

test('admin can update general settings via livewire', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::settings.index')
        ->set('tab', 'general')
        ->set('maintenance_banner_enabled', true)
        ->set('maintenance_banner_message', 'Aviso de Manutenção Programada')
        ->set('daily_exam_limit_per_teacher', 50)
        ->call('saveGeneral')
        ->assertHasNoErrors();

    expect(SystemSetting::getBool('maintenance_banner_enabled'))->toBeTrue();
    expect(SystemSetting::get('maintenance_banner_message'))->toBe('Aviso de Manutenção Programada');
    expect((int) SystemSetting::get('daily_exam_limit_per_teacher'))->toBe(50);
});

test('admin can test recaptcha secret key with google', function () {
    $this->actingAs($this->admin);

    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'], // Secret key foi aceita!
        ]),
    ]);

    Livewire::test('pages::settings.index')
        ->set('recaptcha_secret_key', 'valid-secret-key')
        ->call('testRecaptchaKeys')
        ->assertSet('recaptchaTestResult.success', true);
});
