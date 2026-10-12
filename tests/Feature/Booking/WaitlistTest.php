<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\ClientDataRights;
use App\Actions\Salon\CreateSalon;
use App\Actions\Waitlist\OfferFreedSlots;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.6: the waiting list. Ana works Thursdays from 9 to 10 at Estudio
| Luz: two cuts of 30 minutes and Thursday 2026-10-08 is full. "Now" is
| Wednesday 2026-10-07 08:00 in Lima.
*/

const FULL_DAY = '2026-10-08';

function waitlistClient(string $name, bool $isVip = false): User
{
    $user = User::factory()->create(['name' => $name]);
    SalonClient::factory()->for(test()->salon)->create(['user_id' => $user->id, 'name' => $name, 'is_vip' => $isVip]);

    return $user;
}

function fillSlot(string $time): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: SalonClient::factory()->for(test()->salon)->create(),
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::parse(FULL_DAY.' '.$time, 'America/Lima'),
        source: BookingSource::Staff,
    ));
}

function joinWaitlist(User $user, string $date = FULL_DAY): TestResponse
{
    return test()->actingAs($user)->post(route('salons.waitlist.store', test()->salon), [
        'services' => [['id' => test()->cut->id]],
        'date' => $date,
    ]);
}

function placeOf(User $user): WaitlistEntry
{
    return WaitlistEntry::withoutGlobalScope('salon')
        ->whereHas('client', fn ($client) => $client->where('user_id', $user->id))
        ->latest('id')->firstOrFail();
}

function sweep(?string $at = null): void
{
    if ($at !== null) {
        test()->travelTo(CarbonImmutable::parse($at, 'America/Lima'));
    }

    test()->artisan('waitlist:offer')->assertSuccessful();
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([4], '09:00', '10:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);

    $this->first = fillSlot('09:00');
    $this->second = fillSlot('09:30');
    $this->maria = waitlistClient('María Quispe');
    $this->vera = waitlistClient('Vera Vip', isVip: true);
});

it('takes someone on the list only for a day with no free time for what they want', function () {
    joinWaitlist($this->maria)->assertSessionHasNoErrors()->assertInertiaFlash('toast.type', 'success');
    expect(placeOf($this->maria)->status)->toBe(WaitlistStatus::Waiting)
        ->and(placeOf($this->maria)->date->toDateString())->toBe(FULL_DAY);

    // Twice the same day, a day with times, a day gone by, a day too far.
    joinWaitlist($this->maria)->assertInertiaFlash('toast.type', 'error');
    app(ChangeBookingStatus::class)->cancel($this->first, $this->owner, byClient: false);
    joinWaitlist($this->vera)->assertInertiaFlash('toast.type', 'error');
    joinWaitlist($this->vera, '2026-10-06')->assertInertiaFlash('toast.type', 'error');
    joinWaitlist($this->vera, '2027-10-07')->assertInertiaFlash('toast.type', 'error');

    expect(WaitlistEntry::withoutGlobalScope('salon')->count())->toBe(1);
});

it('asks for a verified account, and a real service', function () {
    $unverified = User::factory()->unverified()->create();
    joinWaitlist($unverified)->assertRedirect(route('verification.notice'));

    $this->actingAs($this->maria)->post(route('salons.waitlist.store', $this->salon), ['services' => [['id' => 999999]], 'date' => FULL_DAY])
        ->assertInertiaFlash('toast.type', 'error');
    auth()->logout();
    $this->post(route('salons.waitlist.store', $this->salon), ['services' => [['id' => $this->cut->id]], 'date' => FULL_DAY])
        ->assertRedirect(route('login'));

    expect(WaitlistEntry::withoutGlobalScope('salon')->count())->toBe(0);
});

it('tells one person at a time when a time opens up: VIPs first, then by arrival', function () {
    joinWaitlist($this->maria);
    joinWaitlist($this->vera);

    // Full day: nobody is told.
    sweep();
    Notification::assertNothingSent();

    app(ChangeBookingStatus::class)->cancel($this->first, $this->owner, byClient: false);
    sweep('2026-10-07 08:05');

    Notification::assertSentTo($this->vera, WaitlistNotification::class, fn (WaitlistNotification $notice): bool => $notice->salon === 'Estudio Luz'
        && str_starts_with($notice->until, '8:35')
        && str_contains($notice->url, 'fecha='.FULL_DAY));
    Notification::assertNotSentTo($this->maria, WaitlistNotification::class);
    expect(placeOf($this->vera)->status)->toBe(WaitlistStatus::Offered)
        ->and(placeOf($this->maria)->status)->toBe(WaitlistStatus::Waiting);

    // While Vera has the turn, running again tells nobody else.
    sweep('2026-10-07 08:20');
    Notification::assertNotSentTo($this->maria, WaitlistNotification::class);

    // Her 30 minutes ran out: María is next and Vera goes after the rest.
    sweep('2026-10-07 08:36');
    Notification::assertSentTo($this->maria, WaitlistNotification::class);
    expect(placeOf($this->maria)->status)->toBe(WaitlistStatus::Offered)
        ->and(placeOf($this->vera)->status)->toBe(WaitlistStatus::Waiting)
        ->and(placeOf($this->vera)->offers_count)->toBe(1);
});

it('closes the place of who booked, and tells nobody when the time is gone', function () {
    joinWaitlist($this->maria);
    joinWaitlist($this->vera);
    app(ChangeBookingStatus::class)->cancel($this->first, $this->owner, byClient: false);
    sweep('2026-10-07 08:05');

    // Vera takes the freed time by the usual way.
    $this->actingAs($this->vera)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $this->cut->id]],
        'start' => CarbonImmutable::parse(FULL_DAY.' 09:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();

    sweep('2026-10-07 08:10');

    expect(placeOf($this->vera)->status)->toBe(WaitlistStatus::Booked)
        ->and(placeOf($this->maria)->status)->toBe(WaitlistStatus::Waiting);
    Notification::assertNotSentTo($this->maria, WaitlistNotification::class);

    // The day goes by: what is left is closed.
    sweep('2026-10-09 08:00');
    expect(placeOf($this->maria)->status)->toBe(WaitlistStatus::Expired);
});

it('shows a person their places and lets only them leave', function () {
    joinWaitlist($this->maria);
    $place = placeOf($this->maria);

    $this->actingAs($this->maria)->get(route('bookings.index'))->assertInertia(fn (Assert $page) => $page
        ->has('waitlist', 1)
        ->where('waitlist.0.salonName', 'Estudio Luz')
        ->where('waitlist.0.date', FULL_DAY)
        ->where('waitlist.0.services', ['Corte'])
        ->where('waitlist.0.hasTurn', false));
    $this->actingAs($this->vera)->get(route('bookings.index'))->assertInertia(fn (Assert $page) => $page->has('waitlist', 0));

    $this->actingAs($this->vera)->delete(route('waitlist.destroy', $place->id))->assertNotFound();
    $this->actingAs($this->maria)->delete(route('waitlist.destroy', $place->id))->assertSessionHasNoErrors();

    expect($place->refresh()->status)->toBe(WaitlistStatus::Cancelled);
    // Out of the line: a freed time is not for them.
    app(ChangeBookingStatus::class)->cancel($this->first, $this->owner, byClient: false);
    sweep('2026-10-07 08:05');
    Notification::assertNotSentTo($this->maria, WaitlistNotification::class);
});

it('holds at most three places per person in a salon', function () {
    // Thursdays ahead, each one full for a client who wants an hour-long service.
    $long = Service::factory()->for($this->salon)->create(['name' => 'Color', 'active_minutes' => 90]);
    $this->staff->services()->attach($long->id);
    $join = fn (string $date) => $this->actingAs($this->maria)->post(route('salons.waitlist.store', $this->salon), ['services' => [['id' => $long->id]], 'date' => $date]);

    foreach (['2026-10-08', '2026-10-15', '2026-10-22'] as $date) {
        $join($date)->assertInertiaFlash('toast.type', 'success');
    }
    $join('2026-10-29')->assertInertiaFlash('toast.type', 'error');

    expect(WaitlistEntry::withoutGlobalScope('salon')->count())->toBe(WaitlistEntry::MAX_PER_CLIENT);
});

it('takes on the list someone who never booked at the salon', function () {
    $nueva = User::factory()->create(['name' => 'Nora Nueva']);

    joinWaitlist($nueva)->assertSessionHasNoErrors()->assertInertiaFlash('toast.type', 'success');

    $record = SalonClient::withoutGlobalScope('salon')->where('user_id', $nueva->id)->sole();
    expect(placeOf($nueva)->salon_client_id)->toBe($record->id)->and($record->is_vip)->toBeFalse();
});

it('closes the place of who let three turns go by, and of an account that is gone', function () {
    joinWaitlist($this->maria);
    joinWaitlist($this->vera);
    app(ChangeBookingStatus::class)->cancel($this->first, $this->owner, byClient: false);

    // Vera deletes her account: María is told, not a ghost.
    app(ClientDataRights::class)->forgetEverywhere($this->vera);
    sweep('2026-10-07 08:05');
    Notification::assertSentTo($this->maria, WaitlistNotification::class);

    // María lets it go by three times: her place closes.
    sweep('2026-10-07 08:36');
    sweep('2026-10-07 09:07');
    sweep('2026-10-07 09:38');
    sweep('2026-10-07 10:09');

    expect(placeOf($this->maria)->status)->toBe(WaitlistStatus::Expired)
        ->and(placeOf($this->maria)->offers_count)->toBe(WaitlistEntry::MAX_OFFERS);
    Notification::assertSentToTimes($this->maria, WaitlistNotification::class, WaitlistEntry::MAX_OFFERS);
});

it('does not bring back a place the person left while the lists were being read', function () {
    joinWaitlist($this->maria);
    $place = placeOf($this->maria);
    app(ChangeBookingStatus::class)->cancel($this->first, $this->owner, byClient: false);

    // The run read her as waiting; she leaves before it writes.
    $stale = WaitlistEntry::withoutGlobalScope('salon')->with('client.user')->findOrFail($place->id);
    $this->actingAs($this->maria)->delete(route('waitlist.destroy', $place->id));
    $move = new ReflectionMethod(OfferFreedSlots::class, 'move');

    expect($move->invoke(app(OfferFreedSlots::class), $stale, WaitlistStatus::Offered))->toBeFalse()
        ->and($place->refresh()->status)->toBe(WaitlistStatus::Cancelled);
});
