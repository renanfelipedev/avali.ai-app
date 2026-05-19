<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('forgot password screen can be rendered', function () {
    $response = $this->get(route('password.request'));
    $response->assertOk();
});

test('forgot password request with unregistered email fails', function () {
    $response = $this->post(route('password.email'), [
        'email' => 'unknown@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('forgot password request with valid email creates token and sends email', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'professor@example.com',
        'name' => 'Professor Teste',
    ]);

    $response = $this->post(route('password.email'), [
        'email' => 'professor@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => 'professor@example.com',
    ]);

    Mail::assertQueued(ResetPasswordMail::class, function ($mail) {
        return $mail->hasTo('professor@example.com') && $mail->userName === 'Professor Teste';
    });
});

test('reset password screen can be rendered with valid token', function () {
    $response = $this->get(route('password.reset', [
        'token' => 'some-token',
        'email' => 'professor@example.com',
    ]));

    $response->assertOk();
    $response->assertSee('some-token');
});

test('reset password form updates password and cleans up token', function () {
    $user = User::factory()->create([
        'email' => 'professor@example.com',
        'password' => Hash::make('oldpassword'),
    ]);

    $token = 'secure-token-xyz';

    DB::table('password_reset_tokens')->insert([
        'email' => 'professor@example.com',
        'token' => Hash::make($token),
        'created_at' => now(),
    ]);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'professor@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();

    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => 'professor@example.com',
    ]);
});
