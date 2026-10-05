<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\CurrentSalonController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Owner\InvitationController;
use App\Http\Controllers\Owner\MemberController;
use App\Http\Controllers\Owner\SalonController;
use App\Http\Controllers\Owner\TeamController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleLoginController::class, 'callback'])->name('google.callback');
});

// Invitation links arrive by email, so the page itself is public.
Route::get('invitaciones/{token}', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('salones/nuevo', [SalonController::class, 'create'])->name('salons.create');
    Route::post('salones', [SalonController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('salons.store');
    Route::put('salon-actual/{salon}', [CurrentSalonController::class, 'update'])->name('current-salon.update');

    Route::post('invitaciones/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept');

    Route::middleware('salon')->group(function () {
        Route::get('equipo', [TeamController::class, 'index'])->name('team.index');
        Route::post('equipo/invitaciones', [InvitationController::class, 'store'])
            ->middleware('throttle:invitations')
            ->name('team.invitations.store');
        Route::delete('equipo/invitaciones/{invitation}', [InvitationController::class, 'destroy'])
            ->name('team.invitations.destroy');
        Route::put('equipo/miembros/{member}', [MemberController::class, 'update'])->name('team.members.update');
        Route::delete('equipo/miembros/{member}', [MemberController::class, 'destroy'])->name('team.members.destroy');
    });
});

require __DIR__.'/settings.php';
