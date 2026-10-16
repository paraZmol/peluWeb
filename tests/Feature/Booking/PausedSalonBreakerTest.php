<?php

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingNotice;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\SalonStatus;
use App\Enums\WaitlistStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WalkIn;
use App\Notifications\BookingNotification;
use App\Notifications\WaitlistNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| booking-breaker on the paused salon (PLAN 7.3): read only or suspended,
| only confirm, refund-deposit, no-show and cancel go through; nothing
| new is booked. "Now" is Wednesday 2026-10-07 08:00 in Lima. Beto works
| every day 09:00–18:00; a cut is 30 minutes and S/ 50.
*/

const PB_PAUSED = 'Tu salón está en pausa. Renueva tu plan para seguir haciendo cambios.';

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create([
        'timezone' => 'America/Lima',
        'min_lead_minutes' => 0,
        'deposit_percent' => 20,
        'accepts_online_bookings' => true,
        'walk_ins_enabled' => true,
    ]);
    $this->beto = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '09:00', '18:00')->create(['name' => 'Beto']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 5000, 'active_minutes' => 30]);
    $this->beto->services()->attach($this->cut->id);
    $this->client = User::factory()->create(['name' => 'Ana Cliente']);
});

function pbAt(string $time, string $date = '2026-10-09'): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima');
}

function pbClientOf(User $user): SalonClient
{
    return SalonClient::withoutGlobalScopes()->where('salon_id', test()->salon->id)->where('user_id', $user->id)->first()
        ?? SalonClient::factory()->for(test()->salon)->forUser($user)->create();
}

function pbBook(string $time, string $date = '2026-10-09', ?User $user = null, BookingSource $source = BookingSource::Client): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $user === null ? SalonClient::factory()->for(test()->salon)->create() : pbClientOf($user),
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->beto,
        startsAt: pbAt($time, $date),
        source: $source,
    ));
}

/**
 * Read only (trial over long before, nothing paid) or suspended by hand.
 */
function pbPause(string $how = 'read_only'): void
{
    $salon = test()->salon;

    if ($how === 'suspended') {
        $salon->forceFill(['status' => SalonStatus::Suspended])->save();
    } else {
        $salon->forceFill(['trial_ends_at' => now()->subDays(40), 'paid_until' => null])->save();
    }

    expect($salon->refresh()->isWritable())->toBeFalse();
}

function pbBookingsCount(): int
{
    return DB::table('bookings')->where('salon_id', test()->salon->id)->count();
}

function pbStoreBody(string $time = '11:00', string $date = '2026-10-09'): array
{
    return ['services' => [['id' => test()->cut->id]], 'staff' => test()->beto->id, 'start' => pbAt($time, $date)->getTimestamp()];
}

function pbWait(string $date = '2026-10-09'): WaitlistEntry
{
    $entry = new WaitlistEntry;
    $entry->forceFill([
        'salon_id' => test()->salon->id,
        'salon_client_id' => pbClientOf(test()->client)->id,
        'staff_id' => test()->beto->id,
        'date' => $date,
        'selections' => [['service_id' => test()->cut->id, 'variant_id' => null]],
        'status' => WaitlistStatus::Waiting,
        'offers_count' => 0,
    ])->save();

    return $entry;
}

// ------------------------------------------------- 1. confirming an expired hold

it('confirms while read only an expired hold whose slot nobody took, once, without overlapping anything', function () {
    $held = pbBook('10:00', user: $this->client);
    expect($held->status)->toBe(BookingStatus::Pending);

    $this->travel(31)->minutes();
    expect($held->refresh()->isHoldExpired())->toBeTrue();
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $held), ['method' => 'yape'])->assertSessionHasNoErrors();
    // A second press does not record the deposit twice.
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $held), ['method' => 'yape']);

    expect($held->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($held->hold_expires_at)->toBeNull()
        ->and(Payment::withoutGlobalScopes()->where('booking_id', $held->id)->where('kind', PaymentKind::Deposit)->count())->toBe(1)
        ->and(Payment::withoutGlobalScopes()->where('booking_id', $held->id)->sum('amount_cents'))->toEqual(1000);
});

it('stops every change of the team when the platform suspends the salon, even confirm, no-show and cancel', function () {
    $paid = pbBook('10:00', user: $this->client);
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $paid), ['method' => 'yape'])->assertSessionHasNoErrors();
    $held = pbBook('11:00');
    $missed = pbBook('09:00');
    $this->travelTo(pbAt('09:20'));
    pbPause('suspended');

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $held), ['method' => 'yape'])->assertInertiaFlash('toast.message', PB_PAUSED);
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.no-show', $missed))->assertInertiaFlash('toast.message', PB_PAUSED);
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.cancel', $paid))->assertInertiaFlash('toast.message', PB_PAUSED);

    expect($held->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($missed->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($paid->refresh()->status)->toBe(BookingStatus::Confirmed);

    // The client still calls off their own (Mis reservas does not go through the salon middleware).
    $this->actingAs($this->client)->post(route('bookings.cancel', $paid->code))->assertSessionHasNoErrors();
    expect($paid->refresh()->status)->toBe(BookingStatus::Cancelled);
});

it('leaves no way back for a deposit already paid when the platform suspends the salon', function () {
    $paid = pbBook('10:00', '2026-10-07', user: $this->client);
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $paid), ['method' => 'yape'])->assertSessionHasNoErrors();
    pbPause('suspended');

    // The visit cannot happen (the team cannot even start it), so the client calls it off...
    $this->actingAs($this->client)->post(route('bookings.cancel', $paid->code))->assertSessionHasNoErrors();
    // ...and the salon tries to give the deposit back.
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.refund-deposit', $paid), ['method' => 'yape']);

    // Either the deposit stays refundable and is given back, or the cancellation is not the client's fault.
    expect($paid->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($paid->deposit_refundable)->toBeTrue()
        ->and(Payment::withoutGlobalScopes()->where('booking_id', $paid->id)->where('kind', PaymentKind::Refund)->count())->toBe(1);
});

it('refuses in pause to revive an expired hold whose time another client already took', function () {
    $held = pbBook('10:00', user: $this->client);
    $this->travel(31)->minutes();

    // The hold ran out while the salon still worked: someone else took 10:00.
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $taker = pbBook('10:00');
    expect($taker->status)->toBe(BookingStatus::Confirmed);
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $held), ['method' => 'yape']);

    expect($held->refresh()->status)->not->toBe(BookingStatus::Confirmed)
        ->and($held->deposit_paid_at)->toBeNull()
        ->and(Payment::withoutGlobalScopes()->where('booking_id', $held->id)->exists())->toBeFalse()
        ->and($taker->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('lets only the first of two expired holds on the same time be revived in pause', function () {
    $first = pbBook('10:00', user: $this->client);
    $this->travel(31)->minutes();
    // The first hold is gone, so a second person holds the same time, and lets it go too.
    $second = pbBook('10:00');
    $this->travel(31)->minutes();
    expect($first->refresh()->isHoldExpired())->toBeTrue()->and($second->refresh()->isHoldExpired())->toBeTrue();
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $first), ['method' => 'yape'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $second), ['method' => 'yape']);

    expect($first->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($second->refresh()->status)->not->toBe(BookingStatus::Confirmed)
        ->and(Payment::withoutGlobalScopes()->where('booking_id', $second->id)->exists())->toBeFalse()
        ->and(Booking::withoutGlobalScopes()->where('staff_id', $this->beto->id)->where('status', BookingStatus::Confirmed)->count())->toBe(1);
});

// ------------------------------------------------- 2. no new booking by any way

it('creates no booking in pause through the public wizard, the API v1 or the action itself', function (string $how) {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    pbPause($how);

    $this->actingAs($this->client)->post(route('salons.booking.store', $this->salon), pbStoreBody());

    Sanctum::actingAs($this->client, ['*']);
    $api = $this->postJson(route('api.v1.bookings.store', $this->salon->slug), pbStoreBody('12:00'));
    expect($api->status())->not->toBe(201);

    expect(fn () => pbBook('13:00', source: BookingSource::Staff))->toThrow(BookingException::class)
        ->and(pbBookingsCount())->toBe(0);
})->with(['read only' => 'read_only', 'suspended' => 'suspended']);

it('refuses in pause a booking made by the team from the agenda', function () {
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.store'), [...pbStoreBody(), 'client_name' => 'Luis Paso'])
        ->assertRedirect()->assertInertiaFlash('toast.message', PB_PAUSED);

    expect(pbBookingsCount())->toBe(0)
        ->and(SalonClient::withoutGlobalScopes()->where('name', 'Luis Paso')->exists())->toBeFalse();
});

it('does not move a booking in pause, from the agenda, from Mis reservas or from the API', function () {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $booking = pbBook('10:00', user: $this->client);
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.move', $booking), ['start' => pbAt('15:00')->getTimestamp()])
        ->assertInertiaFlash('toast.message', PB_PAUSED);
    $this->actingAs($this->client)->post(route('bookings.move', $booking->code), ['start' => pbAt('16:00')->getTimestamp()]);
    Sanctum::actingAs($this->client, ['*']);
    $this->postJson(route('api.v1.bookings.move', $booking->code), ['start' => pbAt('17:00')->getTimestamp()]);

    expect($booking->refresh()->starts_at->equalTo(pbAt('10:00')))->toBeTrue()
        ->and(DB::table('booking_segments')->where('booking_id', $booking->id)->min('starts_at'))->toBe(pbAt('10:00')->utc()->format('Y-m-d H:i:s'));
});

it('does not add to nor move the walk-in line in pause', function () {
    $this->actingAs($this->owner->fresh())->post(route('walk-ins.store'), ['name' => 'Primero'])->assertSessionHasNoErrors();
    $walkIn = WalkIn::withoutGlobalScopes()->where('name', 'Primero')->firstOrFail();
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('walk-ins.store'), ['name' => 'Segundo'])->assertInertiaFlash('toast.message', PB_PAUSED);
    $this->actingAs($this->owner->fresh())->patch(route('walk-ins.update', $walkIn), ['status' => 'called'])->assertInertiaFlash('toast.message', PB_PAUSED);

    expect(WalkIn::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1)
        ->and($walkIn->refresh()->status->value)->toBe('waiting');
});

it('takes nobody on the waiting list and offers no free time of a paused salon', function () {
    $entry = pbWait();
    pbPause();

    // Joining is closed.
    $this->actingAs($this->client)->post(route('salons.waitlist.store', $this->salon), ['services' => [['id' => $this->cut->id]], 'date' => '2026-10-09'])->assertNotFound();

    // The whole day is free, yet the one already waiting is not told.
    $this->artisan('waitlist:offer')->assertSuccessful();

    expect($entry->refresh()->status)->toBe(WaitlistStatus::Waiting)
        ->and($entry->offers_count)->toBe(0)
        ->and(WaitlistEntry::withoutGlobalScopes()->count())->toBe(1);
    Notification::assertNotSentTo($this->client, WaitlistNotification::class);
});

it('does not open a "repeat" booking in pause: the wizard says it is paused and the store refuses it', function () {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $done = pbBook('10:00', user: $this->client);
    $this->travelTo(pbAt('12:00'));
    Booking::withoutGlobalScopes()->whereKey($done->id)->update(['status' => BookingStatus::Completed]);
    pbPause();

    $this->actingAs($this->client)->get(route('salons.booking.create', ['salon' => $this->salon->slug, 'repetir' => $done->code]))
        ->assertInertia(fn ($page) => $page->where('isOpen', false));
    $this->actingAs($this->client)->post(route('salons.booking.store', $this->salon), pbStoreBody('10:00', '2026-10-16'));

    expect(pbBookingsCount())->toBe(1);
});

// ------------------------------------------------- 3. what no-show and cancel set off

it('marks "no vino" in pause without charging, paying a commission or offering the slot', function () {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $booking = pbBook('10:00', user: $this->client);
    $entry = pbWait();
    $this->travelTo(pbAt('10:20'));
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.no-show', $booking))->assertSessionHasNoErrors();
    $this->artisan('waitlist:offer')->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::NoShow)
        ->and($booking->commission_cents)->toBeIn([null, 0])
        ->and($booking->charged_cents)->toBeIn([null, 0])
        ->and(Payment::withoutGlobalScopes()->where('salon_id', $this->salon->id)->exists())->toBeFalse()
        ->and(pbClientOf($this->client)->no_show_count)->toBe(1)
        ->and($entry->refresh()->status)->toBe(WaitlistStatus::Waiting);
    Notification::assertNotSentTo($this->client, WaitlistNotification::class);
});

it('cancels in pause without offering the freed time to the waiting list nor moving money', function () {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $booking = pbBook('10:00');
    $entry = pbWait();
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.cancel', $booking))->assertSessionHasNoErrors();
    $this->artisan('waitlist:offer')->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($entry->refresh()->status)->toBe(WaitlistStatus::Waiting)
        ->and(Payment::withoutGlobalScopes()->where('salon_id', $this->salon->id)->exists())->toBeFalse();
    Notification::assertNotSentTo($this->client, WaitlistNotification::class);

    // And the freed time is still not bookable while paused.
    $this->actingAs(User::factory()->create())->post(route('salons.booking.store', $this->salon), pbStoreBody('10:00'));
    expect(pbBookingsCount())->toBe(1);
});

it('gives a paid deposit back once in pause after the salon cancels, and never twice', function () {
    $booking = pbBook('10:00', user: $this->client);
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.confirm', $booking), ['method' => 'yape'])->assertSessionHasNoErrors();
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.cancel', $booking))->assertSessionHasNoErrors();
    expect($booking->refresh()->deposit_refundable)->toBeTrue()
        ->and(Payment::withoutGlobalScopes()->where('booking_id', $booking->id)->count())->toBe(1);

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'yape'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.refund-deposit', $booking), ['method' => 'yape']);

    expect(Payment::withoutGlobalScopes()->where('booking_id', $booking->id)->where('kind', PaymentKind::Refund)->count())->toBe(1)
        ->and((int) Payment::withoutGlobalScopes()->where('booking_id', $booking->id)->get()->sum(fn (Payment $p) => $p->signedCents()))->toBe(0);
});

it('does not start nor charge a visit in pause (only confirm, refund, no-show and cancel pass)', function () {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $booking = pbBook('10:00', user: $this->client);
    $this->travelTo(pbAt('10:05'));
    pbPause();

    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.start', $booking))->assertInertiaFlash('toast.message', PB_PAUSED);
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.complete', $booking), ['method' => 'cash'])->assertInertiaFlash('toast.message', PB_PAUSED);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and(Payment::withoutGlobalScopes()->where('salon_id', $this->salon->id)->exists())->toBeFalse();
});

// ------------------------------------------------- 4. midnight in the salon's zone

it('turns read only at midnight of the salon\'s own zone, whatever the server zone', function (string $zone, string $limaMoment, bool $writable, ?string $serverZone) {
    $previous = date_default_timezone_get();

    if ($serverZone !== null) {
        date_default_timezone_set($serverZone);
    }

    try {
        // Paid through 2026-11-06: grace through the 13th, read only from the 14th.
        $this->salon->forceFill(['timezone' => $zone, 'trial_ends_at' => null, 'paid_until' => '2026-11-06'])->save();
        $this->travelTo(CarbonImmutable::parse($limaMoment, 'America/Lima'));

        expect($this->salon->refresh()->isWritable())->toBe($writable);
    } finally {
        date_default_timezone_set($previous);
    }
})->with([
    'Lima, 23:59 of the last day of grace' => ['America/Lima', '2026-11-13 23:59:59', true, null],
    'Lima, 00:00 of the first day read only' => ['America/Lima', '2026-11-14 00:00:00', false, null],
    'Lima, 23:59, server in Tokyo' => ['America/Lima', '2026-11-13 23:59:59', true, 'Asia/Tokyo'],
    'Lima, 00:00, server in Honolulu' => ['America/Lima', '2026-11-14 00:00:00', false, 'Pacific/Honolulu'],
    'Tokyo salon, already the 14th there' => ['Asia/Tokyo', '2026-11-13 10:00:00', false, null],
    'Tokyo salon, still the 13th there' => ['Asia/Tokyo', '2026-11-13 09:59:00', true, null],
    'Honolulu salon, still the 13th there' => ['Pacific/Honolulu', '2026-11-14 04:59:00', true, null],
    'Honolulu salon, the 14th there' => ['Pacific/Honolulu', '2026-11-14 05:00:00', false, null],
]);

it('takes a booking at 23:59 of the last day of grace in Lima and refuses it at 00:00', function () {
    $this->salon->forceFill(['trial_ends_at' => null, 'paid_until' => '2026-11-06', 'deposit_percent' => 0])->save();

    $this->travelTo(CarbonImmutable::parse('2026-11-13 23:59:00', 'America/Lima'));
    $this->actingAs($this->client)->post(route('salons.booking.store', $this->salon), pbStoreBody('10:00', '2026-11-20'))->assertSessionHasNoErrors();
    expect(pbBookingsCount())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-11-14 00:00:00', 'America/Lima'));
    $this->actingAs(User::factory()->create())->post(route('salons.booking.store', $this->salon), pbStoreBody('11:00', '2026-11-20'));
    $this->actingAs($this->owner->fresh())->post(route('agenda.bookings.store'), [...pbStoreBody('12:00', '2026-11-20'), 'client_name' => 'Luis Paso'])
        ->assertInertiaFlash('toast.message', PB_PAUSED);

    expect(pbBookingsCount())->toBe(1);
});

// ------------------------------------------------- 5. reminders keep going out

it('still sends the reminder of a booking already taken while the salon is paused', function (string $how) {
    $this->salon->forceFill(['deposit_percent' => 0])->save();
    $booking = pbBook('10:00', user: $this->client);
    expect($booking->status)->toBe(BookingStatus::Confirmed);
    pbPause($how);

    // 22 hours before the visit.
    $this->travelTo(pbAt('12:00', '2026-10-08'));
    $this->artisan('bookings:send-reminders')->assertSuccessful();

    expect($booking->refresh()->reminder_sent_at)->not->toBeNull();
    Notification::assertSentTo($this->client, BookingNotification::class, fn (BookingNotification $n) => $n->notice === BookingNotice::Reminder);
})->with(['read only' => 'read_only', 'suspended' => 'suspended']);
