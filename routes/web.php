<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Client\BookingController as ClientBookingController;
use App\Http\Controllers\CurrentSalonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Owner\CashController;
use App\Http\Controllers\Owner\ClientController;
use App\Http\Controllers\Owner\CommissionController;
use App\Http\Controllers\Owner\ExpenseCategoryController;
use App\Http\Controllers\Owner\ExpenseController;
use App\Http\Controllers\Owner\HolidayController;
use App\Http\Controllers\Owner\InvitationController;
use App\Http\Controllers\Owner\MemberController;
use App\Http\Controllers\Owner\SalonBrandingController;
use App\Http\Controllers\Owner\SalonController;
use App\Http\Controllers\Owner\SalonPhotoController;
use App\Http\Controllers\Owner\SalonSettingsController;
use App\Http\Controllers\Owner\ServiceCatalogController;
use App\Http\Controllers\Owner\ServiceController;
use App\Http\Controllers\Owner\StaffController;
use App\Http\Controllers\Owner\TeamController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\SalonPageController;
use App\Http\Controllers\Public\SamplePhotoController;
use App\Http\Controllers\Staff\AgendaBookingController;
use App\Http\Controllers\Staff\AgendaController;
use App\Http\Controllers\Staff\BookingMoveController;
use App\Http\Controllers\Staff\BookingStatusController;
use App\Http\Controllers\Staff\ScheduleController;
use App\Http\Controllers\Staff\StaffPhotoController;
use App\Http\Controllers\Staff\StaffServiceController;
use App\Http\Controllers\Staff\TimeOffController;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentSalon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::inertia('/', 'welcome')->name('home');

// Bundled sample photos for previews and the landing page. Without the
// session: a response cached as public must never carry a session cookie.
Route::get('img/muestra/{file}', [SamplePhotoController::class, 'show'])
    ->where('file', '[a-z0-9.-]+')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        HandleAppearance::class,
        ResolveCurrentSalon::class,
        HandleInertiaRequests::class,
    ])
    ->middleware('throttle:300,1,samples')
    ->name('samples.show');

// Public salon profile, shared by the salon on WhatsApp/Instagram and as a QR.
// Generous limit: link-preview bots (WhatsApp, Facebook) also fetch it.
Route::get('s/{salon:slug}', [SalonPageController::class, 'show'])
    ->middleware('throttle:120,1,salon-page')
    ->name('salons.show');

// Online booking: anyone can browse services and free times; confirming
// needs a verified account.
Route::get('s/{salon:slug}/reservar', [BookingController::class, 'create'])
    ->middleware('throttle:120,1,booking-page')
    ->name('salons.booking.create');
Route::get('s/{salon:slug}/disponibilidad', [BookingController::class, 'availability'])
    ->middleware(['throttle:90,1,booking-availability', 'lookup'])
    ->name('salons.booking.availability');

// A third argument gives each limit its own counter: unnamed throttles of
// the same user or IP would otherwise add up across routes.
Route::middleware(['guest', 'throttle:10,1,google-login'])->group(function () {
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

    Route::get('s/{salon:slug}/reservar/continuar', [BookingController::class, 'resume'])->name('salons.booking.resume');
    Route::post('s/{salon:slug}/reservas', [BookingController::class, 'store'])
        ->middleware('throttle:10,1,booking-store')
        ->name('salons.booking.store');

    Route::get('mis-reservas', [ClientBookingController::class, 'index'])->name('bookings.index');
    Route::get('mis-reservas/{code}', [ClientBookingController::class, 'show'])->name('bookings.show');
    Route::post('mis-reservas/{code}/cancelar', [ClientBookingController::class, 'cancel'])
        ->middleware('throttle:20,1,booking-cancel')
        ->name('bookings.cancel');
    Route::get('mis-reservas/{code}/horarios', [ClientBookingController::class, 'slots'])
        ->middleware(['throttle:90,1,booking-move-slots', 'lookup'])
        ->name('bookings.slots');
    Route::post('mis-reservas/{code}/mover', [ClientBookingController::class, 'move'])
        ->middleware('throttle:10,1,booking-move')
        ->name('bookings.move');
    Route::post('mis-reservas/{code}/foto', [ClientBookingController::class, 'storePhoto'])
        ->middleware('throttle:10,1,booking-photo')
        ->name('bookings.photo.store');
    Route::delete('mis-reservas/{code}/foto', [ClientBookingController::class, 'destroyPhoto'])->name('bookings.photo.destroy');

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
        Route::put('mi-salon/reservas', [SalonSettingsController::class, 'updateBooking'])->name('salon.booking.update');
        Route::put('mi-salon/ubicacion', [SalonSettingsController::class, 'updateLocation'])->name('salon.location.update');
        Route::put('mi-salon/color', [SalonBrandingController::class, 'updateColor'])->name('salon.color.update');
        Route::put('mi-salon/diseno', [SalonBrandingController::class, 'updateTheme'])->name('salon.theme.update');

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('mi-salon/logo', [SalonBrandingController::class, 'storeLogo'])->name('salon.logo.store');
            Route::post('mi-salon/portada', [SalonBrandingController::class, 'storeCover'])->name('salon.cover.store');
            Route::post('mi-salon/fotos', [SalonPhotoController::class, 'store'])->name('salon.photos.store');
        });
        Route::delete('mi-salon/logo', [SalonBrandingController::class, 'destroyLogo'])->name('salon.logo.destroy');
        Route::delete('mi-salon/portada', [SalonBrandingController::class, 'destroyCover'])->name('salon.cover.destroy');
        Route::put('mi-salon/fotos/orden', [SalonPhotoController::class, 'reorder'])->name('salon.photos.reorder');
        Route::delete('mi-salon/fotos/{photo}', [SalonPhotoController::class, 'destroy'])->name('salon.photos.destroy');

        Route::get('caja', [CashController::class, 'index'])->name('cash.index');
        Route::post('caja/abrir', [CashController::class, 'open'])->name('cash.open');
        Route::post('caja/{session}/cerrar', [CashController::class, 'close'])->name('cash.close');

        Route::get('comisiones', [CommissionController::class, 'index'])->name('commissions.index');
        Route::post('comisiones/{staff}/liquidar', [CommissionController::class, 'settle'])->name('commissions.settle');
        Route::put('comisiones/{staff}/reglas', [CommissionController::class, 'updateRules'])->name('commissions.rules.update');

        Route::get('gastos', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('gastos', [ExpenseController::class, 'store'])->middleware('throttle:60,1,expenses')->name('expenses.store');
        // POST: browsers cannot send a file in a PUT form.
        Route::post('gastos/{expense}', [ExpenseController::class, 'update'])->middleware('throttle:60,1,expenses')->name('expenses.update');
        Route::delete('gastos/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('gastos-categorias', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
        Route::put('gastos-categorias/{category}', [ExpenseCategoryController::class, 'update'])->name('expenses.categories.update');
        Route::delete('gastos-categorias/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');

        Route::get('servicios', [ServiceController::class, 'index'])->name('services.index');
        Route::post('servicios', [ServiceController::class, 'store'])->name('services.store');
        Route::post('servicios/catalogo', [ServiceCatalogController::class, 'store'])->name('services.catalog.store');
        Route::put('servicios/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::patch('servicios/{service}/estado', [ServiceController::class, 'toggle'])->name('services.toggle');
        Route::delete('servicios/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        // Professionals: the owner manages the list; each one edits their own hours.
        Route::get('profesionales', [StaffController::class, 'index'])->name('staff.index');
        Route::post('profesionales', [StaffController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('staff.store');
        Route::put('profesionales/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('profesionales/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
        Route::post('profesionales/{staff}/foto', [StaffPhotoController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('staff.photo.store');
        Route::delete('profesionales/{staff}/foto', [StaffPhotoController::class, 'destroy'])->name('staff.photo.destroy');

        Route::get('mi-horario', [ScheduleController::class, 'mine'])->name('staff.schedule.mine');
        Route::get('profesionales/{staff}/horario', [ScheduleController::class, 'show'])->name('staff.schedule.show');
        Route::put('profesionales/{staff}/horario', [ScheduleController::class, 'update'])->name('staff.schedule.update');
        Route::post('profesionales/{staff}/ausencias', [TimeOffController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('staff.timeoff.store');
        Route::delete('profesionales/{staff}/ausencias/{timeOff}', [TimeOffController::class, 'destroy'])->name('staff.timeoff.destroy');
        Route::put('profesionales/{staff}/servicios', [StaffServiceController::class, 'update'])->name('staff.services.update');

        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        // The team books for a client, and moves bookings.
        Route::get('agenda/nueva', [AgendaBookingController::class, 'create'])->name('agenda.bookings.create');
        Route::get('agenda/disponibilidad', [AgendaBookingController::class, 'availability'])
            ->middleware(['throttle:120,1,agenda-availability', 'lookup'])
            ->name('agenda.bookings.availability');
        Route::get('agenda/clientes', [AgendaBookingController::class, 'clients'])
            ->middleware(['throttle:60,1,agenda-clients', 'lookup'])
            ->name('agenda.bookings.clients');
        Route::post('agenda', [AgendaBookingController::class, 'store'])
            ->middleware('throttle:60,1,agenda-store')
            ->name('agenda.bookings.store');
        Route::get('agenda/{booking}/horarios', [BookingMoveController::class, 'slots'])
            ->middleware(['throttle:120,1,agenda-move-slots', 'lookup'])
            ->name('agenda.bookings.slots');
        Route::post('agenda/{booking}/mover', [BookingMoveController::class, 'update'])
            ->middleware('throttle:60,1,agenda-move')
            ->name('agenda.bookings.move');
        Route::post('agenda/{booking}/confirmar', [BookingStatusController::class, 'confirm'])->name('agenda.bookings.confirm');
        Route::post('agenda/{booking}/iniciar', [BookingStatusController::class, 'start'])->name('agenda.bookings.start');
        Route::post('agenda/{booking}/completar', [BookingStatusController::class, 'complete'])->name('agenda.bookings.complete');
        Route::post('agenda/{booking}/no-vino', [BookingStatusController::class, 'noShow'])->name('agenda.bookings.no-show');
        Route::post('agenda/{booking}/cancelar', [BookingStatusController::class, 'cancel'])->name('agenda.bookings.cancel');
        Route::post('agenda/{booking}/devolver-sena', [BookingStatusController::class, 'refundDeposit'])->name('agenda.bookings.refund-deposit');

        Route::get('clientes', [ClientController::class, 'index'])->name('clients.index');
        Route::patch('clientes/{client}/vip', [ClientController::class, 'updateVip'])->name('clients.vip.update');

        Route::post('feriados', [HolidayController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('holidays.store');
        Route::delete('feriados/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    });
});

require __DIR__.'/settings.php';
