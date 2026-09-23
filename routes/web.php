<?php

use App\Http\Controllers\AttendantPanelController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\ClinicSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\DisplayPanelController;
use App\Http\Controllers\DisplayPanelPlaylistController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\KioskPanelController;
use App\Http\Controllers\MediaItemController;
use App\Http\Controllers\TicketIssueController;
use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\TvPanelController;
use App\Http\Controllers\UnitTicketTypeController;
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

Route::get('/painel/{publicToken}', TvPanelController::class)
    ->where('publicToken', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:60,1')
    ->name('tv.panel');

Route::get('/totem/{publicToken}', KioskPanelController::class)
    ->where('publicToken', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:kiosk-page')
    ->name('kiosk.panel');

Route::middleware(['auth', 'operational'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/clinica', [ClinicController::class, 'show'])->name('clinic.show');
    Route::put('/clinica', [ClinicController::class, 'update'])->name('clinic.update');
    Route::get('/configuracoes', [ClinicSettingsController::class, 'index'])->name('settings.index');
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('/mesas', [DeskController::class, 'index'])->name('desks.index');
    Route::get('/paineis', [DisplayPanelController::class, 'index'])->name('display-panels.index');
    Route::get('/paineis/{panel}/playlist', [DisplayPanelPlaylistController::class, 'edit'])->name('display-panels.playlist');
    Route::get('/midia-tv', [MediaItemController::class, 'index'])->name('media-items.index');
    Route::get('/totens', [KioskController::class, 'index'])->name('kiosks.index');
    Route::get('/tipos-por-unidade', [UnitTicketTypeController::class, 'index'])->name('unit-ticket-types.index');
    Route::get('/tipos-de-senha', [TicketTypeController::class, 'index'])->name('ticket-types.index');
    Route::get('/emitir-senha', [TicketIssueController::class, 'create'])->name('tickets.issue');
    Route::get('/atendimento', AttendantPanelController::class)->name('attendant.panel');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
