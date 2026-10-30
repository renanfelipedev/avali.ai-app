<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('students listing is ordered alphabetically by name', function () {
    $teacher = User::factory()->create();

    Student::create(['user_id' => $teacher->id, 'name' => 'Zélia Cardoso']);
    Student::create(['user_id' => $teacher->id, 'name' => 'Ana Beatriz']);
    Student::create(['user_id' => $teacher->id, 'name' => 'Carlos Eduardo']);

    $component = Livewire::actingAs($teacher)
        ->test('pages::students.index');

    $students = $component->viewData('students');

    expect($students->pluck('name')->all())->toBe([
        'Ana Beatriz',
        'Carlos Eduardo',
        'Zélia Cardoso',
    ]);
});

test('users listing is ordered alphabetically by name', function () {
    $adminRole = Role::firstOrCreate(['slug' => UserRole::ADMIN->value], ['name' => 'Administrador']);

    $admin = User::factory()->create(['name' => 'Carlos Admin']);
    $admin->roles()->attach($adminRole);

    User::factory()->create(['name' => 'Zilda Professora']);
    User::factory()->create(['name' => 'Alice Professora']);
    User::factory()->create(['name' => 'Bernardo Professor']);

    $component = Livewire::actingAs($admin)
        ->test('pages::users.index');

    $users = $component->viewData('users');

    expect($users->pluck('name')->all())->toBe([
        'Alice Professora',
        'Bernardo Professor',
        'Carlos Admin',
        'Zilda Professora',
    ]);
});

test('classrooms listing is ordered alphabetically by name', function () {
    $teacher = User::factory()->create();

    Classroom::create(['user_id' => $teacher->id, 'name' => 'Turma C - 3º Ano']);
    Classroom::create(['user_id' => $teacher->id, 'name' => 'Turma A - 1º Ano']);
    Classroom::create(['user_id' => $teacher->id, 'name' => 'Turma B - 2º Ano']);

    $component = Livewire::actingAs($teacher)
        ->test('pages::classrooms.index');

    $classrooms = $component->viewData('classrooms');

    expect($classrooms->pluck('name')->all())->toBe([
        'Turma A - 1º Ano',
        'Turma B - 2º Ano',
        'Turma C - 3º Ano',
    ]);
});
