<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 5.4: commission rules and paying a period. "Now" starts on Wednesday
| 2026-10-07 08:00 in Lima. Pedro earns 40 % in general, a fixed S/ 5.00
| for a beard trim (S/ 20.00) and 50 % for color (S/ 100.00).
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima'])->save();
    $this->pedro = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 15]);
    $this->color = Service::factory()->for($this->salon)->create(['name' => 'Color', 'price_cents' => 10000, 'active_minutes' => 60]);
    $this->pedro->services()->attach([$this->cut->id, $this->beard->id, $this->color->id]);

    $this->actingAs($this->owner)->put(route('commissions.rules.update', $this->pedro), [
        'percent' => 40,
        'services' => [['id' => $this->beard->id, 'amount' => '5.00'], ['id' => $this->color->id, 'percent' => 50]],
    ])->assertRedirect();
});

/**
 * A visit of Pedro on the given day and time, completed and paid in cash.
 *
 * @param  list<Service>  $services
 */
function commissionVisit(string $date, string $time, array $services, int $discountCents = 0, ?Staff $staff = null): Booking
{
    $test = test();
    $at = CarbonImmutable::parse("{$date} {$time}", 'America/Lima');
    $test->travelTo($at->subHours(2));

    $booking = app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: SalonClient::factory()->for($test->salon)->create(),
        selections: array_map(fn (Service $service): array => ['service_id' => $service->id, 'variant_id' => null], $services),
        staff: $staff ?? $test->pedro,
        startsAt: $at,
        source: BookingSource::Staff,
    ));

    $test->travelTo($at);
    $status = app(ChangeBookingStatus::class);
    $status->start($booking, $test->owner);

    return $status->complete($booking, $test->owner, new Checkout(discountCents: $discountCents, parts: ['cash' => $booking->price_cents - $discountCents]));
}

it('stores the rules, a fixed amount winning over a percent', function () {
    $this->actingAs($this->owner)->put(route('commissions.rules.update', $this->pedro), [
        'percent' => 40,
        'services' => [['id' => $this->beard->id, 'amount' => '5.00', 'percent' => 90], ['id' => $this->color->id, 'percent' => 50], ['id' => 999999, 'percent' => 100]],
    ]);

    $rules = $this->pedro->refresh()->services()->get()->mapWithKeys(fn (Service $service) => [
        $service->name => [$service->getRelationValue('pivot')->commission_percent, $service->getRelationValue('pivot')->commission_cents],
    ]);

    expect($this->pedro->commission_percent)->toBe(40)
        ->and($rules->all())->toBe(['Corte' => [null, null], 'Barba' => [null, 500], 'Color' => [50, null]]);
});

it('freezes the commission when the visit is completed', function () {
    // 40 % of 35.00 + fixed 5.00 = 19.00
    $plain = commissionVisit('2026-10-07', '10:00', [$this->cut, $this->beard]);
    // 10.00 off 135.00: cut 35.00 − 2.59 = 32.41 → 12.96; color 100.00 − 7.41 = 92.59 → 46.30
    $discounted = commissionVisit('2026-10-07', '11:00', [$this->cut, $this->color], discountCents: 1000);

    expect($plain->commission_cents)->toBe(1900)
        ->and($discounted->commission_cents)->toBe(1296 + 4630);

    // Raising the percent afterwards does not rewrite them.
    $this->actingAs($this->owner)->put(route('commissions.rules.update', $this->pedro), ['percent' => 90]);

    expect($plain->refresh()->commission_cents)->toBe(1900)
        ->and(commissionVisit('2026-10-07', '15:00', [$this->cut])->commission_cents)->toBe(3150);
});

it('pays a period once, as an expense, and leaves the rest pending', function () {
    $this->actingAs($this->owner)->post(route('cash.open'), ['amount' => '100']);
    $first = commissionVisit('2026-10-07', '10:00', [$this->cut]);      // 14.00
    $second = commissionVisit('2026-10-07', '11:00', [$this->beard]);   // 5.00
    $nextDay = commissionVisit('2026-10-08', '10:00', [$this->cut]);    // 14.00, outside the period
    $period = ['from' => '2026-10-07', 'to' => '2026-10-07', 'method' => 'cash'];

    $this->actingAs($this->owner)->get(route('commissions.index', ['desde' => '2026-10-07', 'hasta' => '2026-10-07']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('owner/commissions/index', false)
            ->where('from', '2026-10-07')->where('to', '2026-10-07')
            ->where('staff', fn ($staff) => collect($staff)->firstWhere('name', 'Pedro')['pending'] === '19.00'
                && collect($staff)->firstWhere('name', 'Pedro')['visits'] === 2
                && collect($staff)->firstWhere('name', 'Pedro')['sales'] === '55.00')
            ->where('canEditRules', true)
            ->has('history', 0));

    $this->actingAs($this->owner)->post(route('commissions.settle', $this->pedro), $period)->assertRedirect();
    $this->actingAs($this->owner)->post(route('commissions.settle', $this->pedro), $period)->assertInertiaFlash('toast.type', 'error');

    $settlement = CommissionSettlement::withoutGlobalScope('salon')->sole();
    $expense = Expense::withoutGlobalScope('salon')->sole();

    expect([$settlement->total_cents, $settlement->bookings_count, $settlement->paid_by])->toBe([1900, 2, $this->owner->id])
        ->and([$expense->id, $expense->amount_cents, $expense->method->value, $expense->category?->name])->toBe([$settlement->expense_id, 1900, 'cash', 'Sueldos y comisiones'])
        ->and($expense->description)->toBe('Comisiones de Pedro, del 07/10/2026 al 07/10/2026')
        ->and([$first->refresh()->commission_settlement_id, $second->refresh()->commission_settlement_id, $nextDay->refresh()->commission_settlement_id])
        ->toBe([$settlement->id, $settlement->id, null]);

    // The till sees the cash leave: 100 + 35 + 20 + 35 − 19 = 171.00
    $this->actingAs($this->owner)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page->where('session.expectedCash', '171.00'));

    // The payment cannot be edited or deleted from Gastos.
    $this->actingAs($this->owner)->delete(route('expenses.destroy', $expense))->assertInertiaFlash('toast.type', 'error');
    $this->actingAs($this->owner)->post(route('expenses.update', $expense), ['amount' => '1', 'method' => 'cash', 'date' => '2026-10-08'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($expense->refresh()->amount_cents)->toBe(1900)->and($expense->trashed())->toBeFalse();
});

it('falls back to this month when the period is nonsense', function (array $query) {
    $this->actingAs($this->owner)->get(route('commissions.index', $query))
        ->assertInertia(fn (Assert $page) => $page->where('from', '2026-10-01')->where('to', '2026-10-07'));
})->with([
    'nothing' => [[]],
    'garbage' => [['desde' => "2026-10-01' OR 1=1", 'hasta' => 'mañana']],
    'backwards' => [['desde' => '2026-10-07', 'hasta' => '2026-10-01']],
    'impossible day' => [['desde' => '2026-02-31', 'hasta' => '2026-10-07']],
    'the future' => [['desde' => '2026-10-01', 'hasta' => '2030-01-01']],
    'longer than a year' => [['desde' => '2020-01-01', 'hasta' => '2026-10-07']],
]);

it('keeps the rules for the owner and the payments for whoever handles cash', function () {
    commissionVisit('2026-10-07', '10:00', [$this->cut]);
    $member = SalonMember::factory()->for($this->salon)->create();
    $period = ['from' => '2026-10-07', 'to' => '2026-10-07', 'method' => 'yape'];

    $this->actingAs($member->user)->get(route('commissions.index'))->assertForbidden();
    $this->actingAs($member->user)->post(route('commissions.settle', $this->pedro), $period)->assertForbidden();
    $this->actingAs($member->user)->put(route('commissions.rules.update', $this->pedro), ['percent' => 100])->assertForbidden();

    $member->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
    $cashier = $member->user->fresh();

    $this->actingAs($cashier)->put(route('commissions.rules.update', $this->pedro), ['percent' => 100])->assertForbidden();
    $this->actingAs($cashier)->get(route('commissions.index'))->assertInertia(fn (Assert $page) => $page->where('canEditRules', false));
    $this->actingAs($cashier)->post(route('commissions.settle', $this->pedro), $period)->assertRedirect();

    expect($this->pedro->refresh()->commission_percent)->toBe(40)
        ->and(CommissionSettlement::withoutGlobalScope('salon')->sole()->total_cents)->toBe(1400);
});

it('rejects rules that are not percents or money', function (array $payload, string $field) {
    $this->actingAs($this->owner)->put(route('commissions.rules.update', $this->pedro), $payload)->assertSessionHasErrors($field);

    expect($this->pedro->refresh()->commission_percent)->toBe(40);
})->with([
    'over 100' => [['percent' => 101], 'percent'],
    'negative' => [['percent' => -1], 'percent'],
    'service over 100' => [['percent' => 10, 'services' => [['id' => 1, 'percent' => 150]]], 'services.0.percent'],
    'amount with words' => [['percent' => 10, 'services' => [['id' => 1, 'amount' => 'cinco']]], 'services.0.amount'],
]);

it('keeps another salon away from this one\'s professionals and commissions', function () {
    commissionVisit('2026-10-07', '10:00', [$this->cut]);
    $intruder = User::factory()->create();
    app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);

    $this->actingAs($intruder)->put(route('commissions.rules.update', $this->pedro->id), ['percent' => 100])->assertNotFound();
    $this->actingAs($intruder)->post(route('commissions.settle', $this->pedro->id), ['from' => '2026-10-07', 'to' => '2026-10-07', 'method' => 'cash'])->assertNotFound();
    $this->actingAs($intruder)->get(route('commissions.index'))->assertInertia(fn (Assert $page) => $page
        ->where('staff', fn ($staff) => collect($staff)->doesntContain('name', 'Pedro') && collect($staff)->sum('pendingCents') === 0));

    expect($this->pedro->refresh()->commission_percent)->toBe(40)
        ->and(CommissionSettlement::withoutGlobalScope('salon')->count())->toBe(0);
});
