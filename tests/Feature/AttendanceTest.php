<?php

use App\Mail\AttendanceReportMail;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest cannot access attendance index', function () {
    $this->get(route('attendance.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view attendance index', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('attendance.index'));

    $response->assertOk();
});

test('teacher can start an attendance session via livewire', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.index')
        ->set('class_name', 'Turma Teste 123')
        ->call('startSession')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('attendance_sessions', [
        'user_id' => $user->id,
        'class_name' => 'Turma Teste 123',
        'is_active' => true,
    ]);
});

test('student can sign up to an active attendance session', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-uuid-1234',
        'user_id' => $user->id,
        'class_name' => 'Turma Teste',
        'is_active' => true,
    ]);

    $response = $this->get(route('attendance.student-signup', $session->uuid));
    $response->assertOk();

    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'Aluno Teste')
        ->call('register')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'Aluno Teste',
    ]);
});

test('student cannot sign up to an inactive attendance session', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-uuid-1234',
        'user_id' => $user->id,
        'class_name' => 'Turma Teste',
        'is_active' => false,
    ]);

    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'Aluno Teste')
        ->call('register')
        ->assertHasErrors(['student_name']);

    $this->assertDatabaseMissing('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'Aluno Teste',
    ]);
});

test('student cannot sign up twice from the same device_id in a session', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-uuid-1234',
        'user_id' => $user->id,
        'class_name' => 'Turma Teste',
        'is_active' => true,
    ]);

    // First signup: successful
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('device_id', 'device-xyz')
        ->set('student_name', 'Aluno Um')
        ->call('register')
        ->assertHasNoErrors();

    // Second signup with same device_id: should error out
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('device_id', 'device-xyz')
        ->set('student_name', 'Aluno Dois')
        ->call('register')
        ->assertHasErrors(['student_name']);

    $this->assertDatabaseHas('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'Aluno Um',
    ]);

    $this->assertDatabaseMissing('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'Aluno Dois',
    ]);
});

test('teacher can close session and send email', function () {
    Mail::fake();

    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-uuid-1234',
        'user_id' => $user->id,
        'class_name' => 'Turma Teste',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session])
        ->call('endSessionAndSendMail')
        ->assertHasNoErrors();

    $session->refresh();
    expect($session->is_active)->toBeFalse();

    Mail::assertQueued(AttendanceReportMail::class, function ($mail) use ($user, $session) {
        return $mail->hasTo($user->email) && $mail->session->id === $session->id;
    });
});

test('teacher can start an attendance session with custom duration in hours', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.index')
        ->set('class_name', 'Turma Com Duracao')
        ->set('duration_hours', 4)
        ->call('startSession')
        ->assertHasNoErrors()
        ->assertRedirect();

    $session = AttendanceSession::where('class_name', 'Turma Com Duracao')->first();
    expect($session)->not->toBeNull()
        ->and($session->duration_hours)->toBe(4)
        ->and($session->is_active)->toBeTrue()
        ->and($session->expires_at->isAfter(now()))->toBeTrue()
        ->and((int) round(now()->diffInRealMinutes($session->expires_at) / 60))->toBe(4);
});

test('duration_hours must be a positive integer', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.index')
        ->set('class_name', 'Turma Invalida')
        ->set('duration_hours', 0)
        ->call('startSession')
        ->assertHasErrors(['duration_hours']);
});

test('expired attendance sessions are closed automatically by artisan command', function () {
    Mail::fake();

    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'expired-session-uuid',
        'user_id' => $user->id,
        'class_name' => 'Turma Expirada',
        'is_active' => true,
        'duration_hours' => 2,
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('attendance:close-expired')
        ->assertSuccessful();

    $session->refresh();
    expect($session->is_active)->toBeFalse();

    Mail::assertQueued(AttendanceReportMail::class, function ($mail) use ($user, $session) {
        return $mail->hasTo($user->email) && $mail->session->id === $session->id;
    });
});

test('student cannot sign up to an attendance session that has expired', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-expired-signup',
        'user_id' => $user->id,
        'class_name' => 'Turma Expirada',
        'is_active' => true,
        'duration_hours' => 1,
        'expires_at' => now()->subSeconds(30),
    ]);

    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'Aluno Atrasado')
        ->call('register')
        ->assertHasErrors(['student_name']);

    $session->refresh();
    expect($session->is_active)->toBeFalse();

    $this->assertDatabaseMissing('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'Aluno Atrasado',
    ]);
});

test('teacher viewing expired session automatically closes it and triggers mail', function () {
    Mail::fake();

    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-expired-view',
        'user_id' => $user->id,
        'class_name' => 'Turma Expirada View',
        'is_active' => true,
        'duration_hours' => 1,
        'expires_at' => now()->subHour(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session]);

    $session->refresh();
    expect($session->is_active)->toBeFalse();

    Mail::assertQueued(AttendanceReportMail::class, function ($mail) use ($user, $session) {
        return $mail->hasTo($user->email) && $mail->session->id === $session->id;
    });
});

test('teacher can update duration_hours on attendance show page', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-update-duration',
        'user_id' => $user->id,
        'class_name' => 'Turma Update',
        'is_active' => true,
        'duration_hours' => 1,
        'expires_at' => now()->addHour(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session])
        ->set('new_class_name', 'Turma Update')
        ->set('new_duration_hours', 5)
        ->call('updateClassroom')
        ->assertHasNoErrors();

    $session->refresh();
    expect($session->duration_hours)->toBe(5)
        ->and((int) round(now()->diffInRealMinutes($session->expires_at) / 60))->toBe(5);
});
