<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\SalonController;
use Illuminate\Support\Facades\Route;

/*
| API v1, for the mobile app (registered under /api/v1). Token only: no
| cookies and no session. Each route has its own throttle counter (third
| parameter), like the web routes.
*/

Route::post('login', [AuthController::class, 'store'])
    ->middleware('throttle:api-login')
    ->name('api.v1.login');

// Open to anyone, like the public booking wizard. A token is optional and
// only changes what its owner may book.
Route::get('salons/{salon:slug}', [SalonController::class, 'show'])
    ->middleware('throttle:120,1,api-salon')
    ->name('api.v1.salons.show');
Route::get('salons/{salon:slug}/availability', [SalonController::class, 'availability'])
    ->middleware('throttle:90,1,api-availability')
    ->name('api.v1.salons.availability');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'show'])->name('api.v1.me');
    Route::post('logout', [AuthController::class, 'destroy'])->name('api.v1.logout');
});

// Without the current terms accepted: 403 with code `terms_not_accepted`.
Route::middleware(['auth:sanctum', 'terms'])->group(function () {
    Route::middleware(['verified', 'throttle:120,1,api-bookings'])->group(function () {
        Route::get('bookings', [BookingController::class, 'index'])->name('api.v1.bookings.index');
        Route::get('bookings/{code}', [BookingController::class, 'show'])->name('api.v1.bookings.show');
        Route::get('bookings/{code}/slots', [BookingController::class, 'slots'])->name('api.v1.bookings.slots');
    });

    Route::middleware('verified')->group(function () {
        Route::post('salons/{salon:slug}/bookings', [BookingController::class, 'store'])
            ->middleware('throttle:10,1,api-booking-store')
            ->name('api.v1.bookings.store');
        Route::post('bookings/{code}/cancel', [BookingController::class, 'cancel'])
            ->middleware('throttle:20,1,api-booking-cancel')
            ->name('api.v1.bookings.cancel');
        Route::post('bookings/{code}/move', [BookingController::class, 'move'])
            ->middleware('throttle:10,1,api-booking-move')
            ->name('api.v1.bookings.move');
    });
});
