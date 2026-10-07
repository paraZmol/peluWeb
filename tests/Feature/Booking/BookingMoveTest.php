<?php

use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\SalonPermission;
use App\Enums\ScheduleAccess;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonHoliday;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 4.8: the team books for a client without an account, and bookings
| are moved (by the team, or by their client with the salon's notice).
| "Now" is Wednesday 2026-10-07 08:00 in Lima; Pedro and Kevin work Wed,
| Thu and Fri 09:00–18:00. Kevin is a team member without extra permissions.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-lucho', 'timezone' => 'America/Lima', 'phone' => '999888777']);
    $this->pedro = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->member = SalonMember::factory()->for($this->salon)->create();
    $this->kevin = Staff::factory()->for($this->salon)->linkedTo($this->member->user)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Kevin']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->pedro->services()->attach($this->cut->id);
    $this->kevin->services()->attach($this->cut->id);
    $this->customer = User::factory()->create(['name' => 'Ana Cliente', 'phone' => '+51911222333']);
});

function moveAt(string $time, string $date = '2026-10-07'): int
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima')->getTimestamp();
}

/**
 * A booking made through the action. With $user it belongs to that account.
 */
function movable(string $time, string $date = '2026-10-08', ?Staff $staff = null, ?User $user = null, ?Service $service = null): Booking
{
    $test = test();
    $client = $user === null
        ? SalonClient::factory()->for($test->salon)->create()
        : (SalonClient::withoutGlobalScopes()->where('salon_id', $test->salon->id)->where('user_id', $user->id)->first()
            ?? SalonClient::factory()->for($test->salon)->forUser($user)->create());

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: $client,
        selections: [['service_id' => ($service ?? $test->cut)->id, 'variant_id' => null]],
        staff: $staff ?? $test->pedro,
        startsAt: CarbonImmutable::createFromTimestampUTC(moveAt($time, $date)),
        source: BookingSource::Client,
    ));
}

function agendaPayload(array $overrides = []): array
{
    return [
        'services' => [['id' => test()->cut->id]],
        'staff' => test()->pedro->id,
        'start' => moveAt('10:00'),
        'client_name' => 'Don Lucho',
        ...$overrides,
    ];
}

function moveTimes(TestResponse $response): Collection
{
    return collect($response->json('slots'))->pluck('time');
}

describe('the team books for a client', function () {
    it('opens the form with every professional for whoever runs the agenda', function () {
        $this->actingAs($this->owner)->get(route('agenda.bookings.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/booking-create')
                ->has('salon.staff', 2)
                ->has('salon.serviceGroups.0.services', 1)
                ->has('days', 61)
                ->where('days.0', ['date' => '2026-10-07', 'closed' => false]));
    });

    it('shows a professional only themselves', function () {
        $this->actingAs($this->member->user)->get(route('agenda.bookings.create'))
            ->assertInertia(fn (Assert $page) => $page->has('salon.staff', 1)->where('salon.staff.0.name', 'Kevin'));
    });

    it('books for someone without an account: confirmed, no deposit, in any hours', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'accepts_online_bookings' => false, 'min_lead_minutes' => 600])->save();

        $this->actingAs($this->owner)
            ->post(route('agenda.bookings.store'), agendaPayload(['client_phone' => '987654321', 'note' => 'Llamó por teléfono']))
            ->assertRedirect(route('agenda.index', ['fecha' => '2026-10-07']));

        $booking = Booking::withoutGlobalScopes()->sole();
        $client = SalonClient::withoutGlobalScopes()->sole();
        expect($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->source)->toBe(BookingSource::Staff)
            ->and($booking->deposit_cents)->toBe(0)
            ->and($booking->created_by)->toBe($this->owner->id)
            ->and($booking->client_note)->toBe('Llamó por teléfono')
            ->and($client->only(['name', 'phone', 'user_id', 'is_vip', 'salon_id']))
            ->toBe(['name' => 'Don Lucho', 'phone' => '987654321', 'user_id' => null, 'is_vip' => false, 'salon_id' => $this->salon->id]);
    });

    it('offers and books team-only hours, which clients never see', function () {
        $reserved = Staff::factory()->for($this->salon)->working([3], '20:00', '21:00', ScheduleAccess::StaffOnly)->create();
        $reserved->services()->attach($this->cut->id);
        $query = ['date' => '2026-10-07', 'services' => [['id' => $this->cut->id]], 'staff' => $reserved->id];

        $team = $this->actingAs($this->owner)->getJson(route('agenda.bookings.availability', $query))->assertOk();
        $public = $this->getJson(route('salons.booking.availability', ['salon' => 'barberia-lucho', ...$query]));

        expect(moveTimes($team))->toContain('20:00')
            ->and($team->json('quote.depositPercent'))->toBe(0)
            ->and(moveTimes($public))->toBeEmpty();

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), agendaPayload(['staff' => $reserved->id, 'start' => moveAt('20:00')]));
        expect(Booking::withoutGlobalScopes()->count())->toBe(1);
    });

    it('reuses the client picked from the list and the one with the same phone', function () {
        $known = SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa', 'phone' => '900111222']);

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), agendaPayload(['client_id' => $known->id, 'client_name' => null]));
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), agendaPayload(['client_name' => 'Rosita', 'client_phone' => '900111222', 'start' => moveAt('11:00')]));

        expect(SalonClient::withoutGlobalScopes()->count())->toBe(1)
            ->and(Booking::withoutGlobalScopes()->where('salon_client_id', $known->id)->count())->toBe(2);
    });

    it('does not accept a client of another salon', function () {
        $foreign = SalonClient::factory()->for(Salon::factory()->create())->create();

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), agendaPayload(['client_id' => $foreign->id, 'client_name' => null]))
            ->assertSessionHasErrors('client_id');

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    });

    it('leaves no empty client behind when the time was just taken', function () {
        movable('10:00', '2026-10-07');
        $before = SalonClient::withoutGlobalScopes()->count();

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), agendaPayload())->assertInertiaFlash('toast.type', 'error');

        expect(SalonClient::withoutGlobalScopes()->count())->toBe($before)
            ->and(Booking::withoutGlobalScopes()->count())->toBe(1);
    });

    it('lets a professional book only with themselves', function () {
        $this->actingAs($this->member->user)->post(route('agenda.bookings.store'), agendaPayload())->assertForbidden();
        $this->actingAs($this->member->user)->getJson(route('agenda.bookings.availability', ['date' => '2026-10-07', 'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id]))->assertForbidden();

        // "Anyone" means themselves.
        $this->actingAs($this->member->user)->post(route('agenda.bookings.store'), agendaPayload(['staff' => null]));

        expect(Booking::withoutGlobalScopes()->sole()->staff_id)->toBe($this->kevin->id);
    });

    it('is closed to team members without a profile, to clients and to other salons', function () {
        $receptionist = SalonMember::factory()->for($this->salon)->create();
        $outsider = User::factory()->create();
        Salon::factory()->ownedBy($outsider)->create();

        $this->actingAs($receptionist->user)->get(route('agenda.bookings.create'))->assertForbidden();
        $this->actingAs($receptionist->user)->post(route('agenda.bookings.store'), agendaPayload())->assertForbidden();
        $this->actingAs($receptionist->user)->getJson(route('agenda.bookings.clients', ['buscar' => 'an']))->assertForbidden();
        // In their own salon Pedro and the service do not exist.
        $this->actingAs($outsider)->post(route('agenda.bookings.store'), agendaPayload())->assertSessionHasErrors();

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    });

    it('lets whoever sees every agenda book with anyone', function () {
        $receptionist = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ViewAllSchedules)->create();

        $this->actingAs($receptionist->user)->post(route('agenda.bookings.store'), agendaPayload());

        expect(Booking::withoutGlobalScopes()->sole()->staff_id)->toBe($this->pedro->id);
    });

    it('validates the request', function (array $override, string $field) {
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), agendaPayload($override))->assertSessionHasErrors($field);

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'no client' => [['client_name' => null], 'client_name'],
        'one letter' => [['client_name' => 'A'], 'client_name'],
        'markup in the name' => [['client_name' => '<b>Ana</b>'], 'client_name'],
        'letters in the phone' => [['client_phone' => 'nueve'], 'client_phone'],
        'no start' => [['start' => null], 'start'],
        'no services' => [['services' => []], 'services'],
    ]);

    it('suggests clients of this salon only, by name or phone', function () {
        SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa Díaz', 'phone' => '900111222']);
        SalonClient::factory()->for($this->salon)->create(['name' => 'Mario', 'phone' => '955000111']);
        SalonClient::factory()->for(Salon::factory()->create())->create(['name' => 'Rosa Ajena']);
        $search = fn (string $text) => $this->actingAs($this->member->user)->getJson(route('agenda.bookings.clients', ['buscar' => $text]));

        expect($search('ros')->json('clients'))->toHaveCount(1)
            ->and($search('ros')->json('clients.0'))->toMatchArray(['name' => 'Rosa Díaz', 'phone' => '900111222', 'isVip' => false])
            ->and($search('9550')->json('clients.0.name'))->toBe('Mario')
            ->and($search('r')->json('clients'))->toBe([])
            ->and($search('%%')->json('clients'))->toBe([]);
    });
});

describe('moving a booking', function () {
    it('moves it to another time and frees the old one', function () {
        $booking = movable('10:00');

        app(RescheduleBooking::class)->handle(new BookingMove($booking, CarbonImmutable::createFromTimestampUTC(moveAt('15:00', '2026-10-09')), byClient: false, actor: $this->owner));

        $booking->refresh();
        $segment = $booking->segments()->sole();
        $event = $booking->events()->get()->last();
        expect($booking->starts_at->getTimestamp())->toBe(moveAt('15:00', '2026-10-09'))
            ->and($booking->ends_at->getTimestamp())->toBe(moveAt('15:30', '2026-10-09'))
            ->and($segment->starts_at->getTimestamp())->toBe(moveAt('15:00', '2026-10-09'))
            ->and($segment->staff_id)->toBe($this->pedro->id)
            ->and($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($event->from_status)->toBe(BookingStatus::Confirmed)
            ->and($event->to_status)->toBe(BookingStatus::Confirmed)
            ->and($event->changed_by)->toBe($this->owner->id)
            ->and($event->note)->toBe('Movida desde el 08/10/2026 10:00');

        // The old time can be booked again; the new one cannot.
        movable('10:00');
        expect(fn () => movable('15:00', '2026-10-09'))->toThrow(BookingException::class);
    });

    it('does not block itself when it moves a little, but never lands on another booking', function () {
        $booking = movable('10:00');
        movable('11:00');
        $move = fn (string $time) => app(RescheduleBooking::class)->handle(new BookingMove($booking->refresh(), CarbonImmutable::createFromTimestampUTC(moveAt($time, '2026-10-08')), byClient: false));

        $move('10:15');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(moveAt('10:15', '2026-10-08'));

        expect(fn () => $move('10:45'))->toThrow(BookingException::class, 'Ese horario se acaba de ocupar');
        expect(fn () => $move('11:15'))->toThrow(BookingException::class);
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(moveAt('10:15', '2026-10-08'))
            ->and($booking->segments()->count())->toBe(1);
    });

    it('keeps the working stretches of a color apart when it moves', function () {
        $color = Service::factory()->for($this->salon)->create(['active_minutes' => 30, 'idle_minutes' => 30, 'finish_minutes' => 30]);
        $this->pedro->services()->attach($color->id);
        $booking = movable('10:00', service: $color);
        movable('14:30');

        // The other cut fits exactly in the processing time: 14:00–14:30, [14:30–15:00], 15:00–15:30.
        app(RescheduleBooking::class)->handle(new BookingMove($booking, CarbonImmutable::createFromTimestampUTC(moveAt('14:00', '2026-10-08')), byClient: false));

        expect($booking->segments()->get()->map(fn ($segment) => $segment->starts_at->setTimezone('America/Lima')->format('H:i'))->all())
            ->toBe(['14:00', '15:00']);
    });

    it('refuses time off, holidays, days off and the past', function () {
        $booking = movable('10:00');
        StaffTimeOff::factory()->for($this->pedro)->create([
            'salon_id' => $this->salon->id,
            'starts_at' => CarbonImmutable::parse('2026-10-08 13:00', 'America/Lima')->utc(),
            'ends_at' => CarbonImmutable::parse('2026-10-08 14:00', 'America/Lima')->utc(),
        ]);
        SalonHoliday::withoutGlobalScopes()->forceCreate(['salon_id' => $this->salon->id, 'date' => '2026-10-09', 'name' => 'Feriado']);
        $move = fn (string $time, string $date) => app(RescheduleBooking::class)->handle(new BookingMove($booking->refresh(), CarbonImmutable::createFromTimestampUTC(moveAt($time, $date)), byClient: false));

        expect(fn () => $move('13:15', '2026-10-08'))->toThrow(BookingException::class)
            ->and(fn () => $move('10:00', '2026-10-09'))->toThrow(BookingException::class)
            ->and(fn () => $move('10:00', '2026-10-10'))->toThrow(BookingException::class)
            ->and(fn () => $move('07:00', '2026-10-07'))->toThrow(BookingException::class)
            ->and(fn () => $move('18:00', '2026-10-08'))->toThrow(BookingException::class);
    });

    it('only moves bookings that are still waiting to happen', function (string $state) {
        $booking = movable('10:00');
        $booking->forceFill(['status' => $state])->save();

        expect(fn () => app(RescheduleBooking::class)->handle(new BookingMove($booking, CarbonImmutable::createFromTimestampUTC(moveAt('12:00', '2026-10-08')), byClient: false)))
            ->toThrow(BookingException::class, 'ya no admite ese cambio');
    })->with(['cancelled', 'completed', 'in_service', 'no_show', 'expired']);

    it('does not move a hold that already ran out, and keeps the hold of one that did not', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();
        $pending = movable('10:00');
        $expiresAt = $pending->hold_expires_at;

        app(RescheduleBooking::class)->handle(new BookingMove($pending, CarbonImmutable::createFromTimestampUTC(moveAt('12:00', '2026-10-08')), byClient: false));
        expect($pending->refresh()->status)->toBe(BookingStatus::Pending)
            ->and($pending->hold_expires_at->equalTo($expiresAt))->toBeTrue();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00', 'America/Lima'));
        expect(fn () => app(RescheduleBooking::class)->handle(new BookingMove($pending, CarbonImmutable::createFromTimestampUTC(moveAt('13:00', '2026-10-08')), byClient: false)))
            ->toThrow(BookingException::class);
    });

    it('hands it to a colleague who performs the same services, and to nobody else', function () {
        $booking = movable('10:00');
        $busy = movable('10:00', staff: $this->kevin);
        $noCuts = Staff::factory()->for($this->salon)->working([4], '09:00', '18:00')->create();
        $paused = Staff::factory()->for($this->salon)->working([4], '09:00', '18:00')->create(['is_active' => false]);
        $paused->services()->attach($this->cut->id);
        $foreign = Staff::factory()->for(Salon::factory()->create())->working([4], '09:00', '18:00')->create();
        $move = fn (Staff $staff, string $time) => app(RescheduleBooking::class)->handle(new BookingMove($booking->refresh(), CarbonImmutable::createFromTimestampUTC(moveAt($time, '2026-10-08')), byClient: false, staff: $staff));

        expect(fn () => $move($this->kevin, '10:00'))->toThrow(BookingException::class, 'Ese horario se acaba de ocupar')
            ->and(fn () => $move($noCuts, '12:00'))->toThrow(BookingException::class, 'no puede atender')
            ->and(fn () => $move($paused, '12:00'))->toThrow(BookingException::class, 'no puede atender')
            ->and(fn () => $move($foreign, '12:00'))->toThrow(BookingException::class, 'no puede atender');

        $move($this->kevin, '10:30');

        expect($booking->refresh()->staff_id)->toBe($this->kevin->id)
            ->and($booking->segments()->sole()->staff_id)->toBe($this->kevin->id)
            ->and($busy->refresh()->staff_id)->toBe($this->kevin->id);
        // Pedro is free again at 10:00.
        movable('10:00');
    });
});

describe('the team moves a booking from the agenda', function () {
    beforeEach(function () {
        $this->pedros = movable('10:00');
        $this->kevins = movable('10:00', staff: $this->kevin);
    });

    it('offers the move in the agenda with the days to pick', function () {
        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-08']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('bookings.0.actions', ['move', 'cancel'])
                ->where('bookings.0.date', '2026-10-08')
                ->where('bookings.0.staffId', $this->pedro->id)
                ->where('canBook', true)
                ->has('days', 61));
    });

    it('lists the times it could move to, its own included, in any hours', function () {
        movable('11:00');

        $times = moveTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->pedros, 'date' => '2026-10-08'])));

        expect($times)->toContain('10:00')->toContain('10:15')->toContain('09:00')->not->toContain('10:45')->not->toContain('11:00');
    });

    it('moves it and comes back to the agenda on the new day', function () {
        $this->actingAs($this->owner)
            ->post(route('agenda.bookings.move', $this->pedros), ['start' => moveAt('16:00', '2026-10-09')])
            ->assertRedirect(route('agenda.index', ['fecha' => '2026-10-09']));

        expect($this->pedros->refresh()->starts_at->getTimestamp())->toBe(moveAt('16:00', '2026-10-09'));
    });

    it('answers a taken time with a message and moves nothing', function () {
        movable('12:00');

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $this->pedros), ['start' => moveAt('12:00', '2026-10-08')])
            ->assertInertiaFlash('toast.type', 'error');

        expect($this->pedros->refresh()->starts_at->getTimestamp())->toBe(moveAt('10:00', '2026-10-08'));
    });

    it('lets a professional move their own bookings but neither a colleague\'s nor to a colleague', function () {
        $kevin = $this->actingAs($this->member->user);

        $kevin->post(route('agenda.bookings.move', $this->pedros), ['start' => moveAt('12:00', '2026-10-08')])->assertForbidden();
        $kevin->getJson(route('agenda.bookings.slots', [$this->pedros, 'date' => '2026-10-08']))->assertForbidden();
        $kevin->post(route('agenda.bookings.move', $this->kevins), ['start' => moveAt('12:00', '2026-10-08'), 'staff' => $this->pedro->id])->assertForbidden();
        $kevin->getJson(route('agenda.bookings.slots', [$this->kevins, 'date' => '2026-10-08', 'staff' => $this->pedro->id]))->assertForbidden();
        $kevin->post(route('agenda.bookings.move', $this->kevins), ['start' => moveAt('12:00', '2026-10-08'), 'staff' => $this->kevin->id]);

        expect($this->pedros->refresh()->starts_at->getTimestamp())->toBe(moveAt('10:00', '2026-10-08'))
            ->and($this->kevins->refresh()->starts_at->getTimestamp())->toBe(moveAt('12:00', '2026-10-08'))
            ->and($this->kevins->staff_id)->toBe($this->kevin->id);
    });

    it('lets the owner hand it to a colleague', function () {
        $times = moveTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->pedros, 'date' => '2026-10-08', 'staff' => $this->kevin->id])));
        expect($times)->not->toContain('10:00')->toContain('10:30');

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $this->pedros), ['start' => moveAt('10:30', '2026-10-08'), 'staff' => $this->kevin->id]);

        expect($this->pedros->refresh()->staff_id)->toBe($this->kevin->id);
    });

    it('hides bookings and professionals of another salon', function () {
        $outsider = User::factory()->create();
        $theirs = Salon::factory()->ownedBy($outsider)->create();
        $foreign = Staff::factory()->for($theirs)->working([4], '09:00', '18:00')->create();

        $this->actingAs($outsider)->post(route('agenda.bookings.move', $this->pedros), ['start' => moveAt('12:00', '2026-10-08')])->assertNotFound();
        $this->actingAs($outsider)->getJson(route('agenda.bookings.slots', [$this->pedros, 'date' => '2026-10-08']))->assertNotFound();
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $this->pedros), ['start' => moveAt('12:00', '2026-10-08'), 'staff' => $foreign->id])
            ->assertSessionHasErrors('staff');

        expect($this->pedros->refresh()->only(['staff_id']))->toBe(['staff_id' => $this->pedro->id])
            ->and($this->pedros->starts_at->getTimestamp())->toBe(moveAt('10:00', '2026-10-08'));
    });

    it('validates the request', function () {
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $this->pedros), [])->assertSessionHasErrors('start');
        $this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->pedros, 'date' => 'mañana']))->assertStatus(422);
    });
});

describe('a client moves their own booking', function () {
    beforeEach(function () {
        // Friday 10:00: two days ahead, outside the 24 h window.
        $this->mine = movable('10:00', '2026-10-09', user: $this->customer);
    });

    it('tells the page whether it can be moved, with the days to pick', function () {
        $this->actingAs($this->customer)->get(route('bookings.show', $this->mine->code))
            ->assertInertia(fn (Assert $page) => $page->where('booking.canMove', true)->has('days', 61));

        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:01', 'America/Lima'));
        $this->actingAs($this->customer)->get(route('bookings.show', $this->mine->code))
            ->assertInertia(fn (Assert $page) => $page->where('booking.canMove', false)->where('booking.canCancel', true));
    });

    it('lists only the times a client may book, with the same professional', function () {
        StaffSchedule::withoutGlobalScopes()->where('staff_id', $this->pedro->id)->where('weekday', 4)->delete();
        StaffSchedule::withoutGlobalScopes()->forceCreate([
            'salon_id' => $this->salon->id, 'staff_id' => $this->pedro->id, 'weekday' => 4, 'start_time' => '09:00', 'end_time' => '11:00', 'access_level' => ScheduleAccess::Vip,
        ]);
        $url = fn (string $date) => route('bookings.slots', [$this->mine->code, 'date' => $date]);

        expect(moveTimes($this->actingAs($this->customer)->getJson($url('2026-10-09'))))->toContain('10:00')->toContain('17:30')
            // Thursday is VIP only for Pedro, and Kevin's free times are not offered.
            ->and(moveTimes($this->actingAs($this->customer)->getJson($url('2026-10-08'))))->toBeEmpty()
            // Today: the salon's minimum notice applies.
            ->and(moveTimes($this->actingAs($this->customer)->getJson($url('2026-10-07'))))->not->toContain('08:30')->toContain('09:00');
    });

    it('moves it and records who did', function () {
        $this->actingAs($this->customer)
            ->post(route('bookings.move', $this->mine->code), ['start' => moveAt('16:00', '2026-10-09')])
            ->assertRedirect(route('bookings.show', $this->mine->code));

        expect($this->mine->refresh()->starts_at->getTimestamp())->toBe(moveAt('16:00', '2026-10-09'))
            ->and($this->mine->events()->get()->last()->changed_by)->toBe($this->customer->id);
    });

    it('ignores any attempt to pick another professional or team-only hours', function () {
        StaffSchedule::withoutGlobalScopes()->forceCreate([
            'salon_id' => $this->salon->id, 'staff_id' => $this->pedro->id, 'weekday' => 5, 'start_time' => '20:00', 'end_time' => '21:00', 'access_level' => ScheduleAccess::StaffOnly,
        ]);

        $this->actingAs($this->customer)->post(route('bookings.move', $this->mine->code), ['start' => moveAt('16:00', '2026-10-09'), 'staff' => $this->kevin->id, 'byClient' => false]);
        expect($this->mine->refresh()->staff_id)->toBe($this->pedro->id);

        $this->actingAs($this->customer)->post(route('bookings.move', $this->mine->code), ['start' => moveAt('20:00', '2026-10-09')])
            ->assertInertiaFlash('toast.type', 'error');
        expect($this->mine->refresh()->starts_at->getTimestamp())->toBe(moveAt('16:00', '2026-10-09'));
    });

    it('needs the same notice as a cancellation', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:01', 'America/Lima'));

        $this->actingAs($this->customer)->post(route('bookings.move', $this->mine->code), ['start' => moveAt('16:00', '2026-10-09')])
            ->assertInertiaFlash('toast.type', 'error');

        expect($this->mine->refresh()->starts_at->getTimestamp())->toBe(moveAt('10:00', '2026-10-09'))
            ->and($this->actingAs($this->customer)->getJson(route('bookings.slots', [$this->mine->code, 'date' => '2026-10-09']))->json('slots'))->toBe([]);
    });

    it('stops after a few moves', function () {
        foreach (['11:00', '12:00', '13:00', '14:00'] as $time) {
            $this->actingAs($this->customer)->post(route('bookings.move', $this->mine->code), ['start' => moveAt($time, '2026-10-09')]);
        }

        expect($this->mine->refresh()->starts_at->getTimestamp())->toBe(moveAt('13:00', '2026-10-09'));

        // The salon can still move it for them.
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $this->mine), ['start' => moveAt('15:00', '2026-10-09')]);
        expect($this->mine->refresh()->starts_at->getTimestamp())->toBe(moveAt('15:00', '2026-10-09'));
    });

    it('keeps the deposit and the price when it moves', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();
        $paid = movable('12:00', '2026-10-09', user: $this->customer);
        $paid->forceFill(['status' => BookingStatus::Confirmed, 'deposit_paid_at' => now(), 'hold_expires_at' => null])->save();
        $this->cut->forceFill(['price_cents' => 9900])->save();

        $this->actingAs($this->customer)->post(route('bookings.move', $paid->code), ['start' => moveAt('14:00', '2026-10-09')]);

        expect($paid->refresh()->only(['price_cents', 'deposit_cents']))->toBe(['price_cents' => 3500, 'deposit_cents' => 1050])
            ->and($paid->deposit_paid_at)->not->toBeNull()
            ->and($paid->starts_at->getTimestamp())->toBe(moveAt('14:00', '2026-10-09'));
    });

    it('hides a booking that is not mine', function () {
        $stranger = User::factory()->create();

        $this->post(route('bookings.move', $this->mine->code), ['start' => moveAt('16:00', '2026-10-09')])->assertRedirect(route('login'));
        $this->actingAs($stranger)->post(route('bookings.move', $this->mine->code), ['start' => moveAt('16:00', '2026-10-09')])->assertNotFound();
        $this->actingAs($stranger)->getJson(route('bookings.slots', [$this->mine->code, 'date' => '2026-10-09']))->assertNotFound();

        expect($this->mine->refresh()->starts_at->getTimestamp())->toBe(moveAt('10:00', '2026-10-09'));
    });
});
