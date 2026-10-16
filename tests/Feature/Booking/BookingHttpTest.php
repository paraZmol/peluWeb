<?php

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\SalonPermission;
use App\Enums\ScheduleAccess;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Booking over HTTP: the public wizard, "Mis reservas", the team's agenda,
| VIP clients and the salon's booking rules. "Now" is Wednesday 2026-10-07
| 08:00 in Lima; the professional works Wed and Thu 09:00–18:00.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-lucho', 'timezone' => 'America/Lima', 'phone' => '999888777']);
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);
    $this->customer = User::factory()->create(['name' => 'Ana Cliente', 'phone' => '+51911222333']);
});

function httpAt(string $time, string $date = '2026-10-07'): int
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima')->getTimestamp();
}

function bookingPayload(string $time = '10:00', array $overrides = []): array
{
    return ['services' => [['id' => test()->cut->id]], 'staff' => test()->staff->id, 'start' => httpAt($time), ...$overrides];
}

function availabilityUrl(array $query = []): string
{
    return route('salons.booking.availability', ['salon' => 'barberia-lucho', 'date' => '2026-10-07', 'services' => [['id' => test()->cut->id]], ...$query]);
}

/**
 * A booking made directly through the action, for the screens that list them.
 */
function existingBooking(string $time = '10:00', ?User $user = null, ?Staff $staff = null): Booking
{
    $test = test();
    $client = $user === null
        ? SalonClient::factory()->for($test->salon)->create()
        : (SalonClient::withoutGlobalScopes()->where('user_id', $user->id)->first() ?? SalonClient::factory()->for($test->salon)->forUser($user)->create());

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: $client,
        selections: [['service_id' => $test->cut->id, 'variant_id' => null]],
        staff: $staff ?? $test->staff,
        startsAt: CarbonImmutable::createFromTimestampUTC(httpAt($time)),
        source: BookingSource::Client,
    ));
}

describe('public wizard', function () {
    it('shows services, professionals and days to anyone', function () {
        $nobodyDoesIt = Service::factory()->for($this->salon)->create(['name' => 'Sin profesional']);

        $this->get('/s/barberia-lucho/reservar?servicio='.$this->cut->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/booking')
                ->where('salon.staff.0.name', 'Pedro')
                ->has('salon.serviceGroups', 1)
                ->has('salon.serviceGroups.0.services', 1)
                ->where('salon.serviceGroups.0.services.0.staffIds', [$this->staff->id])
                ->has('days', 61)
                ->where('days.0', ['date' => '2026-10-07', 'closed' => false])
                ->where('days.2', ['date' => '2026-10-09', 'closed' => true])
                ->where('isOpen', true)
                ->where('viewer.state', 'guest')
                ->where('preselectedServiceId', $this->cut->id)
                ->missing('salon.id')
                ->missing('salon.staff.0.userId'));
    });

    it('tells whether the viewer can confirm', function () {
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)->get('/s/barberia-lucho/reservar')
            ->assertInertia(fn (Assert $page) => $page->where('viewer.state', 'unverified'));
        $this->actingAs($this->customer)->get('/s/barberia-lucho/reservar')
            ->assertInertia(fn (Assert $page) => $page->where('viewer.state', 'ready'));
    });

    it('is closed for a paused salon and missing for a suspended one', function () {
        $this->salon->forceFill(['accepts_online_bookings' => false])->save();
        $this->get('/s/barberia-lucho/reservar')->assertInertia(fn (Assert $page) => $page->where('isOpen', false));

        $this->salon->forceFill(['status' => 'suspended'])->save();
        $this->get('/s/barberia-lucho/reservar')->assertNotFound();
        $this->getJson(availabilityUrl())->assertNotFound();
    });

    it('links the public page to the wizard once the salon has hours', function () {
        $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
            ->where('salon.bookingUrl', route('salons.booking.create', 'barberia-lucho')));

        $this->salon->forceFill(['accepts_online_bookings' => false])->save();
        $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page->where('salon.bookingUrl', null));
    });
});

describe('availability', function () {
    it('returns the free starts of the day on the salon clock, with the quote', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();
        existingBooking('10:00');

        $response = $this->getJson(availabilityUrl(['staff' => $this->staff->id]))->assertOk();

        $times = collect($response->json('slots'))->pluck('time');
        expect($times)->toContain('09:00')->toContain('10:30')->not->toContain('10:00')->not->toContain('09:45')
            ->and($response->json('slots.0'))->toBe(['start' => httpAt('09:00'), 'time' => '09:00', 'promotion' => null])
            ->and($response->json('quote'))->toMatchArray(['price' => '35.00', 'minutes' => 30, 'depositPercent' => 30, 'deposit' => '10.50', 'priceVaries' => false]);
    });

    it('never sends VIP or team-only starts to guests, regular clients or VIPs of another salon', function () {
        $vipStaff = Staff::factory()->for($this->salon)
            ->working([3], '12:00', '14:00', ScheduleAccess::Vip)
            ->working([3], '20:00', '21:00', ScheduleAccess::StaffOnly)
            ->create();
        $vipStaff->services()->attach($this->cut->id);
        SalonClient::factory()->for(Salon::factory()->create())->forUser($this->customer)->vip()->create();
        $url = availabilityUrl(['staff' => $vipStaff->id]);

        $this->getJson($url)->assertOk()->assertJsonCount(0, 'slots');
        $this->actingAs($this->customer)->getJson($url)->assertJsonCount(0, 'slots');
        $this->actingAs($this->owner)->getJson($url)->assertJsonCount(0, 'slots');
    });

    it('sends VIP starts to a client this salon marked VIP, but never team-only ones', function () {
        $vipStaff = Staff::factory()->for($this->salon)
            ->working([3], '12:00', '14:00', ScheduleAccess::Vip)
            ->working([3], '20:00', '21:00', ScheduleAccess::StaffOnly)
            ->create();
        $vipStaff->services()->attach($this->cut->id);
        SalonClient::factory()->for($this->salon)->forUser($this->customer)->vip()->create();

        $times = collect($this->actingAs($this->customer)->getJson(availabilityUrl(['staff' => $vipStaff->id]))->json('slots'))->pluck('time');

        expect($times)->toContain('12:00')->toContain('13:30')->not->toContain('20:00');
    });

    it('rejects bad input with validation errors, not a crash', function (array $query, int $status) {
        $this->getJson(availabilityUrl($query))->assertStatus($status);
    })->with([
        'no date' => [['date' => null], 422],
        'not a date' => [['date' => 'mañana'], 422],
        'no services' => [['services' => []], 422],
        'unknown service' => [['services' => [['id' => 999999]]], 422],
        'unknown professional' => [['staff' => 999999], 422],
        'too many services' => [['services' => [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5], ['id' => 6]]], 422],
    ]);

    it('does not accept a professional or a service of another salon', function () {
        $other = Salon::factory()->create();
        $foreignStaff = Staff::factory()->for($other)->working([3])->create();
        $foreignService = Service::factory()->for($other)->create();

        $this->getJson(availabilityUrl(['staff' => $foreignStaff->id]))->assertStatus(422);
        $this->getJson(availabilityUrl(['services' => [['id' => $foreignService->id]]]))->assertStatus(422);
    });
});

describe('confirming', function () {
    it('sends guests to sign in and the unverified to verify', function () {
        $this->get(route('bookings.index'))->assertRedirect(route('login'));
        $this->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload())->assertRedirect(route('login'));
        $this->get(route('salons.booking.resume', 'barberia-lucho'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload())
            ->assertRedirect(route('verification.notice'));

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    });

    it('brings a signed-in visitor back to the wizard', function () {
        $this->actingAs($this->customer)
            ->get(route('salons.booking.resume', 'barberia-lucho'))
            ->assertRedirect(route('salons.booking.create', ['salon' => 'barberia-lucho', 'continuar' => 1]));
    });

    it('books for the signed-in account and creates its client record in that salon', function () {
        $response = $this->actingAs($this->customer)
            ->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload('10:00', ['note' => 'Degradado bajo']));

        $booking = Booking::withoutGlobalScopes()->firstOrFail();
        $client = SalonClient::withoutGlobalScopes()->findOrFail($booking->salon_client_id);

        $response->assertRedirect(route('bookings.show', $booking->code));
        expect($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->salon_id)->toBe($this->salon->id)
            ->and($booking->created_by)->toBe($this->customer->id)
            ->and($booking->client_note)->toBe('Degradado bajo')
            ->and($client->user_id)->toBe($this->customer->id)
            ->and($client->name)->toBe('Ana Cliente')
            ->and($client->is_vip)->toBeFalse();
    });

    it('reuses the client record on a second booking', function () {
        $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload('10:00'));
        $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload('11:00'));

        expect(SalonClient::withoutGlobalScopes()->count())->toBe(1)
            ->and(Booking::withoutGlobalScopes()->count())->toBe(2);
    });

    it('explains and books nothing when the slot was just taken', function () {
        existingBooking('10:00');

        $this->actingAs($this->customer)
            ->from('/s/barberia-lucho/reservar')
            ->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload('10:00'))
            ->assertRedirect('/s/barberia-lucho/reservar')
            ->assertInertiaFlash('toast.type', 'error');

        expect(Booking::withoutGlobalScopes()->count())->toBe(1);
    });

    it('ignores any attempt to pass privileges in the request', function () {
        $this->salon->forceFill(['deposit_percent' => 50])->save();

        $this->actingAs($this->customer)->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload('10:00', [
            'is_vip' => true, 'source' => 'staff', 'level' => 'team', 'deposit_cents' => 0, 'status' => 'confirmed', 'price_cents' => 1,
        ]));

        $booking = Booking::withoutGlobalScopes()->firstOrFail();
        expect($booking->is_vip)->toBeFalse()
            ->and($booking->source)->toBe(BookingSource::Client)
            ->and($booking->status)->toBe(BookingStatus::Pending)
            ->and($booking->deposit_cents)->toBe(1750)
            ->and($booking->price_cents)->toBe(3500);
    });

    it('validates the request', function (array $override, string $field) {
        $this->actingAs($this->customer)
            ->post(route('salons.booking.store', 'barberia-lucho'), bookingPayload('10:00', $override))
            ->assertSessionHasErrors($field);

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'no start' => [['start' => null], 'start'],
        'start as text' => [['start' => 'mañana'], 'start'],
        'markup in the note' => [['note' => '<script>x</script>'], 'note'],
        'note too long' => [['note' => str_repeat('a', 256)], 'note'],
        'services keyed' => [['services' => ['a' => ['id' => 1]]], 'services'],
        'professional of another salon' => [['staff' => 999999], 'staff'],
    ]);
});

describe('mis reservas', function () {
    it('lists only my own bookings, across salons, on each salon clock', function () {
        $mine = existingBooking('10:00', $this->customer);
        existingBooking('11:00', User::factory()->create());

        $this->actingAs($this->customer)->get(route('bookings.index'))->assertInertia(fn (Assert $page) => $page
            ->component('client/bookings/index')
            ->has('bookings', 1)
            ->where('bookings.0.code', $mine->code)
            ->where('bookings.0.date', '2026-10-07')
            ->where('bookings.0.time', '10:00')
            ->where('bookings.0.endTime', '10:30')
            ->where('bookings.0.staffName', 'Pedro')
            ->where('bookings.0.salon.name', $this->salon->name));
    });

    it('works for a client who is also the owner of another salon', function () {
        $otherOwner = User::factory()->create();
        Salon::factory()->ownedBy($otherOwner)->create();
        $booking = existingBooking('10:00', $otherOwner);

        $this->actingAs($otherOwner)->get(route('bookings.show', $booking->code))->assertOk();
    });

    it('hides a booking that is not mine, whatever I try', function () {
        $theirs = existingBooking('10:00', User::factory()->create());

        $this->actingAs($this->customer)->get(route('bookings.show', $theirs->code))->assertNotFound();
        $this->actingAs($this->customer)->post(route('bookings.cancel', $theirs->code))->assertNotFound();
        $this->actingAs($this->owner)->get(route('bookings.show', $theirs->code))->assertNotFound();

        expect($theirs->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('shows how to send the deposit while it is pending', function () {
        $this->salon->forceFill(['deposit_percent' => 30, 'payment_instructions' => 'Yape 987654321'])->save();
        $booking = existingBooking('10:00', $this->customer);

        $this->actingAs($this->customer)->get(route('bookings.show', $booking->code))->assertInertia(fn (Assert $page) => $page
            ->where('booking.status', 'pending')
            ->where('booking.deposit.amount', '10.50')
            ->where('booking.deposit.instructions', 'Yape 987654321')
            ->where('booking.deposit.whatsappUrl', fn (string $url) => str_starts_with($url, 'https://wa.me/51999888777?text=')
                && str_contains(urldecode($url), $booking->code)
                && str_contains(urldecode($url), 'S/ 10.50')
                && str_contains(urldecode($url), '07/10/2026'))
            ->where('booking.canCancel', true));

        $this->travel(31)->minutes();

        $this->actingAs($this->customer)->get(route('bookings.show', $booking->code))->assertInertia(fn (Assert $page) => $page
            ->where('booking.status', 'expired')
            ->where('booking.deposit.whatsappUrl', null)
            ->where('booking.canCancel', false));
    });

    it('lets me cancel before it starts, and not after', function () {
        $early = existingBooking('10:00', $this->customer);
        $late = existingBooking('11:00', $this->customer);

        $this->actingAs($this->customer)->post(route('bookings.cancel', $early->code))->assertRedirect(route('bookings.show', $early->code));

        $this->travelTo(CarbonImmutable::parse('2026-10-07 11:05', 'America/Lima'));
        $this->actingAs($this->customer)->post(route('bookings.cancel', $late->code))->assertInertiaFlash('toast.type', 'error');

        expect($early->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($early->events()->get()->last()->changed_by)->toBe($this->customer->id)
            ->and($late->refresh()->status)->toBe(BookingStatus::Confirmed);
    });
});

describe('agenda', function () {
    beforeEach(function () {
        $this->member = SalonMember::factory()->for($this->salon)->create();
        $this->colleague = Staff::factory()->for($this->salon)->linkedTo($this->member->user)->working([3], '09:00', '18:00')->create(['name' => 'Kevin']);
        $this->colleague->services()->attach($this->cut->id);
        $this->pedros = existingBooking('10:00');
        $this->kevins = existingBooking('10:00', staff: $this->colleague);
    });

    it('shows the owner the whole team and lets them filter', function () {
        $this->actingAs($this->owner)->get(route('agenda.index'))->assertInertia(fn (Assert $page) => $page
            ->component('staff/agenda')
            ->where('date', '2026-10-07')
            ->has('bookings', 2)
            ->has('staffOptions', 2)
            ->where('seesEveryone', true)
            ->where('bookings.0.actions', ['start', 'move', 'cancel']));

        $this->actingAs($this->owner)->get(route('agenda.index', ['profesional' => $this->colleague->id]))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 1)->where('bookings.0.staffName', 'Kevin'));
    });

    it('shows a professional only their own appointments, whatever they ask for', function () {
        $this->actingAs($this->member->user)->get(route('agenda.index', ['profesional' => $this->staff->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bookings', 1)
                ->where('bookings.0.id', $this->kevins->id)
                ->where('staffOptions', [])
                ->where('seesEveryone', false));
    });

    it('shows nothing to a team member without a professional profile', function () {
        $receptionist = SalonMember::factory()->for($this->salon)->create();

        $this->actingAs($receptionist->user)->get(route('agenda.index'))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 0)->where('hasProfile', false));
    });

    it('moves to other days and falls back to today on a bad date', function () {
        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-08']))
            ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-08')->has('bookings', 0));
        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => 'ayer']))
            ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-07')->where('today', '2026-10-07'));
    });

    it('lets a professional handle their own appointment but not a colleague\'s', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:01', 'America/Lima'));

        $this->actingAs($this->member->user)->post(route('agenda.bookings.start', $this->pedros))->assertForbidden();
        $this->actingAs($this->member->user)->post(route('agenda.bookings.cancel', $this->pedros))->assertForbidden();
        $this->actingAs($this->member->user)->post(route('agenda.bookings.start', $this->kevins));
        $this->actingAs($this->member->user)->post(route('agenda.bookings.complete', $this->kevins));

        expect($this->pedros->refresh()->status)->toBe(BookingStatus::Confirmed)
            ->and($this->kevins->refresh()->status)->toBe(BookingStatus::Completed)
            ->and($this->kevins->events()->get()->last()->changed_by)->toBe($this->member->user_id);
    });

    it('needs the cash permission to say the deposit arrived', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();
        $pending = existingBooking('11:00', staff: $this->colleague);

        $this->actingAs($this->member->user)->post(route('agenda.bookings.confirm', $pending))->assertForbidden();
        $this->actingAs($this->member->user)->get(route('agenda.index'))
            ->assertInertia(fn (Assert $page) => $page->where('bookings.1.actions', ['move', 'cancel']));

        $this->member->forceFill(['permissions' => [SalonPermission::ManageCash]])->save();
        $this->actingAs($this->member->user->fresh())->post(route('agenda.bookings.confirm', $pending));

        expect($pending->refresh()->status)->toBe(BookingStatus::Confirmed)->and($pending->deposit_paid_at)->not->toBeNull();
    });

    it('marks a no-show and cancels from the agenda', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:20', 'America/Lima'));

        $this->actingAs($this->owner)->post(route('agenda.bookings.no-show', $this->pedros));
        $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $this->kevins));

        expect($this->pedros->refresh()->status)->toBe(BookingStatus::NoShow)
            ->and($this->kevins->refresh()->status)->toBe(BookingStatus::Cancelled);
    });

    it('answers an impossible jump with a message instead of an error page', function () {
        $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $this->pedros))->assertInertiaFlash('toast.type', 'error');

        expect($this->pedros->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('hides bookings of another salon from every action', function () {
        $outsider = User::factory()->create();
        Salon::factory()->ownedBy($outsider)->create();

        foreach (['confirm', 'start', 'complete', 'no-show', 'cancel'] as $action) {
            $this->actingAs($outsider)->post(route("agenda.bookings.{$action}", $this->pedros))->assertNotFound();
        }

        $this->actingAs($outsider)->get(route('agenda.index'))->assertInertia(fn (Assert $page) => $page->has('bookings', 0));
        expect($this->pedros->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('refuses changes while the salon is suspended, but lets it call off a booking it took', function () {
        $this->salon->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->owner)->post(route('agenda.bookings.start', $this->pedros));

        expect($this->pedros->refresh()->status)->toBe(BookingStatus::Confirmed);

        $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $this->pedros));

        expect($this->pedros->refresh()->status)->toBe(BookingStatus::Cancelled);
    });
});

describe('VIP clients', function () {
    it('lists the salon clients and toggles VIP', function () {
        $client = SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa']);
        SalonClient::factory()->for(Salon::factory()->create())->create(['name' => 'Ajena']);

        $this->actingAs($this->owner)->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page
            ->component('owner/clients/index')
            ->has('clients', 1)
            ->where('clients.0.name', 'Rosa')
            ->where('clients.0.isVip', false));

        $this->actingAs($this->owner)->patch(route('clients.vip.update', $client), ['is_vip' => true]);

        expect($client->refresh()->is_vip)->toBeTrue();
    });

    it('searches by name or phone', function () {
        SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa Quispe', 'phone' => '911111111']);
        SalonClient::factory()->for($this->salon)->create(['name' => 'Luis Mamani', 'phone' => '922222222']);

        $this->actingAs($this->owner)->get(route('clients.index', ['buscar' => 'quis']))
            ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.name', 'Rosa Quispe'));
        $this->actingAs($this->owner)->get(route('clients.index', ['buscar' => '9222']))
            ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.name', 'Luis Mamani'));
        $this->actingAs($this->owner)->get(route('clients.index', ['buscar' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('clients', 0));
    });

    it('is only for whoever may mark VIP clients, inside their own salon', function () {
        $member = SalonMember::factory()->for($this->salon)->create();
        $client = SalonClient::factory()->for($this->salon)->create();
        $foreign = SalonClient::factory()->for(Salon::factory()->create())->create();

        $this->actingAs($member->user)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($member->user)->patch(route('clients.vip.update', $client), ['is_vip' => true])->assertForbidden();
        $this->actingAs($this->owner)->patch(route('clients.vip.update', $foreign), ['is_vip' => true])->assertNotFound();

        expect($client->refresh()->is_vip)->toBeFalse()->and($foreign->refresh()->is_vip)->toBeFalse();
    });

    it('cannot make myself VIP as a client', function () {
        $client = SalonClient::factory()->for($this->salon)->forUser($this->customer)->create();

        // Not a member of any salon: the client record does not even resolve.
        $this->actingAs($this->customer)->patch(route('clients.vip.update', $client), ['is_vip' => true])->assertNotFound();

        expect($client->refresh()->is_vip)->toBeFalse();
    });
});

describe('booking rules of the salon', function () {
    function rulesPayload(array $overrides = []): array
    {
        return [
            'accepts_online_bookings' => true, 'min_lead_minutes' => 120, 'max_days_ahead' => 30, 'slot_step_minutes' => 30,
            'cancel_window_hours' => 12, 'deposit_percent' => 20, 'vip_deposit_percent' => null, 'hold_minutes' => 45,
            'payment_instructions' => 'Yape 987 654 321 a nombre de Luis', ...$overrides,
        ];
    }

    it('saves the rules', function () {
        $this->actingAs($this->owner)->put(route('salon.booking.update'), rulesPayload(['vip_deposit_percent' => 0]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('salon.edit'));

        $salon = $this->salon->refresh();
        expect($salon->min_lead_minutes)->toBe(120)
            ->and($salon->slot_step_minutes)->toBe(30)
            ->and($salon->deposit_percent)->toBe(20)
            ->and($salon->vip_deposit_percent)->toBe(0)
            ->and($salon->payment_instructions)->toBe('Yape 987 654 321 a nombre de Luis');
    });

    it('validates them', function (array $override, string $field) {
        $this->actingAs($this->owner)->put(route('salon.booking.update'), rulesPayload($override))->assertSessionHasErrors($field);
    })->with([
        'deposit over 100' => [['deposit_percent' => 101], 'deposit_percent'],
        'negative deposit' => [['deposit_percent' => -1], 'deposit_percent'],
        'odd grid' => [['slot_step_minutes' => 7], 'slot_step_minutes'],
        'no horizon' => [['max_days_ahead' => 0], 'max_days_ahead'],
        'hold too short' => [['hold_minutes' => 1], 'hold_minutes'],
        'markup in payment data' => [['payment_instructions' => '[paga aquí](http://x)'], 'payment_instructions'],
    ]);

    it('keeps them away from staff and from unrelated fields', function () {
        $member = SalonMember::factory()->for($this->salon)->create();

        $this->actingAs($member->user)->put(route('salon.booking.update'), rulesPayload())->assertForbidden();
        $this->actingAs($this->owner)->put(route('salon.booking.update'), rulesPayload(['status' => 'active', 'slug' => 'robado', 'trial_ends_at' => '2030-01-01']));

        expect($this->salon->refresh()->slug)->toBe('barberia-lucho')->and($this->salon->status->value)->toBe('trial');
    });
});
