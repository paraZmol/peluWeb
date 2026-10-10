<?php

use App\Actions\Accounting\SalonReport;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Accounting\Period;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
| Step 5.5: the report's numbers hold together to the céntimo. The story,
| on the salon's calendar (Lima), with a 30 % deposit on a S/ 35.00 cut:
|
|   Tue 6: Ana's first visit, paid in full in cash ............ income 35.00
|   Wed 7: Ana again, S/ 5.00 off and S/ 3.00 tip, by Yape ..... income 30.00
|          Beto's first visit, cut + beard (S/ 55.00), cash .... income 55.00
|          Caro paid her deposit and did not show up ........... income 10.50 kept
|          Dani paid his deposit; the salon cancelled, gave it back
|          rent S/ 100.00 by transfer, supplies S/ 12.30 cash .. expenses 112.30
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 30])->save();
    $this->pedro = Staff::factory()->for($this->salon)->working([1, 2, 3, 4], '09:00', '18:00')->create(['name' => 'Pedro', 'commission_percent' => 40]);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 15]);
    $this->pedro->services()->attach([$this->cut->id, $this->beard->id]);
    $this->status = app(ChangeBookingStatus::class);

    $ana = SalonClient::factory()->for($this->salon)->create();
    reportVisit('2026-10-06', '10:00', $ana, [$this->cut], new Checkout(parts: ['cash' => 2450]));
    reportVisit('2026-10-07', '10:00', $ana, [$this->cut], new Checkout(discountCents: 500, tipCents: 300, parts: ['yape' => 2250]));
    reportVisit('2026-10-07', '11:00', SalonClient::factory()->for($this->salon)->create(), [$this->cut, $this->beard], new Checkout(parts: ['cash' => 3850]));

    $noShow = reportBooking('2026-10-07', '13:00', SalonClient::factory()->for($this->salon)->create(), [$this->cut]);
    $this->status->confirm($noShow, $this->owner, PaymentMethod::Plin);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 13:30', 'America/Lima'));
    $this->status->markNoShow($noShow, $this->owner);

    $cancelled = reportBooking('2026-10-07', '16:00', SalonClient::factory()->for($this->salon)->create(), [$this->cut]);
    $this->status->confirm($cancelled, $this->owner, PaymentMethod::Yape);
    $this->status->cancel($cancelled, $this->owner, byClient: false);
    $this->actingAs($this->owner)->post(route('agenda.bookings.refund-deposit', $cancelled), ['method' => 'yape']);

    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '100', 'method' => 'transfer', 'date' => '2026-10-07']);
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '12.30', 'method' => 'cash', 'date' => '2026-10-07']);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 20:00', 'America/Lima'));
});

/**
 * @param  list<Service>  $services
 */
function reportBooking(string $date, string $time, SalonClient $client, array $services): Booking
{
    $at = CarbonImmutable::parse("{$date} {$time}", 'America/Lima');

    if (CarbonImmutable::now()->greaterThan($at->subHours(2))) {
        test()->travelTo($at->subHours(2));
    }

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client,
        selections: array_map(fn (Service $service): array => ['service_id' => $service->id, 'variant_id' => null], $services),
        staff: test()->pedro,
        startsAt: $at,
        source: BookingSource::Client,
    ));
}

/**
 * Deposit paid in cash, then the visit completed with the given checkout.
 *
 * @param  list<Service>  $services
 */
function reportVisit(string $date, string $time, SalonClient $client, array $services, Checkout $checkout): Booking
{
    $status = test()->status;
    $booking = $status->confirm(reportBooking($date, $time, $client, $services), test()->owner, PaymentMethod::Cash);
    test()->travelTo(CarbonImmutable::parse("{$date} {$time}", 'America/Lima'));
    $status->start($booking, test()->owner);

    return $status->complete($booking, test()->owner, $checkout);
}

function reportFor(string $from, string $to): array
{
    return app(SalonReport::class)->handle(test()->salon, Period::inSalon(test()->salon->refresh(), $from, $to));
}

it('adds up income, expenses and profit for a day', function () {
    $report = reportFor('2026-10-07', '2026-10-07');

    expect($report['totals'])->toBe([
        'incomeCents' => 3000 + 5500 + 1050,
        'servicesCents' => 8500,
        'keptDepositsCents' => 1050,
        'expensesCents' => 11230,
        'profitCents' => 9550 - 11230,
        'tipsCents' => 300,
        'discountsCents' => 500,
        'visits' => 2,
    ])->and($report['previous']['incomeCents'])->toBe(3500)
        ->and($report['previous']['profitCents'])->toBe(3500);
});

it('breaks the same totals down without losing a céntimo', function () {
    $report = reportFor('2026-10-06', '2026-10-07');
    $totals = $report['totals'];

    expect($report['days'])->toBe([
        ['date' => '2026-10-06', 'incomeCents' => 3500, 'expensesCents' => 0],
        ['date' => '2026-10-07', 'incomeCents' => 9550, 'expensesCents' => 11230],
    ])
        ->and(array_sum(array_column($report['days'], 'incomeCents')))->toBe($totals['incomeCents'])
        ->and(array_sum(array_column($report['days'], 'expensesCents')))->toBe($totals['expensesCents'])
        ->and(array_sum(array_column($report['byCategory'], 'cents')))->toBe($totals['expensesCents'])
        ->and(array_sum(array_column($report['byStaff'], 'salesCents')))->toBe($totals['servicesCents'])
        // 40 % of 35.00, of 30.00 and of 55.00
        ->and($report['byStaff'])->toBe([['name' => 'Pedro', 'visits' => 3, 'salesCents' => 12000, 'commissionCents' => 1400 + 1200 + 2200, 'tipsCents' => 300]])
        ->and($report['byService'])->toBe([['name' => 'Corte', 'count' => 3, 'cents' => 10500], ['name' => 'Barba', 'count' => 1, 'cents' => 2000]]);

    // Every sol received: what the visits cost + tips + the kept deposit; the returned one nets to zero.
    // By the day the money changed hands: the first deposit arrived on Monday 5.
    $report = reportFor('2026-10-05', '2026-10-07');

    expect(array_sum(array_column($report['byMethod'], 'cents')))
        ->toBe($totals['servicesCents'] + $totals['tipsCents'] + $totals['keptDepositsCents'])
        ->and(collect($report['byMethod'])->pluck('cents', 'value')->all())
        ->toBe(['cash' => 2450 + 3850 + 1050 + 1050 + 1650, 'yape' => 2250, 'plin' => 1050]);
});

it('counts what was lost and who is new', function () {
    $report = reportFor('2026-10-07', '2026-10-07');

    expect($report['lost'])->toBe(['cancelled' => 1, 'cancelledCents' => 3500, 'noShows' => 1, 'noShowCents' => 3500])
        // Ana had come the day before; Beto is new.
        ->and($report['clients'])->toBe(['new' => 1, 'returning' => 1])
        ->and(reportFor('2026-10-06', '2026-10-07')['clients'])->toBe(['new' => 2, 'returning' => 0]);
});

it('shows the report only with the reports permission, and only this salon\'s numbers', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $this->actingAs($member->user)->get(route('reports.index'))->assertForbidden();

    $member->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
    $this->actingAs($member->user->fresh())->get(route('reports.index'))->assertForbidden();

    $intruder = User::factory()->create();
    $other = app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);
    $theirs = app(SalonReport::class)->handle($other, Period::inSalon($other->refresh(), '2026-10-01', '2026-10-07'));

    expect($theirs['totals']['incomeCents'])->toBe(0)
        ->and($theirs['totals']['expensesCents'])->toBe(0)
        ->and($theirs['byStaff'])->toBe([])
        ->and($theirs['byMethod'])->toBe([]);
});
