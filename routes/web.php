<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->name('login');

Route::get('/cadastro', [RegisterController::class, 'create'])->name('cadastro');
Route::post('/cadastro', [RegisterController::class, 'store'])->name('cadastro');


Route::middleware('auth')->group(function () {
    Route::any('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/home', HomeController::class)->name('home');
});
