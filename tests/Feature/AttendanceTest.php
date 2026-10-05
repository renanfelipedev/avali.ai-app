<?php

use App\Mail\AttendanceReportMail;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Student;
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

test('teacher can end an active attendance session', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-uuid-1234',
        'user_id' => $user->id,
        'class_name' => 'Turma Teste',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session])
        ->call('endSession')
        ->assertHasNoErrors();

    $session->refresh();
    expect($session->is_active)->toBeFalse();
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

test('student name is automatically matched and accented when enrolled in classroom', function () {
    $user = User::factory()->create();
    $classroom = Classroom::create([
        'user_id' => $user->id,
        'name' => 'Turma de História',
    ]);

    $student = Student::create([
        'user_id' => $user->id,
        'name' => 'João Victor Gonçalves',
        'email' => 'joao@example.com',
    ]);
    $classroom->students()->attach($student->id);

    $session = AttendanceSession::create([
        'uuid' => 'test-accent-uuid',
        'user_id' => $user->id,
        'classroom_id' => $classroom->id,
        'class_name' => 'Turma de História',
        'is_active' => true,
    ]);

    // Student types without accents: "joao victor goncalves"
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'joao victor goncalves')
        ->call('register')
        ->assertHasNoErrors();

    // The record should be saved with the enrolled student's official accented name
    $this->assertDatabaseHas('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'João Victor Gonçalves',
    ]);

    // And on the show page, the student should be recognized as present, with 0 absentees
    $component = Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session]);

    expect($component->get('absentees'))->toBeEmpty();
});

test('student name receives accent and title case via dictionary when not in classroom', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-standalone-uuid',
        'user_id' => $user->id,
        'class_name' => 'Palestra Geral',
        'is_active' => true,
    ]);

    // Student types name without accents: "cesar augusto araujo"
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'cesar augusto araujo')
        ->call('register')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendance_records', [
        'attendance_session_id' => $session->id,
        'student_name' => 'César Augusto Araújo',
    ]);
});

test('duplicate check prevents registration when name differs only by accents', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-dup-uuid',
        'user_id' => $user->id,
        'class_name' => 'Turma A',
        'is_active' => true,
    ]);

    // First student registers with accent
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'André Luís')
        ->call('register')
        ->assertHasNoErrors();

    // Second attempt without accent should be blocked as duplicate
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('device_id', 'different-device-123')
        ->set('student_name', 'andre luis')
        ->call('register')
        ->assertHasErrors(['student_name']);
});

test('absentees list correctly recognizes students who signed up without accents as present', function () {
    $user = User::factory()->create();
    $classroom = Classroom::create([
        'user_id' => $user->id,
        'name' => '3º Ano B',
    ]);

    $student1 = Student::create(['user_id' => $user->id, 'name' => 'Maria Vitória']);
    $student2 = Student::create(['user_id' => $user->id, 'name' => 'José da Silva']);
    $classroom->students()->attach([$student1->id, $student2->id]);

    $session = AttendanceSession::create([
        'uuid' => 'test-absentee-accents',
        'user_id' => $user->id,
        'classroom_id' => $classroom->id,
        'class_name' => '3º Ano B',
        'is_active' => true,
    ]);

    // Simulate an existing record saved without accents: "maria vitoria"
    AttendanceRecord::create([
        'attendance_session_id' => $session->id,
        'student_name' => 'maria vitoria',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session]);

    $absentees = $component->get('absentees');

    // Only José da Silva should be absent; Maria Vitória should be recognized as present!
    expect($absentees->count())->toBe(1)
        ->and($absentees->first()->name)->toBe('José da Silva');
});

test('teacher can start an attendance session with duration in minutes', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.index')
        ->set('class_name', 'Chamada 15 Minutos')
        ->set('duration_type', '15')
        ->call('startSession')
        ->assertHasNoErrors()
        ->assertRedirect();

    $session = AttendanceSession::where('class_name', 'Chamada 15 Minutos')->first();
    expect($session)->not->toBeNull()
        ->and($session->duration_minutes)->toBe(15)
        ->and($session->duration_hours)->toBe(1)
        ->and($session->formatted_duration)->toBe('15 min');
});

test('teacher can start session with custom geofence radius', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.index')
        ->set('class_name', 'Chamada com GPS 50m')
        ->set('require_geolocation', true)
        ->set('radius_meters', 50)
        ->call('startSession')
        ->assertHasNoErrors();

    $session = AttendanceSession::where('class_name', 'Chamada com GPS 50m')->first();
    expect($session)->not->toBeNull()
        ->and($session->require_geolocation)->toBeTrue()
        ->and($session->radius_meters)->toBe(50);
});

test('teacher can require PIN code and student must provide matching PIN', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.index')
        ->set('class_name', 'Chamada com PIN')
        ->set('require_pin', true)
        ->set('pin_code', '7842')
        ->call('startSession')
        ->assertHasNoErrors();

    $session = AttendanceSession::where('class_name', 'Chamada com PIN')->first();
    expect($session)->not->toBeNull()
        ->and($session->require_pin)->toBeTrue()
        ->and($session->pin_code)->toBe('7842');

    // Attempt registration with wrong PIN
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'Carlos Alberto')
        ->set('pin_input', '0000')
        ->call('register')
        ->assertHasErrors(['pin_input']);

    expect($session->records()->count())->toBe(0);

    // Attempt registration with correct PIN
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'Carlos Alberto')
        ->set('pin_input', '7842')
        ->call('register')
        ->assertHasNoErrors();

    expect($session->records()->count())->toBe(1);
});

test('only_enrolled mode blocks non-enrolled students and allows enrolled students', function () {
    $user = User::factory()->create();
    $classroom = Classroom::create([
        'user_id' => $user->id,
        'name' => 'Turma Restrita',
    ]);

    $student = Student::create(['user_id' => $user->id, 'name' => 'Ana Clara Ferreira']);
    $classroom->students()->attach($student->id);

    $session = AttendanceSession::create([
        'uuid' => 'test-strict-uuid',
        'user_id' => $user->id,
        'classroom_id' => $classroom->id,
        'class_name' => 'Turma Restrita',
        'only_enrolled' => true,
        'is_active' => true,
    ]);

    // Student not in classroom should be blocked
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'Estranho de Outra Turma')
        ->call('register')
        ->assertHasErrors(['student_name']);

    expect($session->records()->count())->toBe(0);

    // Enrolled student should be accepted
    Livewire::test('pages::attendance.student-signup', ['uuid' => $session->uuid])
        ->set('student_name', 'ana clara ferreira')
        ->call('register')
        ->assertHasNoErrors();

    expect($session->records()->count())->toBe(1);
});

test('teacher can export attendance list as CSV', function () {
    $user = User::factory()->create();
    $session = AttendanceSession::create([
        'uuid' => 'test-csv-uuid',
        'user_id' => $user->id,
        'class_name' => 'Turma CSV',
        'is_active' => true,
    ]);

    AttendanceRecord::create([
        'attendance_session_id' => $session->id,
        'student_name' => 'Bruno Henrique',
        'ip_address' => '192.168.1.1',
    ]);

    $response = Livewire::actingAs($user)
        ->test('pages::attendance.show', ['session' => $session])
        ->call('exportCsv');

    $response->assertFileDownloaded();
});

