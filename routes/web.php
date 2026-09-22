<?php

use App\Http\Controllers\AttendantPanelController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\TicketIssueController;
use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\UserController;
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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/clinica', [ClinicController::class, 'show'])->name('clinic.show');
    Route::put('/clinica', [ClinicController::class, 'update'])->name('clinic.update');
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('/mesas', [DeskController::class, 'index'])->name('desks.index');
    Route::get('/tipos-de-senha', [TicketTypeController::class, 'index'])->name('ticket-types.index');
    Route::get('/emitir-senha', [TicketIssueController::class, 'create'])->name('tickets.issue');
    Route::get('/atendimento', AttendantPanelController::class)->name('attendant.panel');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
