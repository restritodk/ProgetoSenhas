<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware(['auth', 'operational'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
