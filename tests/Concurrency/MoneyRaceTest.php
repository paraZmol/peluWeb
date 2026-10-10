<?php

use App\Actions\Accounting\CountCashSession;
use App\Actions\Accounting\OpenCashSession;
use App\Actions\Accounting\SettleCommissions;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Data\Accounting\Period;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\CashSession;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
| Phase 5 under real concurrency: charging a visit, deposits given back,
| commission settlements and the till, each against itself and against the
| others. Real PHP processes (see money.php), through the HTTP kernel and
| through the actions. No transaction wraps these tests, so each cleans up
| after itself.
|
| The visit costs S/ 35.00 with a 30 % deposit (S/ 10.50), so S/ 24.50 are
| left to collect; Ana earns 40 % (S/ 14.00 for a visit without discount).
*/

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    $this->users = collect();
    $this->owner = mnUser();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['min_lead_minutes' => 0, 'timezone' => 'America/Lima', 'deposit_percent' => 30]);
    $this->service = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->day = CarbonImmutable::now('America/Lima')->addDays(3)->setTime(10, 0);
    $this->ana = mnStaff('Ana');
});

afterEach(function () {
    // A failed assertion must not leave a gate's lock behind.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    DB::table('commission_settlements')->where('salon_id', $this->salon->id)->delete();
    DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
    $this->salon->forceDelete();
    User::whereIn('id', $this->users->pluck('id'))->delete();
});

function mnUser(): User
{
    $user = User::factory()->create();
    test()->users->push($user);

    return $user;
}

function mnStaff(string $name, ?User $account = null): Staff
{
    $day = test()->day;
    $staff = Staff::factory()->for(test()->salon)->working([$day->dayOfWeekIso, $day->addDay()->dayOfWeekIso], '09:00', '18:00')->create(['name' => $name]);
    $staff->services()->attach(test()->service->id);
    $staff->forceFill(['commission_percent' => 40, 'user_id' => $account?->id])->save();

    return $staff;
}

/**
 * A booking waiting for its deposit, three days from now.
 */
function mnPending(string $time, ?Staff $staff = null): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: SalonClient::factory()->for(test()->salon)->create(),
        selections: [['service_id' => test()->service->id, 'variant_id' => null]],
        staff: $staff ?? test()->ana,
        startsAt: test()->day->setTimeFromTimeString($time),
        source: BookingSource::Client,
    ));
}

/**
 * Confirmed, with its S/ 10.50 deposit paid by Yape.
 */
function mnConfirmed(string $time, ?Staff $staff = null): Booking
{
    return app(ChangeBookingStatus::class)->confirm(mnPending($time, $staff), test()->owner, PaymentMethod::Yape);
}

/**
 * Brings a booking to this very moment, so it can be started, completed
 * and falls in today's period.
 */
function mnBringToNow(Booking $booking): void
{
    $times = ['starts_at' => now()->utc(), 'ends_at' => now()->utc()->addMinutes(30)];
    DB::table('bookings')->where('id', $booking->id)->update($times);
    DB::table('booking_segments')->where('booking_id', $booking->id)->update($times);
}

/**
 * With the client in the chair: deposit paid, S/ 24.50 left to collect.
 */
function mnInService(string $time, ?Staff $staff = null): Booking
{
    $booking = mnConfirmed($time, $staff);
    mnBringToNow($booking);

    return app(ChangeBookingStatus::class)->start($booking->refresh(), test()->owner);
}

/**
 * Completed and paid in cash: S/ 14.00 of commission pending.
 */
function mnCompleted(string $time, ?Staff $staff = null): Booking
{
    return app(ChangeBookingStatus::class)->complete(mnInService($time, $staff), test()->owner);
}

/**
 * Cancelled by the salon after its deposit was paid: S/ 10.50 to give back.
 */
function mnCancelledWithDeposit(string $time, ?Staff $staff = null): Booking
{
    return app(ChangeBookingStatus::class)->cancel(mnConfirmed($time, $staff), test()->owner, byClient: false);
}

/**
 * @param  array<string, mixed>  $job
 */
function mnProcess(array $job, float $goAt): Process
{
    $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    $process = new Process([PHP_BINARY, __DIR__.'/money.php', (string) $goAt, base64_encode(json_encode($job, JSON_THROW_ON_ERROR))], base_path(), $env);
    $process->setTimeout(90);
    $process->start();

    return $process;
}

/**
 * @param  list<Process>  $processes
 * @return list<string>
 */
function mnResults(array $processes): array
{
    return array_map(function (Process $process): string {
        $process->wait();

        return trim(str_replace('READY', '', $process->getOutput())).trim($process->getErrorOutput());
    }, $processes);
}

/**
 * One worker per job, all firing at the same instant.
 *
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function mnAll(array $jobs): array
{
    $goAt = microtime(true) + 4;

    return mnResults(array_map(fn (array $job): Process => mnProcess($job, $goAt), $jobs));
}

/**
 * Wait until each worker has booted and sent its request, and give it time
 * to reach the lock it queues behind.
 *
 * @param  list<Process>  $processes
 */
function mnUntilQueued(array $processes): void
{
    $deadline = microtime(true) + 30;

    foreach ($processes as $process) {
        while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
            usleep(100_000);
        }
    }

    usleep(1_500_000);
}

/**
 * The deterministic race: this process holds row locks until every worker
 * is queued behind them, then lets go. A job with "settle" queues before
 * the next one starts, so it is served first.
 *
 * @param  Model|list<Model>  $gate
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function mnBehindGate(Model|array $gate, array $jobs): array
{
    DB::beginTransaction();

    foreach (is_array($gate) ? $gate : [$gate] as $row) {
        $row->newQueryWithoutScopes()->lockForUpdate()->findOrFail($row->getKey());
    }

    $processes = [];

    foreach ($jobs as $job) {
        $processes[] = mnProcess($job, 0.0);

        if ($job['settle'] ?? false) {
            mnUntilQueued($processes);
        }
    }

    mnUntilQueued($processes);
    DB::commit();

    return mnResults($processes);
}

/**
 * @param  list<string>  $results
 * @return array{ok: int, taken: int, error: int, all: string}
 */
function mnTally(array $results): array
{
    return [
        'ok' => count(array_filter($results, fn (string $result) => str_starts_with($result, 'OK'))),
        'taken' => count(array_filter($results, fn (string $result) => $result === 'TAKEN')),
        'error' => count(array_filter($results, fn (string $result) => ! str_starts_with($result, 'OK') && ! in_array($result, ['TAKEN', 'DONE'], true))),
        'all' => implode(' | ', $results),
    ];
}

/**
 * A POST sent by the owner through the HTTP kernel.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function mnHttp(string $uri, array $data = []): array
{
    return ['op' => 'http', 'user' => test()->owner->id, 'uri' => $uri, 'data' => $data];
}

/**
 * @param  list<array{string, string}>  $payments  method and soles
 * @return array<string, mixed>
 */
function mnHttpComplete(Booking $booking, array $payments = [['cash', '24.50']], ?string $discount = null): array
{
    return mnHttp("/agenda/{$booking->id}/completar", array_filter([
        'discount' => $discount,
        'payments' => array_map(fn (array $payment): array => ['method' => $payment[0], 'amount' => $payment[1]], $payments),
    ], fn (mixed $value): bool => $value !== null));
}

/**
 * @return array<string, mixed>
 */
function mnHttpSettle(Staff $staff): array
{
    $today = CarbonImmutable::now('America/Lima')->toDateString();

    return mnHttp("/comisiones/{$staff->id}/liquidar", ['from' => $today, 'to' => $today, 'method' => 'cash']);
}

/**
 * @return array<string, mixed>
 */
function mnSettle(Staff $staff): array
{
    $today = CarbonImmutable::now('America/Lima')->toDateString();

    return ['op' => 'settle', 'staff' => $staff->id, 'from' => $today, 'to' => $today];
}

/**
 * @return list<array{string, string, int}> kind, method and céntimos of each movement of a booking
 */
function mnPayments(Booking $booking, ?string $kind = null): array
{
    return Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)
        ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
        ->orderBy('id')->get()
        ->map(fn (Payment $payment): array => [$payment->kind->value, $payment->method->value, $payment->amount_cents])->all();
}

/**
 * Everything that would make the books lie about a booking of the salon.
 * Must be empty whatever raced:
 *  - one deposit movement exactly when the deposit is marked as paid;
 *  - one refund exactly when it is marked as given back, only if cancelled;
 *  - charges only on completed visits, adding up to what was left to pay;
 *  - charged = price − discount and a frozen commission on completed
 *    visits, nothing of that on the others;
 *  - one "completed" event at most, and no movement without its booking.
 *
 * @return list<string>
 */
function mnLedgerProblems(): array
{
    $problems = [];
    $salonId = test()->salon->id;

    foreach (Booking::withoutGlobalScopes()->where('salon_id', $salonId)->get() as $booking) {
        $rows = collect(mnPayments($booking));
        $sum = fn (string $kind): int => (int) $rows->filter(fn (array $row) => $row[0] === $kind)->sum(fn (array $row) => $row[2]);
        $count = fn (string $kind): int => $rows->filter(fn (array $row) => $row[0] === $kind)->count();
        $say = function (string $what) use (&$problems, $booking, $rows): void {
            $problems[] = "booking {$booking->id} ({$booking->status->value}): {$what} — ".json_encode($rows->all());
        };

        if ($count('deposit') !== ($booking->deposit_paid_at === null ? 0 : 1) || $sum('deposit') !== ($booking->deposit_paid_at === null ? 0 : $booking->deposit_cents)) {
            $say('deposit movements do not match deposit_paid_at');
        }

        if ($count('refund') !== ($booking->deposit_refunded_at === null ? 0 : 1) || $sum('refund') !== ($booking->deposit_refunded_at === null ? 0 : $booking->deposit_cents)) {
            $say('refund movements do not match deposit_refunded_at');
        }

        if ($booking->deposit_refunded_at !== null && $booking->status !== BookingStatus::Cancelled) {
            $say('a deposit was given back on a visit that is not cancelled');
        }

        if ($booking->status === BookingStatus::Completed) {
            if ($booking->charged_cents !== $booking->price_cents - $booking->discount_cents) {
                $say("charged {$booking->charged_cents} is not price {$booking->price_cents} − discount {$booking->discount_cents}");
            }

            if ($sum('charge') !== $booking->charged_cents - $booking->depositPaidCents()) {
                $say("charges add up to {$sum('charge')}, not to charged {$booking->charged_cents} − deposit {$booking->depositPaidCents()}");
            }

            if ($booking->commission_cents === null) {
                $say('completed without a commission');
            }
        } elseif ($count('charge') > 0 || $booking->charged_cents !== null || $booking->commission_cents !== null || $booking->discount_cents !== 0) {
            $say('charged, discounted or commissioned without being completed');
        }

        if ($booking->events()->where('to_status', 'completed')->count() > 1) {
            $say('completed more than once');
        }
    }

    $orphans = Payment::withoutGlobalScope('salon')->where('salon_id', $salonId)->whereNull('booking_id')->count();

    if ($orphans > 0) {
        $problems[] = "{$orphans} movements without a booking";
    }

    return $problems;
}

/**
 * Everything that would make the commissions lie. Must be empty:
 *  - each settlement pays exactly the visits that point to it;
 *  - each settlement has its own expense, of the same amount;
 *  - no visit is counted in two settlements (a visit points to one).
 *
 * @return list<string>
 */
function mnSettlementProblems(): array
{
    $problems = [];
    $settlements = CommissionSettlement::withoutGlobalScopes()->where('salon_id', test()->salon->id)->get();

    foreach ($settlements as $settlement) {
        $visits = Booking::withoutGlobalScopes()->where('commission_settlement_id', $settlement->id)->get();
        $expense = Expense::withoutGlobalScopes()->find($settlement->expense_id);

        if ((int) $visits->sum('commission_cents') !== $settlement->total_cents || $visits->count() !== $settlement->bookings_count) {
            $problems[] = "settlement {$settlement->id} says {$settlement->total_cents} for {$settlement->bookings_count} visits, its visits add up to {$visits->sum('commission_cents')} in {$visits->count()}";
        }

        if ($expense === null || $expense->amount_cents !== $settlement->total_cents) {
            $problems[] = "settlement {$settlement->id} of {$settlement->total_cents} has an expense of ".($expense->amount_cents ?? 'nothing');
        }
    }

    $expenses = (int) Expense::withoutGlobalScopes()->where('salon_id', test()->salon->id)->sum('amount_cents');

    if ($expenses !== (int) $settlements->sum('total_cents')) {
        $problems[] = "expenses add up to {$expenses}, settlements to {$settlements->sum('total_cents')}";
    }

    return $problems;
}

/**
 * A clean slate between the rounds of one test: no visits and no money.
 */
function mnReset(): void
{
    foreach (['commission_settlements', 'expenses', 'cash_sessions', 'payments', 'bookings'] as $table) {
        DB::table($table)->where('salon_id', test()->salon->id)->delete();
    }
}

function mnOpenSessions(): int
{
    return CashSession::withoutGlobalScopes()->where('salon_id', test()->salon->id)->whereNull('closed_at')->count();
}

describe('the same visit completed twice at once', function () {
    it('charges once when two people press "Cobrar" while the visit was in service (HTTP)', function () {
        $booking = mnInService('10:00');

        $tally = mnTally(mnBehindGate($this->ana, [mnHttpComplete($booking), mnHttpComplete($booking, [['yape', '24.50']])]));
        $booking->refresh();

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->status)->toBe(BookingStatus::Completed)
            ->and(mnPayments($booking, 'charge'))->toHaveCount(1)
            ->and($booking->commission_cents)->toBe(1400)
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('charges once when six different checkouts of one visit arrive at the same instant', function () {
        $booking = mnInService('10:00');

        $tally = mnTally(mnAll([
            mnHttpComplete($booking),
            mnHttpComplete($booking, [['yape', '24.50']]),
            mnHttpComplete($booking, [['cash', '10.00'], ['plin', '14.50']]),
            mnHttpComplete($booking, [['cash', '19.50']], discount: '5.00'),
            mnHttp("/agenda/{$booking->id}/completar"),
            ['op' => 'complete', 'booking' => $booking->id, 'parts' => ['card' => 2450]],
        ]));
        $booking->refresh();

        // Whichever won, the books describe exactly that one checkout.
        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->status)->toBe(BookingStatus::Completed)
            ->and($booking->commission_cents)->toBe(intdiv($booking->charged_cents * 40 + 50, 100))
            ->and(mnLedgerProblems())->toBe([], $tally['all']);
    });

    it('records the deposit once when "Seña recibida" is pressed four times at once', function () {
        $booking = mnPending('10:00');

        $tally = mnTally(mnAll([
            mnHttp("/agenda/{$booking->id}/confirmar", ['method' => 'yape']),
            mnHttp("/agenda/{$booking->id}/confirmar", ['method' => 'cash']),
            mnHttp("/agenda/{$booking->id}/confirmar", ['method' => 'plin']),
            mnHttp("/agenda/{$booking->id}/confirmar"),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
            ->and(mnPayments($booking))->toHaveCount(1)
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('keeps the books whole when a visit is completed and cancelled at the same instant', function () {
        // One in service (cannot be cancelled any more) and one still confirmed (cannot be completed yet).
        $inService = mnInService('10:00');
        $confirmed = mnConfirmed('11:00');

        $tally = mnTally(mnAll([
            mnHttpComplete($inService),
            mnHttp("/agenda/{$inService->id}/cancelar"),
            mnHttp("/agenda/{$inService->id}/no-vino"),
            mnHttpComplete($confirmed),
            mnHttp("/agenda/{$confirmed->id}/cancelar"),
            mnHttpComplete($confirmed, [['yape', '24.50']]),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($inService->refresh()->status)->toBe(BookingStatus::Completed)
            ->and($confirmed->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and(mnPayments($confirmed, 'charge'))->toBe([])
            ->and(mnLedgerProblems())->toBe([]);
    });
});

describe('a deposit given back twice, or while something else happens', function () {
    it('gives it back once when the button is pressed twice while the booking was locked (HTTP)', function () {
        $booking = mnCancelledWithDeposit('10:00');
        $uri = "/agenda/{$booking->id}/devolver-sena";

        $tally = mnTally(mnBehindGate($booking, [mnHttp($uri, ['method' => 'yape']), mnHttp($uri, ['method' => 'cash'])]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(mnPayments($booking, 'refund'))->toHaveCount(1)
            ->and($booking->refresh()->deposit_refunded_at)->not->toBeNull()
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('gives it back once when five requests arrive at the same instant', function () {
        $booking = mnCancelledWithDeposit('10:00');
        $uri = "/agenda/{$booking->id}/devolver-sena";

        $tally = mnTally(mnAll([
            mnHttp($uri, ['method' => 'yape']),
            mnHttp($uri, ['method' => 'cash']),
            mnHttp($uri),
            ['op' => 'refund', 'booking' => $booking->id],
            ['op' => 'refund', 'booking' => $booking->id, 'method' => 'plin'],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(mnPayments($booking, 'refund'))->toHaveCount(1)
            ->and(Payment::withoutGlobalScopes()->where('booking_id', $booking->id)->get()->sum(fn (Payment $payment) => $payment->signedCents()))->toBe(0)
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('never gives back the deposit of a visit that ends up completed', function () {
        $booking = mnInService('10:00');
        $uri = "/agenda/{$booking->id}/devolver-sena";

        $tally = mnTally(mnAll([
            mnHttp($uri, ['method' => 'cash']),
            mnHttpComplete($booking),
            ['op' => 'refund', 'booking' => $booking->id],
            mnHttp($uri),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::Completed)
            ->and(mnPayments($booking, 'refund'))->toBe([])
            ->and(mnPayments($booking, 'charge'))->toBe([['charge', 'cash', 2450]])
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('gives it back at most once, and only if cancelled, when the refund races the cancellation', function () {
        $problems = [];

        foreach (range(1, 3) as $round) {
            $booking = mnConfirmed(['10:00', '11:00', '12:00'][$round - 1]);
            $uri = "/agenda/{$booking->id}/devolver-sena";

            $tally = mnTally(mnAll([
                mnHttp("/agenda/{$booking->id}/cancelar"),
                mnHttp($uri, ['method' => 'yape']),
                ['op' => 'refund', 'booking' => $booking->id],
                mnHttp($uri),
            ]));

            if ($tally['error'] > 0 || $booking->refresh()->status !== BookingStatus::Cancelled) {
                $problems[] = "round {$round}: {$tally['all']}";
            }
        }

        expect($problems)->toBe([])->and(mnLedgerProblems())->toBe([]);
    });

    it('never gives back a deposit the client lost, however the requests arrive', function () {
        // Cancelled by the client inside the 24 h window: the salon keeps it.
        $booking = mnConfirmed('10:00');
        DB::table('bookings')->where('id', $booking->id)->update(['starts_at' => now()->utc()->addHours(2), 'ends_at' => now()->utc()->addHours(3)]);
        $uri = "/agenda/{$booking->id}/devolver-sena";

        $tally = mnTally(mnAll([
            ['op' => 'cancel', 'booking' => $booking->id, 'byClient' => true],
            mnHttp($uri, ['method' => 'cash']),
            mnHttp($uri),
            ['op' => 'refund', 'booking' => $booking->id],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->deposit_refundable)->toBeFalse()
            ->and(mnPayments($booking, 'refund'))->toBe([])
            ->and(mnLedgerProblems())->toBe([]);
    });
});

describe('commissions settled twice, or while a visit ends', function () {
    it('pays once when "Liquidar" is pressed twice while the professional was locked (HTTP)', function () {
        $visits = [mnCompleted('10:00'), mnCompleted('11:00'), mnCompleted('12:00')];

        $tally = mnTally(mnBehindGate($this->ana, [mnHttpSettle($this->ana), mnHttpSettle($this->ana)]));
        $settlements = CommissionSettlement::withoutGlobalScopes()->where('staff_id', $this->ana->id)->get();

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($settlements)->toHaveCount(1)
            ->and($settlements[0]->total_cents)->toBe(4200)
            ->and($settlements[0]->bookings_count)->toBe(3)
            ->and(Expense::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1)
            ->and(Booking::withoutGlobalScopes()->whereKey(array_map(fn (Booking $booking) => $booking->id, $visits))->pluck('commission_settlement_id')->unique()->all())->toBe([$settlements[0]->id])
            ->and(mnSettlementProblems())->toBe([]);
    });

    it('pays once when six settlements of one professional arrive at the same instant', function () {
        mnCompleted('10:00');
        mnCompleted('11:00');

        $tally = mnTally(mnAll([
            mnHttpSettle($this->ana), mnHttpSettle($this->ana), mnHttpSettle($this->ana),
            mnSettle($this->ana), mnSettle($this->ana), mnSettle($this->ana),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(CommissionSettlement::withoutGlobalScopes()->where('staff_id', $this->ana->id)->count())->toBe(1)
            ->and((int) Expense::withoutGlobalScopes()->where('salon_id', $this->salon->id)->sum('amount_cents'))->toBe(2800)
            ->and(mnSettlementProblems())->toBe([]);
    });

    it('pays every commission exactly once when visits of the period end while it is being settled', function () {
        $problems = [];

        foreach (range(1, 3) as $round) {
            mnReset();
            mnCompleted('10:00');
            $ending = [mnInService('11:00'), mnInService('12:00')];

            $tally = mnTally(mnAll([
                mnHttpSettle($this->ana),
                mnHttpComplete($ending[0]),
                mnSettle($this->ana),
                mnHttpComplete($ending[1], [['cash', '19.50']], discount: '5.00'),
                mnHttpSettle($this->ana),
            ]));

            // Whatever was left out of the race's settlements is still pending: pay it now.
            try {
                app(SettleCommissions::class)->handle($this->ana, Period::inSalon($this->salon->refresh(), null, null), PaymentMethod::Cash, null);
            } catch (PaymentException) {
                // Nothing was left.
            }

            $earned = (int) Booking::withoutGlobalScopes()->where('salon_id', $this->salon->id)->sum('commission_cents');
            $paid = (int) CommissionSettlement::withoutGlobalScopes()->where('salon_id', $this->salon->id)->sum('total_cents');

            // 14.00 + 14.00 + 40 % of 30.00 = 40.00
            if ($tally['error'] > 0 || $earned !== 4000 || $paid !== $earned || mnSettlementProblems() !== [] || mnLedgerProblems() !== []) {
                $problems[] = "round {$round}: {$tally['all']} earned {$earned} paid {$paid} ".implode('; ', [...mnSettlementProblems(), ...mnLedgerProblems()]);
            }
        }

        expect($problems)->toBe([]);
    });

    it('settles the visit that was completed first, and leaves the one completed after for the next settlement', function () {
        mnCompleted('10:00');
        $ending = mnInService('11:00');

        // Served in this order: the settlement, then the completion.
        $tally = mnTally(mnBehindGate($this->ana, [[...mnHttpSettle($this->ana), 'settle' => true], mnHttpComplete($ending)]));
        $settlement = CommissionSettlement::withoutGlobalScopes()->where('staff_id', $this->ana->id)->sole();

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($settlement->total_cents)->toBe(1400)
            ->and($ending->refresh()->status)->toBe(BookingStatus::Completed)
            ->and($ending->commission_settlement_id)->toBeNull()
            ->and(mnSettlementProblems())->toBe([]);
    });

    it('settles two professionals at once without mixing their visits', function () {
        $beto = mnStaff('Beto');
        mnCompleted('10:00');
        mnCompleted('11:00');
        mnCompleted('10:00', $beto);

        $tally = mnTally(mnAll([mnHttpSettle($this->ana), mnHttpSettle($beto), mnSettle($this->ana), mnSettle($beto)]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(CommissionSettlement::withoutGlobalScopes()->where('staff_id', $this->ana->id)->sole()->total_cents)->toBe(2800)
            ->and(CommissionSettlement::withoutGlobalScopes()->where('staff_id', $beto->id)->sole()->total_cents)->toBe(1400)
            ->and(mnSettlementProblems())->toBe([]);
    });
});

describe('the till opened or closed twice at once', function () {
    it('opens one session when two people open it while the salon was locked (HTTP)', function () {
        $tally = mnTally(mnBehindGate($this->salon, [mnHttp('/caja/abrir', ['amount' => '50']), mnHttp('/caja/abrir', ['amount' => '80'])]));

        expect($tally['error'])->toBe(0, $tally['all'])->and(mnOpenSessions())->toBe(1);
    });

    it('opens one session when six requests arrive at the same instant', function () {
        $tally = mnTally(mnAll([
            ...array_map(fn (int $amount) => mnHttp('/caja/abrir', ['amount' => (string) $amount]), [10, 20, 30]),
            ...array_map(fn (int $cents) => ['op' => 'cashOpen', 'salon' => $this->salon->id, 'cents' => $cents], [4000, 5000, 6000]),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBeLessThanOrEqual(1, $tally['all'])
            ->and(mnOpenSessions())->toBe(1);
    });

    it('closes a session once, with one count, when it is closed four times at the same instant', function () {
        $session = app(OpenCashSession::class)->handle($this->salon, 5000, $this->owner);
        $uri = "/caja/{$session->id}/cerrar";

        $tally = mnTally(mnAll([
            mnHttp($uri, ['amount' => '50']),
            mnHttp($uri, ['amount' => '49', 'note' => 'Falta un sol']),
            ['op' => 'cashClose', 'session' => $session->id, 'cents' => 5100],
            ['op' => 'cashClose', 'session' => $session->id, 'cents' => 4800],
        ]));
        $session->refresh();

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBeLessThanOrEqual(1, $tally['all'])
            ->and($session->isOpen())->toBeFalse()
            ->and($session->expected_cash_cents)->toBe(5000)
            ->and($session->counted_cash_cents)->toBeIn([5000, 4900, 5100, 4800])
            // The note belongs to the count that won.
            ->and($session->note)->toBe($session->counted_cash_cents === 4900 ? 'Falta un sol' : null)
            ->and(mnOpenSessions())->toBe(0);
    });

    it('never ends with two open sessions when the till is closed and reopened by several people at once', function () {
        $session = app(OpenCashSession::class)->handle($this->salon, 5000, $this->owner);

        $tally = mnTally(mnAll([
            mnHttp("/caja/{$session->id}/cerrar", ['amount' => '50']),
            mnHttp('/caja/abrir', ['amount' => '50']),
            mnHttp('/caja/abrir', ['amount' => '60']),
            ['op' => 'cashOpen', 'salon' => $this->salon->id, 'cents' => 7000],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])->and(mnOpenSessions())->toBeLessThanOrEqual(1);
    });
});

describe('the till closed while a cash charge comes in', function () {
    /*
    | CloseCashSession freezes "what should be in the drawer" from the
    | movements committed before its `closed_at`. A charge fixes its
    | `paid_at` when it takes the booking's lock and commits a moment later.
    | If the close reads in between, the charge is not in the frozen figure
    | and yet it is dated inside the session.
    |
    | KNOWN FAILURE (MEDIO), see the report of the booking-breaker of
    | 2026-10-10. Where to fix: every movement of money (ChangeBookingStatus,
    | RefundDeposit, SettleCommissions, SaveExpense) should take a shared lock
    | on the salon's row before fixing its instant, and CloseCashSession an
    | exclusive one before fixing `closed_at` (the salon stays last in the
    | lock order); or movements should carry the id of the session they
    | entered instead of being matched by time.
    |
    | The gate below widens that moment: this process holds the salon's
    | row, which the charge's insert into `payments` needs (foreign key), so
    | the charge stops after dating itself. The close does not touch the
    | salon's row and goes through.
    */
    it('freezes an expected amount that includes every cash charge dated inside the session', function () {
        $booking = mnInService('10:00');
        sleep(1);
        $session = app(OpenCashSession::class)->handle($this->salon, 5000, $this->owner);

        DB::beginTransaction();
        Salon::query()->lockForUpdate()->findOrFail($this->salon->id);

        $charge = mnProcess(mnHttpComplete($booking), 0.0);
        mnUntilQueued([$charge]);
        // A later second than the charge's, so the dates leave no doubt.
        sleep(1);
        // The cashier counts the drawer with those S/ 24.50 already in it.
        $close = mnProcess(mnHttp("/caja/{$session->id}/cerrar", ['amount' => '74.50']), 0.0);
        $closed = mnResults([$close]);

        DB::commit();
        $tally = mnTally([...$closed, ...mnResults([$charge])]);
        $session->refresh();
        $payment = Payment::withoutGlobalScopes()->where('booking_id', $booking->id)->where('kind', 'charge')->sole();
        $recount = app(CountCashSession::class)->handle($session)->expectedCashCents();

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($session->isOpen())->toBeFalse()
            ->and($payment->paid_at->lessThan($session->closed_at))->toBeTrue('the charge is dated inside the session')
            // 50.00 + 24.50: what the movements of the session add up to.
            ->and($recount)->toBe(7450)
            ->and($session->expected_cash_cents)->toBe($recount, 'the frozen figure left the charge out')
            ->and($session->differenceCents())->toBe(0);
    })->skip('Known failure (MEDIO): CloseCashSession does not wait for a charge that is already dated but not yet committed.');

    /*
    | The same race left to chance: three cash charges and the close sent at
    | the same instant, several rounds. A measuring tool, not a guard: run it
    | with MONEY_RACE_ROUNDS=10 (0 of 10 rounds went stale on 2026-10-10).
    */
    it('measures how often an undisturbed race leaves the frozen figure out of step', function () {
        $rounds = (int) (getenv('MONEY_RACE_ROUNDS') ?: 4);
        $stale = [];

        foreach (range(1, $rounds) as $round) {
            mnReset();
            $bookings = [mnInService('10:00'), mnInService('11:00'), mnInService('12:00')];
            sleep(1);
            $session = app(OpenCashSession::class)->handle($this->salon, 5000, $this->owner);

            $tally = mnTally(mnAll([
                ...array_map(fn (Booking $booking) => mnHttpComplete($booking), $bookings),
                mnHttp("/caja/{$session->id}/cerrar", ['amount' => '50']),
            ]));
            $session->refresh();
            $recount = app(CountCashSession::class)->handle($session)->expectedCashCents();

            expect($tally['error'])->toBe(0, $tally['all']);

            if ($session->expected_cash_cents !== $recount) {
                $stale[] = "round {$round}: frozen {$session->expected_cash_cents}, movements say {$recount}";
            }
        }

        expect($stale)->toBe([], count($stale)." of {$rounds} rounds");
    })->skip(fn (): bool => getenv('MONEY_RACE_ROUNDS') === false, 'Set MONEY_RACE_ROUNDS to measure the charge/close race.');
});

describe('lock order: professional, client, booking, salon', function () {
    /*
    | RefundDeposit locks the booking and then writes a payment, whose
    | foreign key reads the professional's row. Whoever changes the same
    | booking's status holds the professional and waits for the booking.
    */
    it('answers both when a deposit is given back while the same booking gets another status change', function () {
        $booking = mnCancelledWithDeposit('10:00');

        // The status change queues for Ana first; the refund then takes the booking and queues for Ana too.
        $tally = mnTally(mnBehindGate($this->ana, [
            [...mnHttp("/agenda/{$booking->id}/cancelar"), 'settle' => true],
            mnHttp("/agenda/{$booking->id}/devolver-sena", ['method' => 'cash']),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(mnPayments($booking, 'refund'))->toHaveCount(1)
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('answers both when a deposit is given back while someone books that professional for the same day', function () {
        $booking = mnCancelledWithDeposit('10:00');
        $client = SalonClient::factory()->for($this->salon)->create();
        $book = ['op' => 'book', 'salon' => $this->salon->id, 'client' => $client->id, 'service' => $this->service->id, 'staff' => $this->ana->id, 'start' => $this->day->setTime(11, 0)->getTimestamp()];

        $tally = mnTally(mnBehindGate($this->ana, [
            [...$book, 'settle' => true],
            mnHttp("/agenda/{$booking->id}/devolver-sena", ['method' => 'cash']),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mnPayments($booking, 'refund'))->toHaveCount(1)
            ->and(mnLedgerProblems())->toBe([]);
    });

    it('answers both when a deposit is given back while that professional\'s commissions are settled', function () {
        mnCompleted('10:00');
        $booking = mnCancelledWithDeposit('11:00');
        // In today's period, where the settlement looks for visits.
        mnBringToNow($booking);

        $tally = mnTally(mnBehindGate($this->ana, [
            [...mnHttpSettle($this->ana), 'settle' => true],
            mnHttp("/agenda/{$booking->id}/devolver-sena", ['method' => 'cash']),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(mnPayments($booking, 'refund'))->toHaveCount(1)
            ->and(CommissionSettlement::withoutGlobalScopes()->where('staff_id', $this->ana->id)->count())->toBe(1)
            ->and(mnSettlementProblems())->toBe([]);
    });

    /*
    | RemoveMember locks the salon and then detaches the member's
    | professional profile (the professional's row). A charge holds the
    | professional and then writes a payment, whose foreign key reads the
    | salon's row. The gate stops the removal between its two steps.
    */
    it('answers both when a visit is charged while its professional\'s account is removed from the team', function () {
        $member = SalonMember::factory()->for($this->salon)->create();
        $this->users->push($member->user);
        $this->ana->forceFill(['user_id' => $member->user_id])->save();
        $booking = mnInService('10:00');

        $tally = mnTally(mnBehindGate($member, [
            ['op' => 'removeMember', 'member' => $member->id, 'settle' => true],
            mnHttpComplete($booking),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::Completed)
            ->and(SalonMember::query()->whereKey($member->id)->exists())->toBeFalse()
            ->and(mnLedgerProblems())->toBe([]);
    });

    /*
    | Older than phase 5, kept here as a guard: markNoShow takes the
    | client's row after the booking (to count the no-show), while a new
    | booking of that client holds the client's row and reads their
    | bookings. They can wait on each other; both retry.
    */
    it('answers both when a client is marked as a no-show while they book with someone else', function () {
        $beto = mnStaff('Beto');
        $missed = mnConfirmed('10:00');
        DB::table('bookings')->where('id', $missed->id)->update(['starts_at' => now()->utc()->subHour(), 'ends_at' => now()->utc()->subMinutes(30)]);
        $client = SalonClient::withoutGlobalScopes()->findOrFail($missed->salon_client_id);
        $book = ['op' => 'book', 'salon' => $this->salon->id, 'client' => $client->id, 'service' => $this->service->id, 'staff' => $beto->id, 'start' => $this->day->setTime(11, 0)->getTimestamp()];

        $tally = mnTally(mnBehindGate($client, [
            [...$book, 'settle' => true],
            mnHttp("/agenda/{$missed->id}/no-vino"),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($missed->refresh()->status)->toBe(BookingStatus::NoShow)
            ->and($client->refresh()->no_show_count)->toBe(1)
            ->and(mnPayments($missed))->toBe([['deposit', 'yape', 1050]]);
    });

    it('answers every request across rounds of charges, refunds, settlements, bookings and the till, all at once', function () {
        $problems = [];
        $beto = mnStaff('Beto');

        foreach (range(1, 3) as $round) {
            mnReset();

            mnCompleted('09:00');
            mnCompleted('09:00', $beto);
            $anas = mnInService('10:00');
            $betos = mnInService('10:00', $beto);
            $refundable = mnCancelledWithDeposit('11:00');
            mnBringToNow($refundable);
            $toCancel = mnConfirmed('12:00', $beto);
            $session = app(OpenCashSession::class)->handle($this->salon, 5000, $this->owner);
            $client = SalonClient::factory()->for($this->salon)->create();

            $tally = mnTally(mnAll([
                mnHttpComplete($anas),
                mnHttpComplete($betos, [['cash', '10.00'], ['yape', '14.50']]),
                mnHttpSettle($this->ana),
                mnSettle($beto),
                mnHttp("/agenda/{$refundable->id}/devolver-sena", ['method' => 'cash']),
                ['op' => 'refund', 'booking' => $refundable->id],
                mnHttp("/agenda/{$toCancel->id}/cancelar"),
                mnHttp("/agenda/{$toCancel->id}/devolver-sena"),
                ['op' => 'book', 'salon' => $this->salon->id, 'client' => $client->id, 'service' => $this->service->id, 'staff' => $this->ana->id, 'start' => $this->day->setTime(14, 0)->getTimestamp()],
                mnHttp("/caja/{$session->id}/cerrar", ['amount' => '50']),
                mnHttp('/caja/abrir', ['amount' => '50']),
            ]));

            $found = [...mnLedgerProblems(), ...mnSettlementProblems()];

            if ($tally['error'] > 0 || $found !== [] || mnOpenSessions() > 1) {
                $problems[] = "round {$round}: {$tally['all']} ".implode('; ', $found).' open sessions '.mnOpenSessions();
            }
        }

        expect($problems)->toBe([]);
    });
});
