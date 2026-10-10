<?php

/*
| Worker for MoneyRaceTest: one real PHP process that performs one
| operation on the salon's money at an agreed instant. Prints READY when it
| is booted and about to act, and then one line:
|
|   OK[:<id>]   the action went through
|   TAKEN       a business rule said no
|   DONE        an HTTP request was answered with a redirect (the buttons
|               answer "back" either way: the test reads the database)
|   ERROR:…     an exception or a 4xx/5xx: a failure on its own
|
|   php money.php <go-at-unix-float> <base64 json: {"op": "...", ...}>
|
| Through the HTTP kernel (routing, middleware, policies, controller):
| http. Through the actions: complete, refund, cancel, noshow, settle,
| cashOpen, cashClose, book, removeMember.
*/

use App\Actions\Accounting\CloseCashSession;
use App\Actions\Accounting\OpenCashSession;
use App\Actions\Accounting\RefundDeposit;
use App\Actions\Accounting\SettleCommissions;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Team\RemoveMember;
use App\Data\Accounting\Period;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\CashSession;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Models\User;
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

const READY_MARKER = 'READY'.PHP_EOL;

$staffOf = fn (int $id): Staff => Staff::withoutGlobalScope('salon')->withTrashed()->findOrFail($id);
$bookingOf = fn (int $id): Booking => Booking::withoutGlobalScope('salon')->findOrFail($id);
$userOf = fn (?int $id): ?User => $id === null ? null : User::findOrFail($id);

try {
    $run = match ($job['op']) {
        'http' => (function () use ($job, $app): Closure {
            $user = User::findOrFail($job['user']);
            $request = Request::create($job['uri'], 'POST', $job['data'] ?? []);
            $request->headers->set('referer', 'http://localhost/came-back');

            return function () use ($app, $request, $user): string {
                Auth::guard('web')->setUser($user);
                $response = $app->make(HttpKernel::class)->handle($request);
                $status = $response->getStatusCode();

                return match (true) {
                    $status >= 500 => 'ERROR:HTTP '.$status.' '.substr(($response->exception ?? null)?->getMessage() ?? '', 0, 300),
                    $response->isRedirect() => 'DONE',
                    default => 'ERROR:HTTP '.$status,
                };
            };
        })(),

        'complete' => (function () use ($job, $bookingOf, $userOf): Closure {
            $booking = $bookingOf($job['booking']);
            $by = $userOf($job['by'] ?? null);
            $checkout = isset($job['parts']) ? new Checkout($job['discount'] ?? 0, $job['parts']) : null;

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->complete($booking, $by, $checkout)->id;
        })(),

        'refund' => (function () use ($job, $bookingOf, $userOf): Closure {
            $booking = $bookingOf($job['booking']);
            $by = $userOf($job['by'] ?? null);

            return fn (): string => 'OK:'.app(RefundDeposit::class)->handle($booking, PaymentMethod::from($job['method'] ?? 'cash'), $by)->id;
        })(),

        'cancel' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->cancel($booking, null, (bool) ($job['byClient'] ?? false))->id;
        })(),

        'noshow' => (function () use ($job, $bookingOf): Closure {
            $booking = $bookingOf($job['booking']);

            return fn (): string => 'OK:'.app(ChangeBookingStatus::class)->markNoShow($booking, null)->id;
        })(),

        'settle' => (function () use ($job, $staffOf, $userOf): Closure {
            $staff = $staffOf($job['staff']);
            $period = Period::inSalon(Salon::findOrFail($staff->salon_id), $job['from'], $job['to']);
            $by = $userOf($job['by'] ?? null);

            return fn (): string => 'OK:'.app(SettleCommissions::class)->handle($staff, $period, PaymentMethod::from($job['method'] ?? 'cash'), $by)->total_cents;
        })(),

        'cashOpen' => (function () use ($job): Closure {
            $salon = Salon::findOrFail($job['salon']);

            return fn (): string => 'OK:'.app(OpenCashSession::class)->handle($salon, $job['cents'] ?? 0, null)->id;
        })(),

        'cashClose' => (function () use ($job): Closure {
            $session = CashSession::withoutGlobalScope('salon')->findOrFail($job['session']);

            return fn (): string => 'OK:'.app(CloseCashSession::class)->handle($session, $job['cents'] ?? 0, null, null)->expected_cash_cents;
        })(),

        'book' => (function () use ($job, $staffOf): Closure {
            $data = new NewBooking(
                salon: Salon::findOrFail($job['salon']),
                client: SalonClient::withoutGlobalScope('salon')->findOrFail($job['client']),
                selections: [['service_id' => $job['service'], 'variant_id' => null]],
                staff: $staffOf($job['staff']),
                startsAt: CarbonImmutable::createFromTimestampUTC($job['start']),
                source: BookingSource::Staff,
            );

            return fn (): string => 'OK:'.app(CreateBooking::class)->handle($data)->id;
        })(),

        'removeMember' => (function () use ($job): Closure {
            $member = SalonMember::query()->findOrFail($job['member']);

            return function () use ($member): string {
                app(RemoveMember::class)->handle($member);

                return 'OK';
            };
        })(),
    };

    // Everyone fires at the same instant, with the app already booted.
    $wait = (float) $goAt - microtime(true);

    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }

    echo READY_MARKER;
    flush();

    echo $run();
} catch (BusinessRuleException) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR:'.$e::class.' '.substr($e->getMessage(), 0, 300);
}
