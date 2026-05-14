<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');
Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->name('login');

Route::get('/cadastro', [RegisterController::class, 'create'])->name('cadastro');
Route::post('/cadastro', [RegisterController::class, 'store'])->name('cadastro');

Route::middleware('auth')->group(function () {
    Route::any('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/home', HomeController::class)->name('home');

    Route::livewire('/users', 'pages::users.index')
        ->name('users.index')
        ->middleware('can:admin');

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
});
