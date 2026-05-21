<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');
Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->name('login');

Route::get('/cadastro', [RegisterController::class, 'create'])->name('cadastro');
Route::post('/cadastro', [RegisterController::class, 'store'])->name('cadastro');

// Password Reset
Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

// Google OAuth
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::middleware('auth')->group(function () {
    Route::any('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/home', HomeController::class)->name('home');

    Route::livewire('/users', 'pages::users.index')
        ->name('users.index')
        ->middleware('can:admin');

    // Módulo de Gestão de Turmas e Alunos
    Route::livewire('/classrooms', 'pages::classrooms.index')->name('classrooms.index');
    Route::livewire('/classrooms/{classroom}', 'pages::classrooms.show')->name('classrooms.show');
    Route::livewire('/students', 'pages::students.index')->name('students.index');

    // Módulo de Geração de Provas (IA)
    Route::livewire('/exams', 'pages::exams.index')->name('exams.index');
    Route::livewire('/exams/create', 'pages::exams.create')->name('exams.create');
    Route::livewire('/exams/{exam}', 'pages::exams.show')->name('exams.show');

    // Logs da IA
    Route::livewire('/ai-logs', 'pages::ai-logs.index')->name('ai-logs.index');

    // Módulo de Correção de Provas
    Route::livewire('/evaluations', 'pages::evaluations.index')->name('evaluations.index');
    Route::livewire('/evaluations/create', 'pages::evaluations.create')->name('evaluations.create');
    Route::livewire('/evaluations/{evaluation}', 'pages::evaluations.show')->name('evaluations.show');
    // Módulo de Gerenciamento de Tarefas em Background
    Route::livewire('/tasks', 'pages::tasks.index')->name('tasks.index');

    // Módulo de Chamada Online
    Route::livewire('/attendance', 'pages::attendance.index')->name('attendance.index');
    Route::livewire('/attendance/{session:uuid}', 'pages::attendance.show')->name('attendance.show');

    // Módulo de Perfil do Usuário
    Route::livewire('/profile', 'pages::profile.index')->name('profile');

    // Application Health (Production Only)
    Route::livewire('/health', 'pages::health.index')
        ->name('health')
        ->middleware('can:admin');
});

// Rota Pública do Aluno para registrar presença na Chamada
Route::livewire('/c/{uuid}', 'pages::attendance.student-signup')->name('attendance.student-signup');
