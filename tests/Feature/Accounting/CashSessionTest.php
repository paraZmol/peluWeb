<?php

use App\Actions\Accounting\CountCashSession;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\CashSession;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 5.3: opening and closing the till. "Now" starts on Wednesday
| 2026-10-07 08:30 in Lima; the visit costs S/ 35.00 with a 30 % deposit.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:30', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 30])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create();
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);
});

function cashBook(string $time): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: SalonClient::factory()->for(test()->salon)->create(),
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima'),
        source: BookingSource::Client,
    ));
}

/** Deposit by the given method now, then the visit at its time paid as given. */
function cashVisit(string $time, PaymentMethod $deposit, Checkout $checkout): Booking
{
    $status = app(ChangeBookingStatus::class);
    $booking = $status->confirm(cashBook($time), test()->owner, $deposit);
    test()->travelTo(CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima'));
    $status->start($booking, test()->owner);

    return $status->complete($booking, test()->owner, $checkout);
}

function openSession(): CashSession
{
    return CashSession::withoutGlobalScope('salon')->where('salon_id', test()->salon->id)->whereNull('closed_at')->sole();
}

it('opens the till once, with its float', function () {
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '50.00'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '80'])->assertInertiaFlash('toast.type', 'error');

    expect(openSession()->opening_cash_cents)->toBe(5000)
        ->and(openSession()->opened_by)->toBe($this->owner->id)
        ->and(CashSession::withoutGlobalScope('salon')->count())->toBe(1);
});

it('expects the float plus cash in minus cash out, and leaves other methods aside', function () {
    // Before opening: a cash deposit that must not count.
    app(ChangeBookingStatus::class)->confirm(cashBook('17:00'), $this->owner, PaymentMethod::Cash);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:45', 'America/Lima'));
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '50']);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:50', 'America/Lima'));

    // Deposit 10.50 by Yape; balance 24.50 + tip 2.00 = 26.50: 20.00 cash + 6.50 Plin.
    cashVisit('10:00', PaymentMethod::Yape, new Checkout(tipCents: 200, parts: ['cash' => 2000, 'plin' => 650]));
    // Deposit 10.50 in cash; balance 24.50 in cash.
    cashVisit('11:00', PaymentMethod::Cash, new Checkout(parts: ['cash' => 2450]));
    // Supplies paid in cash, and the rent by transfer.
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '12.30', 'method' => 'cash', 'date' => '2026-10-07']);
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '900', 'method' => 'transfer', 'date' => '2026-10-07']);
    // Yesterday's cash expense is not from this drawer.
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '40', 'method' => 'cash', 'date' => '2026-10-06']);

    $count = app(CountCashSession::class)->handle(openSession());

    // 50.00 + 20.00 + 10.50 + 24.50 − 12.30 = 92.70
    expect($count->expectedCashCents())->toBe(9270)
        ->and([$count->inCents(PaymentMethod::Yape), $count->inCents(PaymentMethod::Plin), $count->outCents(PaymentMethod::Transfer)])->toBe([1050, 650, 90000])
        ->and($count->netCents(PaymentMethod::Cash))->toBe(4270);

    $this->actingAs($this->owner)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
        ->component('owner/cash/index', false)
        ->where('session.openingCash', '50.00')
        ->where('session.expectedCash', '92.70')
        ->where('session.openedAt', '2026-10-07 08:45')
        ->where('session.methods.0', ['value' => 'cash', 'label' => 'Efectivo', 'in' => '55.00', 'out' => '12.30', 'netCents' => 4270])
        ->has('session.methods', 4)
        ->has('history', 0));
});

it('closes against the count, freezes the result and reports the difference', function () {
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '50']);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:50', 'America/Lima'));
    cashVisit('10:00', PaymentMethod::Cash, new Checkout(parts: ['cash' => 2450]));
    $session = openSession();

    $this->travelTo(CarbonImmutable::parse('2026-10-07 19:00', 'America/Lima'));
    $this->actingAs($this->owner)->post(route('cash.close', $session), ['amount' => '83.00', 'note' => 'Faltaron dos soles'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('cash.close', $session), ['amount' => '85.00'])->assertInertiaFlash('toast.type', 'error');

    $session->refresh();

    // 50.00 + 10.50 + 24.50 = 85.00 expected; 83.00 counted.
    expect([$session->expected_cash_cents, $session->counted_cash_cents, $session->differenceCents()])->toBe([8500, 8300, -200])
        ->and($session->closed_by)->toBe($this->owner->id);

    // Money that moves afterwards belongs to no closed session.
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '10', 'method' => 'cash', 'date' => '2026-10-07']);

    expect($session->refresh()->expected_cash_cents)->toBe(8500)
        ->and(app(CountCashSession::class)->handle($session)->expectedCashCents())->toBe(8500);

    $this->actingAs($this->owner)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
        ->where('session', null)
        ->where('history.0.differenceCents', -200)
        ->where('history.0.countedCash', '83.00')
        ->where('history.0.closedAt', '2026-10-07 19:00')
        ->where('history.0.note', 'Faltaron dos soles'));

    // The next day starts a new one.
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '83'])->assertRedirect();
    expect(openSession()->id)->not->toBe($session->id);
});

it('rejects amounts that are not money', function (string $amount) {
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => $amount])->assertSessionHasErrors('amount');

    expect(CashSession::withoutGlobalScope('salon')->count())->toBe(0);
})->with(['-10', '10.555', 'cien', '']);

it('needs the cash permission and stays inside its salon', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '50']);
    $session = openSession();

    $this->actingAs($member->user)->get(route('cash.index'))->assertForbidden();
    $this->actingAs($member->user)->post(route('cash.open'), ['amount' => '1'])->assertForbidden();
    $this->actingAs($member->user)->post(route('cash.close', $session), ['amount' => '1'])->assertForbidden();

    $intruder = User::factory()->create();
    app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);
    $this->actingAs($intruder)->post(route('cash.close', $session->id), ['amount' => '0'])->assertNotFound();
    $this->actingAs($intruder)->post(route('cash.open'), ['amount' => '5'])->assertRedirect();

    $member->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
    $this->actingAs($member->user->fresh())->post(route('cash.close', $session), ['amount' => '50'])->assertRedirect();

    expect($session->refresh()->isOpen())->toBeFalse()
        ->and($session->differenceCents())->toBe(0)
        ->and(CashSession::withoutGlobalScope('salon')->whereNull('closed_at')->count())->toBe(1);
});
