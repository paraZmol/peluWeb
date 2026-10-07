<?php

/*
| Worker for BookingMoveRaceTest: one real PHP process that performs one
| operation against the agenda at an agreed instant. Prints one line:
| OK[:<id>], TAKEN (a business rule or a validation said no) or
| ERROR:<class> <message>, after a first line READY when it is about to act.
|
|   php move.php <go-at-unix-float> <base64 json: {"op": "...", ...}>
|
| Operations through the actions: move, book, cancel, confirm, start,
| schedule, timeoff, holiday, remove, pause.
| Operations through the HTTP kernel (routing, middleware, controller):
| httpMove (team), httpClientMove, httpBook (client), httpAgenda (team
| books for a client), httpStatus (team: cancelar, iniciar, confirmar).
*/

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Salon\AddHoliday;
use App\Actions\Staff\AddTimeOff;
use App\Actions\Staff\RemoveStaff;
use App\Actions\Staff\ReplaceWeeklySchedule;
use App\Actions\Staff\SaveStaff;
use App\Data\Booking\BookingMove;
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

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $goAt, $payload] = $argv;

/** @var array<string, mixed> $job */
$job = json_decode((string) base64_decode($payload, true), true, 16, JSON_THROW_ON_ERROR);

const BACK_MARKER = '/came-back-with-an-error';
const READY_MARKER = 'READY'.PHP_EOL;

$staffOf = fn (int $id): Staff => Staff::withoutGlobalScope('salon')->findOrFail($id);
$bookingOf = fn (int $id): Booking => Booking::withoutGlobalScope('salon')->findOrFail($id);

/**
 * Sends one request as the given user. A redirect "back" (business rule or
 * validation error) lands on BACK_MARKER and reads as TAKEN.
 *
 * @param  array<string, mixed>  $data
 */
$http = function (int $userId, string $uri, array $data, bool $backIsDone = false) use ($app): Closure {
    $user = User::findOrFail($userId);
    $request = Request::create($uri, 'POST', $data);
    $request->headers->set('referer', 'http://localhost'.BACK_MARKER);

    return function () use ($app, $request, $user, $backIsDone): string {
        Auth::guard('web')->setUser($user);
        $response = $app->make(HttpKernel::class)->handle($request);
        $location = (string) $response->headers->get('Location');
        $status = $response->getStatusCode();

        return match (true) {
            $status >= 500 => 'ERROR:HTTP '.$status.' '.substr(($response->exception ?? null)?->getMessage() ?? '', 0, 300),
            // Status buttons answer "back" either way: the test reads the outcome from the database.
            $response->isRedirect() && $backIsDone => 'DONE',
            $response->isRedirect() && str_contains($location, BACK_MARKER) => 'TAKEN',
            $response->isRedirect() => 'OK:'.basename($location),
            default => 'ERROR:HTTP '.$status,
        };
    };
};

try {
    $run = match ($job['op']) {
        'move' => (function () use ($job, $staffOf, $bookingOf): Closure {
            $move = new BookingMove(
                booking: $bookingOf($job['booking']),
                startsAt: CarbonImmutable::createFromTimestampUTC($job['start']),
                byClient: (bool) ($job['byClient'] ?? false),
                actor: isset($job['actor']) ? User::findOrFail($job['actor']) : null,
                staff: ($job['staff'] ?? 0) === 0 ? null : $staffOf($job['staff']),
            );

            return fn (): string => 'OK:'.app(RescheduleBooking::class)->handle($move)->id;
        })(),

        'book' => (function () use ($job, $staffOf): Closure {
            $data = new NewBooking(
                salon: Salon::findOrFail($job['salon']),
                client: SalonClient::withoutGlobalScope('salon')->findOrFail($job['client']),
                selections: [['service_id' => $job['service'], 'variant_id' => null]],
                staff: ($job['staff'] ?? 0) === 0 ? null : $staffOf($job['staff']),
                startsAt: CarbonImmutable::createFromTimestampUTC($job['start']),
                source: BookingSource::from($job['source'] ?? 'client'),
            );

            return fn (): string => 'OK:'.app(CreateBooking::class)->handle($data)->id;
        })(),

        'cancel' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->cancel($booking, null, (bool) ($job['byClient'] ?? false))->id;
        })(),

        'confirm' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->confirm($booking, null)->id;
        })(),

        'start' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->start($booking, null)->id;
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

        'holiday' => (function () use ($job): Closure {
            $salon = Salon::findOrFail($job['salon']);
            app(CurrentSalon::class)->set($salon);

            return fn (): string => 'OK:'.app(AddHoliday::class)->handle($salon, $job['date'], 'Cierre')->id;
        })(),

        'httpMove' => $http($job['user'], "/agenda/{$job['booking']}/mover", ['start' => $job['start'], 'staff' => $job['staff'] ?? null]),

        'httpClientMove' => $http($job['user'], "/mis-reservas/{$job['code']}/mover", ['start' => $job['start']]),

        'httpBook' => $http($job['user'], '/s/'.Salon::findOrFail($job['salon'])->slug.'/reservas', [
            'services' => [['id' => $job['service']]],
            'staff' => ($job['staff'] ?? 0) === 0 ? null : $job['staff'],
            'start' => $job['start'],
        ]),

        'httpAgenda' => $http($job['user'], '/agenda', array_filter([
            'services' => [['id' => $job['service']]],
            'staff' => ($job['staff'] ?? 0) === 0 ? null : $job['staff'],
            'start' => $job['start'],
            'client_id' => $job['client_id'] ?? null,
            'client_name' => $job['client_name'] ?? null,
            'client_phone' => $job['client_phone'] ?? null,
        ], fn (mixed $value): bool => $value !== null)),

        'httpStatus' => $http($job['user'], "/agenda/{$job['booking']}/{$job['action']}", [], backIsDone: true),
    };

    // Everyone fires at the same instant, with the app already booted.
    $wait = (float) $goAt - microtime(true);

    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }

    // Tells the test this worker is booted and about to hit the database.
    echo READY_MARKER;
    flush();

    echo $run();
} catch (BusinessRuleException) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR:'.$e::class.' '.substr($e->getMessage(), 0, 300);
}
