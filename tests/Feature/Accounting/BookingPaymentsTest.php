<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 5.1: every sol that changes hands for a booking becomes a payment.
| "Now" is Wednesday 2026-10-07 08:00 in Lima; the visit costs S/ 35.00 and
| the salon asks for a 30 % deposit (S/ 10.50).
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['timezone' => 'America/Lima', 'deposit_percent' => 30]);
    $this->barber = SalonMember::factory()->for($this->salon)->create();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['user_id' => $this->barber->user_id]);
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);
});

function payBook(string $time, ?Salon $salon = null, ?Staff $staff = null, ?Service $service = null): Booking
{
    $salon ??= test()->salon;

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $salon->refresh(),
        client: SalonClient::factory()->for($salon)->create(),
        selections: [['service_id' => ($service ?? test()->cut)->id, 'variant_id' => null]],
        staff: $staff ?? test()->staff,
        startsAt: CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima'),
        source: BookingSource::Client,
    ));
}

/**
 * @return list<array{string, string, int}>
 */
function paymentsOf(Booking $booking): array
{
    return Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->orderBy('id')->get()
        ->map(fn (Payment $payment): array => [$payment->kind->value, $payment->method->value, $payment->amount_cents])->all();
}

/** A visit with its deposit paid by Yape and already in service at 10:00. */
function payInService(): Booking
{
    $booking = payBook('10:00');
    test()->actingAs(test()->owner)->post(route('agenda.bookings.confirm', $booking), ['method' => 'yape']);
    test()->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    test()->actingAs(test()->owner)->post(route('agenda.bookings.start', $booking));

    return $booking->refresh();
}

it('records the deposit with its method when the team says it arrived', function () {
    $booking = payBook('10:00');

    $this->actingAs($this->owner)->post(route('agenda.bookings.confirm', $booking), ['method' => 'yape'])->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and(paymentsOf($booking))->toBe([['deposit', 'yape', 1050]])
        ->and(Payment::withoutGlobalScope('salon')->sole()->recorded_by)->toBe($this->owner->id)
        ->and(Payment::withoutGlobalScope('salon')->sole()->staff_id)->toBe($this->staff->id);
});

it('collects the balance split in two methods, with a discount', function () {
    $booking = payInService();

    // 35.00 − 5.00 discount − 10.50 deposit = 19.50
    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), [
        'discount' => '5.00',
        'payments' => [['method' => 'cash', 'amount' => '17.00'], ['method' => 'plin', 'amount' => '2,50']],
    ])->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and([$booking->charged_cents, $booking->discount_cents])->toBe([3000, 500])
        ->and(paymentsOf($booking))->toBe([['deposit', 'yape', 1050], ['charge', 'cash', 1700], ['charge', 'plin', 250]]);

    // Everything received for the visit = what it cost, to the céntimo.
    expect(Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->sum('amount_cents'))
        ->toEqual($booking->charged_cents);
});

it('charges in full when nothing is sent, and shows the agenda what is left to collect', function () {
    $booking = payInService();

    $this->actingAs($this->owner)->get(route('agenda.index'))->assertInertia(fn (Assert $page) => $page
        ->where('bookings.0.balance', '24.50')
        ->where('bookings.0.depositPaid', '10.50')
        ->where('bookings.0.charged', null)
        ->has('paymentMethods', 6));

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking));

    expect($booking->refresh()->charged_cents)->toBe(3500)
        ->and(paymentsOf($booking))->toBe([['deposit', 'yape', 1050], ['charge', 'other', 2450]]);
});

it('leaves the visit in service and records nothing when the payments do not add up', function (array $payload) {
    $booking = payInService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), $payload);

    expect($booking->refresh()->status)->toBe(BookingStatus::InService)
        ->and($booking->charged_cents)->toBeNull()
        ->and(paymentsOf($booking))->toBe([['deposit', 'yape', 1050]]);
})->with([
    'short by a céntimo' => [['payments' => [['method' => 'cash', 'amount' => '24.49']]]],
    'forgets the deposit' => [['payments' => [['method' => 'cash', 'amount' => '35.00']]]],
    'discount below the paid deposit' => [['discount' => '30.00', 'payments' => []]],
    'unknown method' => [['payments' => [['method' => 'bitcoin', 'amount' => '24.50']]]],
    'same method twice' => [['payments' => [['method' => 'cash', 'amount' => '20.00'], ['method' => 'cash', 'amount' => '4.50']]]],
    'three decimals' => [['payments' => [['method' => 'cash', 'amount' => '24.500']]]],
    'negative amount' => [['payments' => [['method' => 'cash', 'amount' => '-24.50']]]],
]);

it('does not charge the same visit twice', function () {
    $booking = payInService();
    $pay = ['payments' => [['method' => 'cash', 'amount' => '24.50']]];

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), $pay);
    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), $pay)->assertInertiaFlash('toast.type', 'error');

    expect(paymentsOf($booking))->toHaveCount(2);
});

it('lets whoever attends collect their own visit, but not a colleague\'s', function () {
    $booking = payInService();
    $colleague = SalonMember::factory()->for($this->salon)->create();
    $pay = ['payments' => [['method' => 'cash', 'amount' => '24.50']]];

    $this->actingAs($colleague->user)->post(route('agenda.bookings.complete', $booking), $pay)->assertForbidden();
    $this->actingAs($this->barber->user)->post(route('agenda.bookings.complete', $booking), $pay)->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed);
});

it('keeps discounts for whoever handles cash, and tips out of the books', function () {
    $booking = payInService();

    // The barber attends it but has no cash permission: no discounts.
    $this->actingAs($this->barber->user)->post(route('agenda.bookings.complete', $booking), [
        'discount' => '24.50', 'payments' => [],
    ])->assertForbidden();
    $this->actingAs($this->barber->user)->get(route('agenda.index'))
        ->assertInertia(fn (Assert $page) => $page->where('bookings.0.canDiscount', false));

    // A tip is between the client and the barber: money beyond the price is refused.
    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), [
        'tip' => '5.00', 'payments' => [['method' => 'cash', 'amount' => '29.50']],
    ])->assertInertiaFlash('toast.type', 'error');

    expect($booking->refresh()->status)->toBe(BookingStatus::InService)
        ->and(paymentsOf($booking))->toHaveCount(1);

    $this->barber->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
    $this->actingAs($this->barber->user->fresh())->post(route('agenda.bookings.complete', $booking), [
        'discount' => '4.50', 'payments' => [['method' => 'cash', 'amount' => '20.00']],
    ])->assertRedirect();

    expect($booking->refresh()->charged_cents)->toBe(3050)
        ->and(array_sum(array_column(paymentsOf($booking), 2)))->toBe(3050);
});

describe('giving a deposit back', function () {
    beforeEach(function () {
        $this->booking = payBook('10:00');
        app(ChangeBookingStatus::class)->confirm($this->booking, $this->owner, PaymentMethod::Yape);
    });

    it('records money going out, once, when the salon cancelled', function () {
        $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $this->booking));
        $this->actingAs($this->owner)->get(route('agenda.index'))
            ->assertInertia(fn (Assert $page) => $page->where('bookings.0.actions', ['refundDeposit']));

        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $this->booking), ['method' => 'yape'])->assertRedirect();
        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $this->booking), ['method' => 'yape'])
            ->assertInertiaFlash('toast.type', 'error');

        expect(paymentsOf($this->booking))->toBe([['deposit', 'yape', 1050], ['refund', 'yape', 1050]])
            ->and($this->booking->refresh()->deposit_refunded_at)->not->toBeNull()
            ->and(Payment::withoutGlobalScope('salon')->get()->sum(fn (Payment $payment) => $payment->signedCents()))->toBe(0);
    });

    it('refuses when the client lost it or the visit is still on', function () {
        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $this->booking))->assertInertiaFlash('toast.type', 'error');

        // Cancelled by the client two hours before: inside the 24 h window.
        app(ChangeBookingStatus::class)->cancel($this->booking, null, byClient: true);
        $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $this->booking))->assertInertiaFlash('toast.type', 'error');

        expect(paymentsOf($this->booking))->toHaveCount(1);
    });

    it('needs the cash permission', function () {
        app(ChangeBookingStatus::class)->cancel($this->booking, $this->owner, byClient: false);

        $this->actingAs($this->barber->user)->post(route('agenda.bookings.refund-deposit', $this->booking))->assertForbidden();

        $this->barber->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
        $this->actingAs($this->barber->user->fresh())->post(route('agenda.bookings.refund-deposit', $this->booking))->assertRedirect();

        expect(paymentsOf($this->booking))->toHaveCount(2);
    });
});

it('keeps another salon away from this one\'s bookings and payments', function () {
    $booking = payInService();
    $intruder = User::factory()->create();
    Salon::factory()->ownedBy($intruder)->create();

    $this->actingAs($intruder)->post(route('agenda.bookings.complete', $booking->id), ['payments' => [['method' => 'cash', 'amount' => '24.50']]])->assertNotFound();
    $this->actingAs($intruder)->post(route('agenda.bookings.confirm', $booking->id), ['method' => 'cash'])->assertNotFound();
    $this->actingAs($intruder)->post(route('agenda.bookings.refund-deposit', $booking->id))->assertNotFound();

    expect($booking->refresh()->status)->toBe(BookingStatus::InService)
        ->and(paymentsOf($booking))->toHaveCount(1);

    // The global scope hides them too.
    $this->actingAs($intruder)->get(route('agenda.index'));
    expect(Payment::count())->toBe(0)
        ->and(PaymentKind::Refund->isOutgoing())->toBeTrue();
});
