<?php

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::firstOrCreate(
        ['slug' => UserRole::ADMIN->value],
        ['name' => 'Administrador', 'description' => 'Administrador']
    );
    $this->teacherRole = Role::firstOrCreate(
        ['slug' => UserRole::TEACHER->value],
        ['name' => 'Professor', 'description' => 'Professor']
    );
    $this->studentRole = Role::firstOrCreate(
        ['slug' => UserRole::STUDENT->value],
        ['name' => 'Estudante', 'description' => 'Estudante']
    );

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->roles()->attach($this->adminRole);

    $this->teacher = User::factory()->create(['is_active' => true]);
    $this->teacher->roles()->attach($this->teacherRole);
});

test('non-admin user cannot access users page', function () {
    $response = $this->actingAs($this->teacher)->get(route('users.index'));

    $response->assertForbidden();
});

test('admin can access users page', function () {
    $response = $this->actingAs($this->admin)->get(route('users.index'));

    $response->assertOk();
    $response->assertSee('Gerenciamento de Usuários');
});

test('student role is not available in user creation/editing roles list', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test('pages::users.index');
    $allRoles = $component->viewData('allRoles');

    expect($allRoles->pluck('slug')->all())->not->toContain(UserRole::STUDENT->value);
    expect($allRoles->pluck('slug')->all())->toContain(UserRole::ADMIN->value);
    expect($allRoles->pluck('slug')->all())->toContain(UserRole::TEACHER->value);
});

test('createUser respects auto_activate_users setting', function () {
    $this->actingAs($this->admin);

    // Test with auto_activate_users = false
    SystemSetting::set('auto_activate_users', false);
    Livewire::test('pages::users.index')
        ->call('createUser')
        ->assertSet('is_active', false);

    // Test with auto_activate_users = true
    SystemSetting::set('auto_activate_users', true);
    Livewire::test('pages::users.index')
        ->call('createUser')
        ->assertSet('is_active', true);
});

test('admin can create user with admin role', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::users.index')
        ->call('createUser')
        ->set('name', 'Novo Administrador')
        ->set('email', 'novo.admin@example.com')
        ->set('password', 'password123')
        ->set('is_active', true)
        ->set('selectedRoles', [(string) $this->adminRole->id])
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'novo.admin@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->isAdmin())->toBeTrue();
    expect($newUser->is_active)->toBeTrue();
});

test('student role is automatically stripped if submitted', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::users.index')
        ->call('createUser')
        ->set('name', 'Tentativa Aluno')
        ->set('email', 'aluno.tentativa@example.com')
        ->set('password', 'password123')
        ->set('is_active', true)
        ->set('selectedRoles', [(string) $this->studentRole->id])
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'aluno.tentativa@example.com')->first();
    expect($newUser)->not->toBeNull();
    // Student role was stripped and fallback assigned teacher
    expect($newUser->hasRole(UserRole::STUDENT))->toBeFalse();
    expect($newUser->hasRole(UserRole::TEACHER))->toBeTrue();
});

test('non-admin cannot assign admin role', function () {
    // If a non-admin calls the component directly
    $this->actingAs($this->teacher);

    // Livewire mount throws 403 because user is not admin
    Livewire::test('pages::users.index')
        ->assertForbidden();
});

test('admin cannot delete themselves', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::users.index')
        ->call('deleteUser', $this->admin);

    expect(User::find($this->admin->id))->not->toBeNull();
});

test('admin can delete another user', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::users.index')
        ->call('deleteUser', $this->teacher);

    expect(User::find($this->teacher->id))->toBeNull();
});
