<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
| Regression tests for the findings of the Phase 4 security audit.
| "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-lucho', 'timezone' => 'America/Lima']);
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create();
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);
    $this->customer = User::factory()->create();
});

function hardStart(string $time, string $date = '2026-10-07'): int
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima')->getTimestamp();
}

function hardPayload(string $time, string $date = '2026-10-07'): array
{
    return ['services' => [['id' => test()->cut->id]], 'staff' => test()->staff->id, 'start' => hardStart($time, $date)];
}

function hardBooking(string $time, SalonClient $client, BookingSource $source = BookingSource::Client): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client,
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::createFromTimestampUTC(hardStart($time)),
        source: $source,
    ));
}

it('still lets someone book after browsing many days (limits do not add up across routes)', function () {
    $url = fn (string $date) => route('salons.booking.availability', ['salon' => 'barberia-lucho', 'date' => $date, 'services' => [['id' => $this->cut->id]]]);

    foreach (range(1, 15) as $day) {
        $this->actingAs($this->customer)->getJson($url('2026-10-'.str_pad((string) (7 + $day), 2, '0', STR_PAD_LEFT)))->assertOk();
    }

    $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), hardPayload('10:00'))->assertSessionHasNoErrors();

    expect(Booking::withoutGlobalScopes()->count())->toBe(1);
});

it('answers dates outside the booking horizon with nothing, and absurd starts with a validation error', function () {
    $far = route('salons.booking.availability', ['salon' => 'barberia-lucho', 'date' => '2099-01-07', 'services' => [['id' => $this->cut->id]]]);
    $past = route('salons.booking.availability', ['salon' => 'barberia-lucho', 'date' => '2020-01-01', 'services' => [['id' => $this->cut->id]]]);

    $this->getJson($far)->assertOk()->assertJsonCount(0, 'slots');
    $this->getJson($past)->assertOk()->assertJsonCount(0, 'slots');

    $this->actingAs($this->customer)
        ->post(route('salons.booking.store', 'barberia-lucho'), [...hardPayload('10:00'), 'start' => 9000000000000000])
        ->assertSessionHasErrors('start');
});

it('leaves no client record behind when the booking does not go through', function () {
    $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), hardPayload('03:00'));

    $this->salon->forceFill(['accepts_online_bookings' => false])->save();
    $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), hardPayload('10:00'));

    expect(SalonClient::withoutGlobalScopes()->count())->toBe(0)
        ->and(Booking::withoutGlobalScopes()->count())->toBe(0);
});

it('caps how many online bookings a client can create per day, cancelled ones included', function () {
    $client = SalonClient::factory()->for($this->salon)->forUser($this->customer)->create();
    $status = app(ChangeBookingStatus::class);

    foreach (range(1, CreateBooking::MAX_PER_CLIENT_PER_DAY) as $ignored) {
        $status->cancel(hardBooking('10:00', $client), null, byClient: true);
    }

    $this->actingAs($this->customer)
        ->post(route('salons.booking.store', 'barberia-lucho'), hardPayload('10:00'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Booking::withoutGlobalScopes()->where('status', '!=', BookingStatus::Cancelled)->count())->toBe(0)
        // The team can still book that person by hand, and tomorrow the cap is back.
        ->and(hardBooking('10:00', $client, BookingSource::Staff)->status)->toBe(BookingStatus::Confirmed);

    $this->travel(25)->hours();
    $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), hardPayload('11:00', '2026-10-08'));

    expect(Booking::withoutGlobalScopes()->where('source', 'client')->where('status', 'confirmed')->count())->toBe(1);
});

it('keeps deposit and payment details for owners, not for whoever edits the profile', function () {
    $member = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageSalonProfile, SalonPermission::ManageCash)->create();
    $rules = [
        'accepts_online_bookings' => true, 'min_lead_minutes' => 60, 'max_days_ahead' => 60, 'slot_step_minutes' => 15,
        'cancel_window_hours' => 24, 'deposit_percent' => 0, 'vip_deposit_percent' => null, 'hold_minutes' => 30,
        'payment_instructions' => 'Yape al 900000000 (el mío)',
    ];

    $this->actingAs($member->user)->put(route('salon.booking.update'), $rules)->assertForbidden();
    $this->actingAs($member->user)->get(route('salon.edit'))
        ->assertInertia(fn ($page) => $page->where('canManageBookingRules', false));

    expect($this->salon->refresh()->payment_instructions)->toBeNull();

    $this->actingAs($this->owner)->put(route('salon.booking.update'), $rules)->assertSessionHasNoErrors();
    expect($this->salon->refresh()->payment_instructions)->toBe('Yape al 900000000 (el mío)');
});

it('does not let staff make themselves VIP, even with the VIP permission', function () {
    $member = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageVipClients)->create();
    $self = SalonClient::factory()->for($this->salon)->forUser($member->user)->create();
    $someone = SalonClient::factory()->for($this->salon)->create();
    $ownerAsClient = SalonClient::factory()->for($this->salon)->forUser($this->owner)->create();

    $this->actingAs($member->user)->patch(route('clients.vip.update', $self), ['is_vip' => true])->assertForbidden();
    $this->actingAs($member->user)->patch(route('clients.vip.update', $someone), ['is_vip' => true]);
    $this->actingAs($this->owner)->patch(route('clients.vip.update', $ownerAsClient), ['is_vip' => true]);

    expect($self->refresh()->is_vip)->toBeFalse()
        ->and($someone->refresh()->is_vip)->toBeTrue()
        ->and($ownerAsClient->refresh()->is_vip)->toBeTrue();
});

it('needs the cash permission to cancel a booking whose deposit was paid', function () {
    $this->salon->forceFill(['deposit_percent' => 30])->save();
    $member = SalonMember::factory()->for($this->salon)->create();
    $this->staff->forceFill(['user_id' => $member->user_id])->save();
    $client = SalonClient::factory()->for($this->salon)->create();
    $paid = app(ChangeBookingStatus::class)->confirm(hardBooking('10:00', $client), $this->owner);
    $unpaid = hardBooking('11:00', $client);

    $this->actingAs($member->user)->post(route('agenda.bookings.cancel', $paid))->assertForbidden();
    $this->actingAs($member->user)->post(route('agenda.bookings.cancel', $unpaid));

    expect($paid->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($unpaid->refresh()->status)->toBe(BookingStatus::Cancelled);

    $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $paid));
    expect($paid->refresh()->status)->toBe(BookingStatus::Cancelled)->and($paid->deposit_refundable)->toBeTrue();
});

it('skips a hold that was confirmed in the meantime instead of aborting the expiry run', function () {
    $this->salon->forceFill(['deposit_percent' => 30, 'hold_minutes' => 30])->save();
    $first = hardBooking('10:00', SalonClient::factory()->for($this->salon)->create());
    $second = hardBooking('11:00', SalonClient::factory()->for($this->salon)->create());
    $this->travel(31)->minutes();

    // A stale copy, as the command holds while someone confirms the booking.
    $stale = Booking::withoutGlobalScopes()->findOrFail($first->id);
    app(ChangeBookingStatus::class)->confirm($first, $this->owner);

    expect(app(ChangeBookingStatus::class)->expire($stale))->toBeNull();

    $this->artisan('bookings:expire-holds')->assertSuccessful();

    expect($first->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($second->refresh()->status)->toBe(BookingStatus::Expired);
});
