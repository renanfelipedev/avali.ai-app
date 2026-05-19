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
