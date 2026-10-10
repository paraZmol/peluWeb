<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 5 QA: who can reach the money screens (every role) and the edge
| cases around amounts and periods. "Now" is Wednesday 2026-10-07 08:00 in
| Lima; the cut costs S/ 35.00.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->barber = SalonMember::factory()->for($this->salon)->create();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['user_id' => $this->barber->user_id]);
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);

    $this->intruder = User::factory()->create();
    $this->otherSalon = app(CreateSalon::class)->handle($this->intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);
});

function qa5InService(?Service $service = null): Booking
{
    $booking = app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: SalonClient::factory()->for(test()->salon)->create(),
        selections: [['service_id' => ($service ?? test()->cut)->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'),
        source: BookingSource::Staff,
    ));
    test()->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));

    return app(ChangeBookingStatus::class)->start($booking, test()->owner);
}

/**
 * @return list<array{string, string}>
 */
function qa5MoneyRoutes(): array
{
    return [
        ['get', 'expenses.index'], ['post', 'expenses.store'], ['get', 'cash.index'], ['post', 'cash.open'],
        ['get', 'commissions.index'], ['get', 'reports.index'], ['get', 'reports.export'],
        ['post', 'expenses.categories.store'],
    ];
}

it('keeps visitors out of every money route', function () {
    foreach (qa5MoneyRoutes() as [$method, $name]) {
        $this->{$method}(route($name))->assertRedirect(route('login'));
    }

    $this->post(route('cash.close', 1), ['amount' => '1'])->assertRedirect(route('login'));
    $this->post(route('commissions.settle', 1), ['method' => 'cash'])->assertRedirect(route('login'));
    $this->put(route('commissions.rules.update', 1), ['percent' => 10])->assertRedirect(route('login'));
    $this->post(route('expenses.update', 1), [])->assertRedirect(route('login'));
    $this->delete(route('expenses.destroy', 1))->assertRedirect(route('login'));
    $this->get(route('expenses.receipt', 1))->assertRedirect(route('login'));
});

it('keeps a client (no salon) out of every money route', function () {
    $client = User::factory()->create();

    foreach (qa5MoneyRoutes() as [$method, $name]) {
        $response = $this->actingAs($client)->{$method}(route($name));
        expect($response->status())->toBeIn([302, 403, 404]);
        // never a page with data
        expect($response->status() === 200)->toBeFalse();
    }

    // Without a salon the record never resolves: 403 or 404, never a change.
    expect($this->actingAs($client)->post(route('cash.close', 1), ['amount' => '1'])->status())->toBeIn([403, 404])
        ->and($this->actingAs($client)->post(route('commissions.settle', $this->staff), ['method' => 'cash'])->status())->toBeIn([403, 404])
        ->and($this->actingAs($client)->put(route('commissions.rules.update', $this->staff), ['percent' => 99])->status())->toBeIn([403, 404])
        ->and($this->actingAs($client)->delete(route('expenses.destroy', 1))->status())->toBeIn([403, 404]);
    expect($this->staff->refresh()->commission_percent)->not->toBe(99);
});

it('gives a VIP client no more reach than any other client', function () {
    $vip = User::factory()->create();
    SalonClient::factory()->for($this->salon)->create(['user_id' => $vip->id, 'is_vip' => true]);

    foreach (qa5MoneyRoutes() as [$method, $name]) {
        expect($this->actingAs($vip)->{$method}(route($name))->status())->toBeIn([302, 403, 404]);
    }
});

it('shows staff without cash or report permissions none of the money screens, and with them only theirs', function () {
    foreach (['expenses.index', 'cash.index', 'commissions.index', 'reports.index', 'reports.export'] as $name) {
        $this->actingAs($this->barber->user)->get(route($name))->assertForbidden();
    }

    $this->barber->forceFill(['permissions' => [SalonPermission::ViewReports]])->save();
    $this->actingAs($this->barber->user->fresh())->get(route('reports.index'))->assertOk();
    $this->actingAs($this->barber->user->fresh())->get(route('reports.export'))->assertOk();
    foreach (['expenses.index', 'cash.index', 'commissions.index'] as $name) {
        $this->actingAs($this->barber->user->fresh())->get(route($name))->assertForbidden();
    }

    $this->barber->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
    foreach (['expenses.index', 'cash.index', 'commissions.index'] as $name) {
        $this->actingAs($this->barber->user->fresh())->get(route($name))->assertOk();
    }
    $this->actingAs($this->barber->user->fresh())->get(route('reports.index'))->assertForbidden();
    // Cash handlers may pay commissions but never rewrite the rules (owner's call).
    $this->actingAs($this->barber->user->fresh())->put(route('commissions.rules.update', $this->staff), ['percent' => 90])->assertForbidden();
});

it('answers 404 to another salon\'s ids on every record route', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '10', 'method' => 'cash', 'date' => '2026-10-07']);
    $expense = Expense::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->sole();

    $this->actingAs($this->intruder)->get(route('expenses.receipt', $expense->id))->assertNotFound();
    $this->actingAs($this->intruder)->post(route('commissions.settle', $this->staff->id), ['method' => 'cash'])->assertNotFound();
    $this->actingAs($this->intruder)->put(route('commissions.rules.update', $this->staff->id), ['percent' => 90])->assertNotFound();
    $this->actingAs($this->intruder)->post(route('expenses.update', $expense->id), ['amount' => '1', 'method' => 'cash', 'date' => '2026-10-07'])->assertNotFound();

    expect($this->staff->refresh()->commission_percent)->not->toBe(90)
        ->and($expense->refresh()->amount_cents)->toBe(1000);
});

it('refuses a discount of "0" from nobody, and lets a no-cash professional charge in full', function () {
    $booking = qa5InService();

    $this->actingAs($this->barber->user)->post(route('agenda.bookings.complete', $booking), [
        'discount' => '0.00', 'payments' => [['method' => 'cash', 'amount' => '35.00']],
    ])->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and($booking->charged_cents)->toBe(3500);
});

it('lets the whole price be waived with the cash permission, charging zero and recording no payment', function () {
    $booking = qa5InService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['discount' => '35.00', 'payments' => []])->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and([$booking->charged_cents, $booking->discount_cents])->toBe([0, 3500])
        ->and(Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->count())->toBe(0);
});

it('rejects a zero-valued payment row that does not close the balance, and accepts zeros next to a real one', function () {
    $booking = qa5InService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['payments' => [['method' => 'cash', 'amount' => '0']]]);
    expect($booking->refresh()->status)->toBe(BookingStatus::InService);

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), [
        'payments' => [['method' => 'cash', 'amount' => '35.00'], ['method' => 'yape', 'amount' => '0.00']],
    ])->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and(Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->count())->toBe(1);
});

it('splits in three methods exactly, and refuses three that do not add up', function () {
    $booking = qa5InService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['payments' => [
        ['method' => 'cash', 'amount' => '10.00'], ['method' => 'yape', 'amount' => '10.00'], ['method' => 'plin', 'amount' => '14.99'],
    ]]);
    expect($booking->refresh()->status)->toBe(BookingStatus::InService)
        ->and(Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->count())->toBe(0);

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['payments' => [
        ['method' => 'cash', 'amount' => '10.00'], ['method' => 'yape', 'amount' => '10.00'], ['method' => 'plin', 'amount' => '15.00'],
    ]])->assertRedirect();
    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and(Payment::withoutGlobalScope('salon')->where('booking_id', $booking->id)->sum('amount_cents'))->toEqual(3500);
});

it('refuses four methods with money, even when they add up', function () {
    $booking = qa5InService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['payments' => [
        ['method' => 'cash', 'amount' => '10.00'], ['method' => 'yape', 'amount' => '10.00'],
        ['method' => 'plin', 'amount' => '10.00'], ['method' => 'card', 'amount' => '5.00'],
    ]]);

    expect($booking->refresh()->status)->toBe(BookingStatus::InService);
});

it('refuses amounts that are not money in a checkout', function (string $amount) {
    $booking = qa5InService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['payments' => [['method' => 'cash', 'amount' => $amount]]]);

    expect($booking->refresh()->status)->toBe(BookingStatus::InService);
})->with(['1e3', '35,00,0', ' ', '٣٥', '99999999', '０']);

it('accepts an array or number where a string is expected without a 500', function () {
    $booking = qa5InService();

    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['discount' => ['5'], 'payments' => 'x'])->assertSessionHasErrors();
    $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), ['payments' => [['method' => ['cash'], 'amount' => '35']]])->assertSessionHasErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::InService);
});

it('rejects expenses of zero in every spelling', function (string $amount) {
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => $amount, 'method' => 'cash', 'date' => '2026-10-07'])->assertSessionHasErrors('amount');
})->with(['0', '0.00', '00', '0,0', '000000']);

describe('periods', function () {
    it('treats a one-day period as that day', function () {
        $this->actingAs($this->owner)->get(route('reports.index', ['desde' => '2026-10-03', 'hasta' => '2026-10-03']))
            ->assertInertia(fn (Assert $page) => $page->where('from', '2026-10-03')->where('to', '2026-10-03')
                ->where('previousFrom', '2026-10-02')->where('previousTo', '2026-10-02')->has('report.days', 1));
    });

    it('falls back to this month when the period is backwards, malformed or impossible', function (array $query) {
        $this->actingAs($this->owner)->get(route('reports.index', $query))
            ->assertInertia(fn (Assert $page) => $page->where('from', '2026-10-01')->where('to', '2026-10-07'));
    })->with([
        'backwards' => [['desde' => '2026-10-06', 'hasta' => '2026-10-02']],
        'text' => [['desde' => 'ayer', 'hasta' => 'hoy']],
        'february 30th' => [['desde' => '2026-02-30', 'hasta' => '2026-10-07']],
        'month 13' => [['desde' => '2026-13-01', 'hasta' => '2026-10-07']],
        'sql' => [['desde' => "2026-10-01' OR 1=1 --", 'hasta' => '2026-10-07']],
        'arrays' => [['desde' => ['2026-10-01'], 'hasta' => ['x']]],
    ]);

    it('never goes past today nor beyond a year', function () {
        $this->actingAs($this->owner)->get(route('reports.index', ['desde' => '2026-10-01', 'hasta' => '2030-01-01']))
            ->assertInertia(fn (Assert $page) => $page->where('to', '2026-10-07'));

        $this->actingAs($this->owner)->get(route('reports.index', ['desde' => '2000-01-01', 'hasta' => '2026-10-07']))
            ->assertInertia(fn (Assert $page) => $page->where('from', '2026-10-01'));
    });

    it('serves the CSV for a backwards period as this month instead of failing', function () {
        $this->actingAs($this->owner)->get(route('reports.export', ['desde' => '2026-10-06', 'hasta' => '2026-10-02']))
            ->assertOk()->assertDownload('movimientos-estudio-luz-2026-10-01-a-2026-10-07.csv');
    });
});

it('defuses every formula starter in the CSV, in tildes and ñ too', function (string $text) {
    $this->actingAs($this->owner)->post(route('expenses.store'), ['amount' => '7', 'method' => 'cash', 'date' => '2026-10-07', 'description' => $text]);
    $csv = $this->actingAs($this->owner)->get(route('reports.export'))->streamedContent();

    foreach (explode("\n", $csv) as $line) {
        foreach (explode(';', $line) as $cell) {
            $cell = trim($cell, '"');
            expect($cell === '' || ! preg_match('/^[=+\-@\t\r]/', $cell))->toBeTrue("cell [{$cell}] starts like a formula");
        }
    }
})->with(['=1+1', '+51 999', '-2', '@SUM(A1)', 'Ñandú ñoño ✂️💈 tildes áéíóú']);
