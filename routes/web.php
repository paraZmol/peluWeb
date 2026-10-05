<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\CurrentSalonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Owner\InvitationController;
use App\Http\Controllers\Owner\MemberController;
use App\Http\Controllers\Owner\SalonBrandingController;
use App\Http\Controllers\Owner\SalonController;
use App\Http\Controllers\Owner\SalonPhotoController;
use App\Http\Controllers\Owner\SalonSettingsController;
use App\Http\Controllers\Owner\ServiceCatalogController;
use App\Http\Controllers\Owner\ServiceController;
use App\Http\Controllers\Owner\TeamController;
use App\Http\Controllers\Public\SalonPageController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public salon profile, shared by the salon on WhatsApp/Instagram and as a QR.
// Generous limit: link-preview bots (WhatsApp, Facebook) also fetch it.
Route::get('s/{salon:slug}', [SalonPageController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('salons.show');

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleLoginController::class, 'callback'])->name('google.callback');
});

// Invitation links arrive by email, so the page itself is public.
Route::get('invitaciones/{token}', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

        Route::get('mi-salon', [SalonSettingsController::class, 'edit'])->name('salon.edit');
        Route::put('mi-salon/datos', [SalonSettingsController::class, 'updateProfile'])->name('salon.profile.update');
        Route::put('mi-salon/ubicacion', [SalonSettingsController::class, 'updateLocation'])->name('salon.location.update');
        Route::put('mi-salon/color', [SalonBrandingController::class, 'updateColor'])->name('salon.color.update');

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('mi-salon/logo', [SalonBrandingController::class, 'storeLogo'])->name('salon.logo.store');
            Route::post('mi-salon/portada', [SalonBrandingController::class, 'storeCover'])->name('salon.cover.store');
            Route::post('mi-salon/fotos', [SalonPhotoController::class, 'store'])->name('salon.photos.store');
        });
        Route::delete('mi-salon/logo', [SalonBrandingController::class, 'destroyLogo'])->name('salon.logo.destroy');
        Route::delete('mi-salon/portada', [SalonBrandingController::class, 'destroyCover'])->name('salon.cover.destroy');
        Route::put('mi-salon/fotos/orden', [SalonPhotoController::class, 'reorder'])->name('salon.photos.reorder');
        Route::delete('mi-salon/fotos/{photo}', [SalonPhotoController::class, 'destroy'])->name('salon.photos.destroy');

        Route::get('servicios', [ServiceController::class, 'index'])->name('services.index');
        Route::post('servicios', [ServiceController::class, 'store'])->name('services.store');
        Route::post('servicios/catalogo', [ServiceCatalogController::class, 'store'])->name('services.catalog.store');
        Route::put('servicios/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::patch('servicios/{service}/estado', [ServiceController::class, 'toggle'])->name('services.toggle');
        Route::delete('servicios/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });
});

require __DIR__.'/settings.php';
