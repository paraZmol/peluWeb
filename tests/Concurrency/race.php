<?php

/*
| Worker for BookingRaceBreakerTest: one real PHP process that performs one
| operation against the agenda at an agreed instant. Prints one line:
| OK[:<id>], TAKEN (a business rule said no) or ERROR:<class> <message>.
|
|   php race.php <go-at-unix-float> <base64 json: {"op": "...", ...}>
|
| Operations: book (CreateBooking directly, or inside an outer transaction
| with "wrap"), http (the real POST route
| through the HTTP kernel), cancel, confirm, noshow, expire, schedule,
| timeoff, remove, pause, sync.
*/

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Staff\AddTimeOff;
use App\Actions\Staff\RemoveStaff;
use App\Actions\Staff\ReplaceWeeklySchedule;
use App\Actions\Staff\SaveStaff;
use App\Actions\Staff\SyncStaffServices;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\ScheduleAccess;
use App\Enums\TimeOffReason;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use App\Models\User;
use App\Support\CurrentSalon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $goAt, $payload] = $argv;

/** @var array<string, mixed> $job */
$job = json_decode((string) base64_decode($payload, true), true, 16, JSON_THROW_ON_ERROR);

$staffOf = fn (int $id): Staff => Staff::withoutGlobalScope('salon')->findOrFail($id);
$bookingOf = fn (int $id): Booking => Booking::withoutGlobalScope('salon')->findOrFail($id);

try {
    $run = match ($job['op']) {
        'book' => (function () use ($job, $staffOf): Closure {
            $data = new NewBooking(
                salon: Salon::findOrFail($job['salon']),
                client: SalonClient::withoutGlobalScope('salon')->findOrFail($job['client']),
                selections: [['service_id' => $job['service'], 'variant_id' => null]],
                staff: ($job['staff'] ?? 0) === 0 ? null : $staffOf($job['staff']),
                startsAt: CarbonImmutable::createFromTimestampUTC($job['start']),
                source: BookingSource::from($job['source'] ?? 'client'),
            );

            // "wrap": what the controller does — an outer transaction that
            // has already read something before the booking starts.
            return ($job['wrap'] ?? false)
                ? fn (): string => 'OK:'.DB::transaction(function () use ($data): int {
                    SalonClient::withoutGlobalScope('salon')->find($data->client->id);

                    return app(CreateBooking::class)->handle($data)->id;
                })
                : fn (): string => 'OK:'.app(CreateBooking::class)->handle($data)->id;
        })(),

        // The request a browser sends, through routing, middleware and the controller.
        'http' => (function () use ($job, $app): Closure {
            $salon = Salon::findOrFail($job['salon']);
            $user = User::findOrFail($job['user']);
            $request = Request::create("/s/{$salon->slug}/reservas", 'POST', [
                'services' => [['id' => $job['service']]],
                'staff' => ($job['staff'] ?? 0) === 0 ? null : $job['staff'],
                'start' => $job['start'],
            ]);

            return function () use ($app, $request, $user): string {
                Auth::guard('web')->setUser($user);
                $response = $app->make(HttpKernel::class)->handle($request);
                $location = (string) $response->headers->get('Location');

                return match (true) {
                    $response->getStatusCode() >= 500 => 'ERROR:HTTP '.$response->getStatusCode().' '.substr(($response->exception ?? null)?->getMessage() ?? '', 0, 300),
                    $response->isRedirect() && str_contains($location, '/mis-reservas/') => 'OK:'.basename($location),
                    $response->isRedirect() => 'TAKEN',
                    default => 'ERROR:HTTP '.$response->getStatusCode(),
                };
            };
        })(),

        'cancel' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->cancel($booking, null, (bool) ($job['byClient'] ?? false))->id;
        })(),

        'confirm' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->confirm($booking, null)->id;
        })(),

        'noshow' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->markNoShow($booking, null)->id;
        })(),

        'expire' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => app(ChangeBookingStatus::class)->expire($booking) === null ? 'TAKEN' : 'OK';
        })(),

        'schedule' => (function () use ($job, $staffOf): Closure {
            $staff = $staffOf($job['staff']);
            app(CurrentSalon::class)->set(Salon::findOrFail($staff->salon_id));
            $ranges = array_map(fn (array $range): array => [...$range, 'access' => ScheduleAccess::from($range['access'])], $job['ranges']);

            return function () use ($staff, $ranges): string {
                app(ReplaceWeeklySchedule::class)->handle($staff, $ranges);

                return 'OK';
            };
        })(),

        'timeoff' => (function () use ($job, $staffOf): Closure {
            $staff = $staffOf($job['staff']);
            app(CurrentSalon::class)->set(Salon::findOrFail($staff->salon_id));

            return fn (): string => 'OK:'.app(AddTimeOff::class)->handle(
                $staff,
                CarbonImmutable::createFromTimestampUTC($job['from']),
                CarbonImmutable::createFromTimestampUTC($job['to']),
                TimeOffReason::Vacation,
            )->id;
        })(),

        'remove' => (function () use ($job, $staffOf): Closure {
            $staff = $staffOf($job['staff']);
            app(CurrentSalon::class)->set(Salon::findOrFail($staff->salon_id));

            return function () use ($staff): string {
                app(RemoveStaff::class)->handle($staff);

                return 'OK';
            };
        })(),

        // What the owner's "pause" switch does.
        'pause' => (function () use ($job, $staffOf): Closure {
            $staff = $staffOf($job['staff']);
            $salon = Salon::findOrFail($staff->salon_id);
            app(CurrentSalon::class)->set($salon);

            return function () use ($salon, $staff): string {
                app(SaveStaff::class)->handle($salon, $staff, ['name' => $staff->name, 'is_active' => false], $staff->user_id);

                return 'OK';
            };
        })(),

        'sync' => (function () use ($job, $staffOf): Closure {
            $staff = $staffOf($job['staff']);
            app(CurrentSalon::class)->set(Salon::findOrFail($staff->salon_id));

            return function () use ($staff, $job): string {
                app(SyncStaffServices::class)->handle($staff, $job['entries'], true);

                return 'OK';
            };
        })(),
    };

    // Everyone fires at the same instant, with the app already booted.
    $wait = (float) $goAt - microtime(true);

    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }

    echo $run();
} catch (BusinessRuleException) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR:'.$e::class.' '.substr($e->getMessage(), 0, 300);
}
