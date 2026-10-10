<?php

use App\Actions\Accounting\CountCashSession;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\CashSession;
use App\Models\CommissionSettlement;
use App\Models\Payment;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 5 against the booking's state machine: charging must never leave a
| visit and its money telling two different stories. "Now" starts on
| Wednesday 2026-10-07 08:00 in Lima; the visit costs S/ 35.00 with a 30 %
| deposit (S/ 10.50), so S/ 24.50 are left to collect. Ana earns 40 %.
*/

beforeEach(function () {
    chkAt('08:00');

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['timezone' => 'America/Lima', 'deposit_percent' => 30]);
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->ana = chkStaff('Ana');
});

function chkAt(string $time, string $date = '2026-10-07'): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} {$time}", 'America/Lima'));
}

function chkStaff(string $name): Staff
{
    $staff = Staff::factory()->for(test()->salon)->working([3, 4], '09:00', '18:00')->create(['name' => $name]);
    $staff->services()->attach(test()->cut->id);
    $staff->forceFill(['commission_percent' => 40])->save();

    return $staff;
}

/** A booking for today at 10:00 (or the given time), waiting for its deposit. */
function chkPending(string $time = '10:00', ?Service $service = null): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: SalonClient::factory()->for(test()->salon)->create(),
        selections: [['service_id' => ($service ?? test()->cut)->id, 'variant_id' => null]],
        staff: test()->ana,
        startsAt: CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima'),
        source: BookingSource::Client,
    ));
}

/** Deposit paid by Yape. */
function chkConfirmed(string $time = '10:00'): Booking
{
    return app(ChangeBookingStatus::class)->confirm(chkPending($time), test()->owner, PaymentMethod::Yape);
}

/** The client is in the chair at 10:00: S/ 24.50 left to collect. */
function chkInService(): Booking
{
    $booking = chkConfirmed();
    chkAt('10:00');

    return app(ChangeBookingStatus::class)->start($booking, test()->owner);
}

/**
 * @return list<array{string, string, int}> kind, method and céntimos
 */
function chkMoney(Booking $booking): array
{
    return Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->orderBy('id')->get()
        ->map(fn (Payment $payment): array => [$payment->kind->value, $payment->method->value, $payment->amount_cents])->all();
}

/**
 * @param  array<string, mixed>  $payload
 */
function chkComplete(Booking $booking, array $payload = ['payments' => [['method' => 'cash', 'amount' => '24.50']]]): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('agenda.bookings.complete', $booking), $payload);
}

describe('a visit that is not in service cannot be charged', function () {
    it('refuses to complete it and records no charge', function (Closure $make, BookingStatus $stays) {
        $booking = $make();
        $before = chkMoney($booking);

        // With a checkout, in full, and straight through the action.
        expect(chkComplete($booking)->getStatusCode())->toBeLessThan(500)
            ->and(chkComplete($booking, [])->getStatusCode())->toBeLessThan(500)
            ->and(fn () => app(ChangeBookingStatus::class)->complete($booking, $this->owner))->toThrow(BookingException::class);

        $booking->refresh();

        expect($booking->status)->toBe($stays)
            ->and($booking->charged_cents)->toBeNull()
            ->and($booking->commission_cents)->toBeNull()
            ->and(chkMoney($booking))->toBe($before);
    })->with([
        'pending, deposit not paid' => [fn () => chkPending(), BookingStatus::Pending],
        'pending with a hold that ran out' => [function () {
            $booking = chkPending();
            chkAt('08:31');

            return $booking;
        }, BookingStatus::Pending],
        'expired' => [function () {
            $booking = chkPending();
            chkAt('08:31');

            return app(ChangeBookingStatus::class)->expire($booking);
        }, BookingStatus::Expired],
        'confirmed, not started' => [fn () => chkConfirmed(), BookingStatus::Confirmed],
        'cancelled by the salon' => [fn () => app(ChangeBookingStatus::class)->cancel(chkConfirmed(), test()->owner, byClient: false), BookingStatus::Cancelled],
        'cancelled by the client' => [fn () => app(ChangeBookingStatus::class)->cancel(chkConfirmed(), null, byClient: true), BookingStatus::Cancelled],
        'no-show' => [function () {
            $booking = chkConfirmed();
            chkAt('10:30');

            return app(ChangeBookingStatus::class)->markNoShow($booking, test()->owner);
        }, BookingStatus::NoShow],
    ]);

    it('keeps the first checkout when a completed visit is charged again with other numbers', function () {
        $booking = chkInService();
        chkComplete($booking, ['discount' => '4.50', 'payments' => [['method' => 'cash', 'amount' => '20.00']]]);

        chkComplete($booking)->assertInertiaFlash('toast.type', 'error');
        chkComplete($booking, ['discount' => '24.50', 'payments' => []])->assertInertiaFlash('toast.type', 'error');
        chkComplete($booking, [])->assertInertiaFlash('toast.type', 'error');

        expect([$booking->refresh()->charged_cents, $booking->discount_cents, $booking->commission_cents])->toBe([3050, 450, 1220])
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050], ['charge', 'cash', 2000]])
            ->and($booking->events()->where('to_status', 'completed')->count())->toBe(1);
    });

    it('cannot go anywhere else once it is completed, cancelled or marked as a no-show', function () {
        $completed = chkInService();
        chkComplete($completed);
        $routes = ['agenda.bookings.cancel', 'agenda.bookings.no-show', 'agenda.bookings.start', 'agenda.bookings.confirm', 'agenda.bookings.refund-deposit'];

        foreach ($routes as $route) {
            $this->actingAs($this->owner)->post(route($route, $completed))->assertInertiaFlash('toast.type', 'error');
        }

        expect($completed->refresh()->status)->toBe(BookingStatus::Completed)
            ->and(chkMoney($completed))->toBe([['deposit', 'yape', 1050], ['charge', 'cash', 2450]]);
    });
});

describe('charging more or less than what is owed', function () {
    it('leaves the visit in service and records nothing', function (array $payload) {
        $booking = chkInService();

        expect(chkComplete($booking, $payload)->getStatusCode())->toBeLessThan(500);

        expect($booking->refresh()->status)->toBe(BookingStatus::InService)
            ->and([$booking->charged_cents, $booking->commission_cents, $booking->discount_cents])->toBe([null, null, 0])
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050]]);
    })->with([
        'a céntimo too much' => [['payments' => [['method' => 'cash', 'amount' => '24.51']]]],
        'the full price again in a second method' => [['payments' => [['method' => 'cash', 'amount' => '24.50'], ['method' => 'yape', 'amount' => '24.50']]]],
        'discount a céntimo above the balance' => [['discount' => '24.51', 'payments' => []]],
        'discount above the price' => [['discount' => '40.00', 'payments' => []]],
        'discount of a million' => [['discount' => '999999.99', 'payments' => []]],
        'right discount, payments for the full balance' => [['discount' => '5.00', 'payments' => [['method' => 'cash', 'amount' => '24.50']]]],
        'right payments, split in four methods' => [['payments' => [['method' => 'cash', 'amount' => '10'], ['method' => 'yape', 'amount' => '5'], ['method' => 'plin', 'amount' => '5'], ['method' => 'card', 'amount' => '4.50']]]],
        'only a zero' => [['payments' => [['method' => 'cash', 'amount' => '0']]]],
        'an empty discount and nothing else' => [['discount' => '']],
        'payments that are not a list' => [['payments' => 'cash:24.50']],
        'a payment that is not a pair' => [['payments' => ['24.50']]],
        'an amount that is a list' => [['payments' => [['method' => 'cash', 'amount' => ['24.50']]]]],
        'a method that is a list' => [['payments' => [['method' => ['cash'], 'amount' => '24.50']]]],
        'a discount that is a list' => [['discount' => ['0'], 'payments' => [['method' => 'cash', 'amount' => '24.50']]]],
        'scientific notation' => [['payments' => [['method' => 'cash', 'amount' => '2450e-2']]]],
        'full-width digits' => [['payments' => [['method' => 'cash', 'amount' => '２４.５０']]]],
        'a sign' => [['payments' => [['method' => 'cash', 'amount' => '+24.50']]]],
        'a negative discount paid on top' => [['discount' => '-5.00', 'payments' => [['method' => 'cash', 'amount' => '29.50']]]],
    ]);

    it('refuses negative numbers that would add up, also below the form', function (Checkout $checkout) {
        $booking = chkInService();

        expect(fn () => app(ChangeBookingStatus::class)->complete($booking, $this->owner, $checkout))->toThrow(PaymentException::class);

        expect($booking->refresh()->status)->toBe(BookingStatus::InService)
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050]]);
    })->with([
        'a negative part offset by another' => [new Checkout(parts: ['cash' => 3000, 'yape' => -550])],
        'a negative discount (a surcharge)' => [new Checkout(discountCents: -500, parts: ['cash' => 2950])],
        'a discount that eats the deposit' => [new Checkout(discountCents: 3500, parts: [])],
        'four methods' => [new Checkout(parts: ['cash' => 1000, 'yape' => 500, 'plin' => 500, 'card' => 450])],
    ]);

    it('accepts a discount of the whole balance: nothing changes hands and the deposit is all it cost', function () {
        $booking = chkInService();

        chkComplete($booking, ['discount' => '24.50', 'payments' => []])->assertRedirect();
        $booking->refresh();

        // 40 % of the S/ 10.50 that the visit ended up costing.
        expect($booking->status)->toBe(BookingStatus::Completed)
            ->and([$booking->charged_cents, $booking->discount_cents, $booking->commission_cents])->toBe([1050, 2450, 420])
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050]]);
    });

    it('drops the methods that received nothing and keeps the total to the céntimo', function () {
        $booking = chkInService();

        chkComplete($booking, ['payments' => [
            ['method' => 'cash', 'amount' => '24,49'], ['method' => 'yape', 'amount' => '0'], ['method' => 'plin', 'amount' => '0.01'], ['method' => 'card', 'amount' => '0,00'],
        ]])->assertRedirect();

        expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050], ['charge', 'cash', 2449], ['charge', 'plin', 1]])
            ->and((int) Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->sum('amount_cents'))->toBe($booking->charged_cents);
    });

    it('completes a free visit without any movement, and pays no fixed commission for it', function () {
        $gift = Service::factory()->for($this->salon)->create(['price_cents' => 0, 'active_minutes' => 30]);
        $this->ana->services()->attach($gift->id, ['commission_cents' => 500]);
        $booking = chkPending('10:00', $gift);
        chkAt('10:00');

        expect($booking->status)->toBe(BookingStatus::Confirmed);
        app(ChangeBookingStatus::class)->start($booking, $this->owner);
        chkComplete($booking, [])->assertRedirect();
        $booking->refresh();

        expect($booking->status)->toBe(BookingStatus::Completed)
            ->and([$booking->charged_cents, $booking->commission_cents])->toBe([0, 0])
            ->and(chkMoney($booking))->toBe([]);

        // And it cannot be turned into a charge.
        $other = chkPending('11:00', $gift);
        chkAt('11:00');
        app(ChangeBookingStatus::class)->start($other, $this->owner);
        chkComplete($other, ['payments' => [['method' => 'cash', 'amount' => '5.00']]])->assertInertiaFlash('toast.type', 'error');

        expect($other->refresh()->status)->toBe(BookingStatus::InService);
    });
});

describe('moving or cancelling a booking that already has money', function () {
    it('keeps the deposit with the booking when it is moved, and charges only the balance afterwards', function () {
        $beto = chkStaff('Beto');
        $booking = chkConfirmed();

        // To tomorrow 15:00, and to Beto.
        app(RescheduleBooking::class)->handle(new BookingMove(
            $booking, CarbonImmutable::parse('2026-10-08 15:00', 'America/Lima'), byClient: false, actor: $this->owner, staff: $beto,
        ));
        $booking->refresh();

        expect($booking->staff_id)->toBe($beto->id)
            ->and([$booking->price_cents, $booking->deposit_cents, $booking->depositPaidCents()])->toBe([3500, 1050, 1050])
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050]]);

        chkAt('15:00', '2026-10-08');
        app(ChangeBookingStatus::class)->start($booking, $this->owner);
        chkComplete($booking, ['payments' => [['method' => 'cash', 'amount' => '35.00']]])->assertInertiaFlash('toast.type', 'error');
        chkComplete($booking)->assertRedirect();
        $booking->refresh();

        expect($booking->status)->toBe(BookingStatus::Completed)
            ->and($booking->charged_cents)->toBe(3500)
            ->and((int) Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->sum('amount_cents'))->toBe(3500)
            ->and(Payment::withoutGlobalScope('salon')->whereNull('booking_id')->count())->toBe(0);
    });

    /*
    | KNOWN GAP (BAJO). `payments.staff_id` is documented as "who attended,
    | for commissions". The deposit is written when it is received; if the
    | visit is later handed to a colleague, that row keeps the first
    | professional. Nothing reads the column today (reports and the export
    | go through bookings.staff_id), so no figure is wrong yet.
    | Where to fix: RescheduleBooking, when the professional changes.
    */
    it('says who attended on every movement of a visit that was handed to a colleague', function () {
        $beto = chkStaff('Beto');
        $booking = chkConfirmed();
        app(RescheduleBooking::class)->handle(new BookingMove(
            $booking, CarbonImmutable::parse('2026-10-08 15:00', 'America/Lima'), byClient: false, actor: $this->owner, staff: $beto,
        ));
        chkAt('15:00', '2026-10-08');
        app(ChangeBookingStatus::class)->start($booking->refresh(), $this->owner);
        chkComplete($booking);

        expect(Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->pluck('staff_id')->unique()->all())->toBe([$beto->id]);
    })->skip('Known gap (BAJO): the deposit keeps the first professional in payments.staff_id after RescheduleBooking hands the visit over.');

    it('leaves no movement behind when a booking is cancelled before its deposit arrived', function () {
        $booking = chkPending();

        $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $booking))->assertRedirect();
        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'cash'])->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->owner)->post(route('agenda.bookings.confirm', $booking), ['method' => 'cash'])->assertInertiaFlash('toast.type', 'error');

        expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->deposit_paid_at)->toBeNull()
            ->and(chkMoney($booking))->toBe([]);
    });

    it('gives the deposit back once and then lets nothing else move for that booking', function () {
        $booking = chkConfirmed();
        $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $booking));
        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'cash'])->assertRedirect();

        foreach (['agenda.bookings.refund-deposit', 'agenda.bookings.confirm', 'agenda.bookings.start', 'agenda.bookings.complete', 'agenda.bookings.cancel', 'agenda.bookings.no-show'] as $route) {
            $this->actingAs($this->owner)->post(route($route, $booking), ['method' => 'cash'])->assertInertiaFlash('toast.type', 'error');
        }

        $booking->refresh();

        expect($booking->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->depositPaidCents())->toBe(0)
            ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050], ['refund', 'cash', 1050]])
            ->and(Payment::withoutGlobalScope('salon')->get()->sum(fn (Payment $payment) => $payment->signedCents()))->toBe(0);
    });

    it('keeps the deposit of a no-show and of a late cancellation by the client, with no way to give it back', function () {
        $missed = chkConfirmed('10:00');
        $late = chkConfirmed('11:00');
        app(ChangeBookingStatus::class)->cancel($late, null, byClient: true);
        chkAt('10:30');
        app(ChangeBookingStatus::class)->markNoShow($missed, $this->owner);

        foreach ([$missed, $late] as $booking) {
            $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'cash'])->assertInertiaFlash('toast.type', 'error');

            expect($booking->refresh()->deposit_refundable)->toBeFalse()
                ->and(chkMoney($booking))->toBe([['deposit', 'yape', 1050]]);
        }
    });

    it('does not let a moved booking be refunded and attended at the same time', function () {
        $booking = chkConfirmed();
        app(RescheduleBooking::class)->handle(new BookingMove($booking, CarbonImmutable::parse('2026-10-07 15:00', 'America/Lima'), byClient: false, actor: $this->owner));

        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'cash'])->assertInertiaFlash('toast.type', 'error');
        chkAt('15:00');
        app(ChangeBookingStatus::class)->start($booking->refresh(), $this->owner);
        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'cash'])->assertInertiaFlash('toast.type', 'error');
        chkComplete($booking)->assertRedirect();

        expect(chkMoney($booking))->toBe([['deposit', 'yape', 1050], ['charge', 'cash', 2450]]);
    });
});

describe('the till against the clock', function () {
    /*
    | KNOWN FAILURE (MEDIO). `payments.paid_at`, `expenses.paid_at` and
    | `cash_sessions.closed_at` are stored to the second, and a session
    | counts `opened_at <= paid_at < closed_at`. A charge collected in the
    | same second the till is closed, but before it, is dated at exactly
    | `closed_at`: it is left out of that session and of the next one
    | (which opens later), although the cash was in the drawer when it was
    | counted. The cashier sees a surplus nobody can explain.
    | Where to fix: CountCashSession / CloseCashSession together with the
    | precision of those three columns (see the report).
    */
    it('counts a cash charge collected a moment before closing, in the same second', function () {
        $booking = chkInService();
        chkAt('08:30:00');
        // Opened earlier that day (a row, so the test owns the instant).
        $session = new CashSession;
        $session->forceFill(['salon_id' => $this->salon->id, 'opened_at' => now(), 'opening_cash_cents' => 5000])->save();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 19:00:00.200000', 'America/Lima'));
        chkComplete($booking)->assertRedirect();

        // Half a second later, with those S/ 24.50 in the drawer.
        $this->travelTo(CarbonImmutable::parse('2026-10-07 19:00:00.700000', 'America/Lima'));
        $this->actingAs($this->owner)->post(route('cash.close', $session), ['amount' => '74.50'])->assertRedirect();
        $session->refresh();

        expect($session->expected_cash_cents)->toBe(7450)
            ->and($session->differenceCents())->toBe(0);
    })->skip('Known failure (MEDIO): a charge in the same second as the close, before it, falls out of every cash session.');

    it('never counts one movement in two consecutive sessions', function () {
        $booking = chkInService();
        $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '50']);
        $first = CashSession::withoutGlobalScope('salon')->sole();

        // Charge, close and reopen in the very same instant.
        chkComplete($booking)->assertRedirect();
        $this->actingAs($this->owner)->post(route('cash.close', $first), ['amount' => '50']);
        $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '50']);
        $second = CashSession::withoutGlobalScope('salon')->whereNull('closed_at')->sole();

        $inFirst = app(CountCashSession::class)->handle($first->refresh())->inCents(PaymentMethod::Cash);
        $inSecond = app(CountCashSession::class)->handle($second)->inCents(PaymentMethod::Cash);

        expect($inFirst + $inSecond)->toBe(2450);
    });
});

describe('paying commissions', function () {
    /*
    | KNOWN GAP (MEDIO). The screen shows what is pending and "Liquidar"
    | sends only the period and the method. If a visit of the period is
    | completed between loading the screen and pressing the button, the
    | settlement silently includes it: the owner hands over the S/ 14.00
    | they read and the books say S/ 28.00 left the till.
    | Where to fix: SettleCommissions::handle should receive the total the
    | person confirmed and refuse (PaymentException) when the amount under
    | the lock is another one; CommissionController::settle passes it.
    */
    it('pays what the owner saw on the screen, or refuses', function () {
        $first = chkInService();
        chkComplete($first);
        $other = chkConfirmed('11:00');
        $day = ['from' => '2026-10-07', 'to' => '2026-10-07', 'method' => 'cash'];
        $seen = null;

        $this->actingAs($this->owner)->get(route('commissions.index', ['desde' => '2026-10-07', 'hasta' => '2026-10-07']))
            ->assertInertia(function (Assert $page) use (&$seen) {
                $seen = collect($page->toArray()['props']['staff'])->firstWhere('name', 'Ana')['pendingCents'];

                return $page;
            });

        // Meanwhile Ana finishes another visit of the same day.
        chkAt('11:00');
        app(ChangeBookingStatus::class)->start($other, $this->owner);
        chkComplete($other);

        $this->actingAs($this->owner)->post(route('commissions.settle', $this->ana), [...$day, 'expected' => $seen])->assertInertiaFlash('toast.type', 'error');
        $paid = (int) CommissionSettlement::withoutGlobalScope('salon')->sum('total_cents');

        expect($seen)->toBe(1400)->and($paid)->toBe(0);
    });

    it('never pays the same visit in two settlements, whatever periods overlap', function () {
        $visit = chkInService();
        chkComplete($visit);
        $settle = fn (string $from, string $to) => $this->actingAs($this->owner)->post(route('commissions.settle', $this->ana), ['from' => $from, 'to' => $to, 'method' => 'cash']);

        $settle('2026-10-07', '2026-10-07')->assertRedirect();
        $settle('2026-10-01', '2026-10-07')->assertInertiaFlash('toast.type', 'error');
        $settle('2026-09-01', '2026-10-31')->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->owner)->post(route('commissions.settle', $this->ana))->assertInertiaFlash('toast.type', 'error');

        expect(CommissionSettlement::withoutGlobalScope('salon')->count())->toBe(1)
            ->and((int) DB::table('expenses')->where('salon_id', $this->salon->id)->sum('amount_cents'))->toBe(1400)
            ->and($visit->refresh()->commission_settlement_id)->not->toBeNull();
    });
});
