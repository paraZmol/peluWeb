<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\SalonPermission;
use App\Enums\SalonStatus;
use App\Enums\ScheduleAccess;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
| Attempts to break step 4.8 one rule at a time: moving a booking and the
| team booking for clients. "Now" is Wednesday 2026-10-07 08:00 in Lima;
| Pedro and Kevin work Wed, Thu and Fri 09:00–18:00 unless a test replaces
| the hours. Kevin is a team member without extra permissions.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'salon-mover', 'timezone' => 'America/Lima']);
    $this->pedro = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->member = SalonMember::factory()->for($this->salon)->create();
    $this->kevin = Staff::factory()->for($this->salon)->linkedTo($this->member->user)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Kevin']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->color = Service::factory()->for($this->salon)->withIdle(20, 35, 25)->create(['name' => 'Tinte', 'price_cents' => 12000]);
    $this->pedro->services()->attach([$this->cut->id, $this->color->id]);
    $this->kevin->services()->attach($this->cut->id);
    $this->customer = User::factory()->create(['name' => 'Ana Cliente', 'phone' => '+51911222333']);
});

function mbAt(string $time, string $date = '2026-10-08', string $timezone = 'America/Lima'): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}", $timezone);
}

function mbClientOf(User $user): SalonClient
{
    return SalonClient::withoutGlobalScopes()->where('salon_id', test()->salon->id)->where('user_id', $user->id)->first()
        ?? SalonClient::factory()->for(test()->salon)->forUser($user)->create();
}

/**
 * A booking made through the action. With $user it belongs to that account.
 */
function mbBook(string $time, string $date = '2026-10-08', ?Staff $staff = null, ?User $user = null, ?Service $service = null, BookingSource $source = BookingSource::Client): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $user === null ? SalonClient::factory()->for(test()->salon)->create() : mbClientOf($user),
        selections: [['service_id' => ($service ?? test()->cut)->id, 'variant_id' => null]],
        staff: $staff ?? test()->pedro,
        startsAt: mbAt($time, $date),
        source: $source,
    ));
}

/**
 * Moves through the action, as the team unless $byClient.
 */
function mbMove(Booking $booking, string $time, string $date = '2026-10-08', bool $byClient = false, ?User $actor = null, ?Staff $staff = null): Booking
{
    return app(RescheduleBooking::class)->handle(new BookingMove($booking->refresh(), mbAt($time, $date), $byClient, $actor, $staff));
}

/**
 * Replace the hours of a professional, bypassing the action.
 *
 * @param  list<array{0: int, 1: string, 2: string, 3?: ScheduleAccess}>  $ranges  [weekday, start, end, access]
 */
function mbHours(Staff $staff, array $ranges): void
{
    StaffSchedule::withoutGlobalScopes()->where('staff_id', $staff->id)->delete();

    foreach ($ranges as $range) {
        StaffSchedule::withoutGlobalScopes()->forceCreate([
            'salon_id' => $staff->salon_id,
            'staff_id' => $staff->id,
            'weekday' => $range[0],
            'start_time' => $range[1],
            'end_time' => $range[2],
            'access_level' => $range[3] ?? ScheduleAccess::Public,
        ]);
    }
}

function mbTimes(TestResponse $response): Collection
{
    return collect($response->json('slots'))->pluck('time');
}

/**
 * The stretches of a booking as "HH:MM-HH:MM" on the salon's clock.
 *
 * @return list<string>
 */
function mbStretches(Booking $booking, string $timezone = 'America/Lima'): array
{
    return $booking->segments()->get()
        ->map(fn ($segment) => $segment->starts_at->setTimezone($timezone)->format('H:i').'-'.$segment->ends_at->setTimezone($timezone)->format('H:i'))
        ->all();
}

function mbMoves(Booking $booking): int
{
    return $booking->events()->whereColumn('from_status', 'to_status')->count();
}

describe('moving a color (apply, process, finish)', function () {
    it('moves it so that another visit falls inside its processing time, never on its finish', function () {
        // The cut is booked into the processing time of the color (14:20–14:55),
        // and then the color goes away to the morning.
        $color = mbBook('14:00', service: $this->color);
        $cut = mbBook('14:20');
        mbMove($color, '10:00');
        expect(mbStretches($color))->toBe(['10:00-10:20', '10:55-11:20']);

        // 13:30 would finish 14:25–14:50 and 14:15 would apply 14:15–14:35: both on the cut (14:20–14:50).
        expect(fn () => mbMove($color, '13:30'))->toThrow(BookingException::class, 'Ese horario se acaba de ocupar')
            ->and(fn () => mbMove($color, '14:15'))->toThrow(BookingException::class, 'Ese horario se acaba de ocupar');
        expect(mbStretches($color->refresh()))->toBe(['10:00-10:20', '10:55-11:20']);

        // 14:00: applies 14:00–14:20, the cut runs while it processes, finishes 14:55–15:20.
        mbMove($color, '14:00');

        expect(mbStretches($color->refresh()))->toBe(['14:00-14:20', '14:55-15:20'])
            ->and($color->ends_at->getTimestamp())->toBe(mbAt('15:20')->getTimestamp())
            ->and($color->segments()->pluck('staff_id')->unique()->all())->toBe([$this->pedro->id])
            ->and(mbStretches($cut))->toBe(['14:20-14:50']);
    });

    it('does not let another visit move onto the finish of a color, but lets it into the processing time', function () {
        $color = mbBook('14:00', service: $this->color);
        $cut = mbBook('11:00');
        $times = mbTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$cut, 'date' => '2026-10-08'])));

        // Free while it processes (14:20–14:55), taken again 14:55–15:20.
        expect($times)->toContain('14:20')->toContain('15:20')
            ->not->toContain('14:00')->not->toContain('14:30')->not->toContain('14:45')->not->toContain('15:00')->not->toContain('15:15');

        expect(fn () => mbMove($cut, '14:30'))->toThrow(BookingException::class)
            ->and(fn () => mbMove($cut, '14:55'))->toThrow(BookingException::class)
            ->and(fn () => mbMove($cut, '15:00'))->toThrow(BookingException::class);

        mbMove($cut, '14:20');

        expect(mbStretches($cut->refresh()))->toBe(['14:20-14:50'])
            ->and(mbStretches($color))->toBe(['14:00-14:20', '14:55-15:20']);
    });

    it('does not slide a color a little when that puts either stretch on the visit sitting in its processing time', function () {
        $color = mbBook('10:00', service: $this->color);
        $cut = mbBook('10:20');

        // 10:15 → applies 10:15–10:35 (on the cut); 09:45 → finishes 10:40–11:05 (on the cut).
        expect(fn () => mbMove($color, '10:15'))->toThrow(BookingException::class)
            ->and(fn () => mbMove($color, '09:45'))->toThrow(BookingException::class)
            ->and(mbStretches($color->refresh()))->toBe(['10:00-10:20', '10:55-11:20'])
            ->and(mbStretches($cut))->toBe(['10:20-10:50']);
    });

    it('needs the finish inside the working hours too: the processing time may run over lunch, the finish may not', function () {
        // Lunch from 12:50 to 13:25.
        mbHours($this->pedro, [[4, '09:00', '12:50'], [4, '13:25', '18:00']]);
        $color = mbBook('09:00', service: $this->color);

        // 12:15: applies until 12:35, finishes 13:10–13:35: partly in the lunch break.
        expect(fn () => mbMove($color, '12:15'))->toThrow(BookingException::class);

        // 17:00 would finish 17:55–18:20, after closing.
        expect(fn () => mbMove($color, '17:00'))->toThrow(BookingException::class);
        expect(mbStretches($color->refresh()))->toBe(['09:00-09:20', '09:55-10:20']);

        // 12:30: applies until 12:50, processes during lunch, finishes 13:25–13:50.
        mbMove($color, '12:30');
        expect(mbStretches($color->refresh()))->toBe(['12:30-12:50', '13:25-13:50']);
    });
});

describe('moving across midnight and to days without hours', function () {
    beforeEach(function () {
        $this->long = Service::factory()->for($this->salon)->create(['active_minutes' => 60]);
        $this->pedro->services()->attach($this->long->id);
        // Wednesday night until 23:59, Thursday from midnight to 02:00 and the usual day.
        mbHours($this->pedro, [[3, '20:00', '23:59'], [4, '00:00', '02:00'], [4, '09:00', '18:00'], [5, '09:00', '18:00']]);
        $this->booking = mbBook('10:00', service: $this->long);
    });

    it('never lets a visit run past midnight from one working day into the next', function () {
        $times = mbTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->booking, 'date' => '2026-10-07'])));

        expect($times)->toContain('20:00')->toContain('22:45')
            ->not->toContain('23:00')->not->toContain('23:15')->not->toContain('23:30')->not->toContain('23:45');

        expect(fn () => mbMove($this->booking, '23:00', '2026-10-07'))->toThrow(BookingException::class)
            ->and(fn () => mbMove($this->booking, '23:30', '2026-10-07'))->toThrow(BookingException::class);

        expect($this->booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00')->getTimestamp());
    });

    it('keeps the salon\'s day when the visit moves to a late hour that is already tomorrow in UTC', function () {
        // Wednesday 22:45 in Lima is Thursday 03:45 UTC.
        $this->actingAs($this->owner)
            ->post(route('agenda.bookings.move', $this->booking), ['start' => mbAt('22:45', '2026-10-07')->getTimestamp()])
            ->assertRedirect(route('agenda.index', ['fecha' => '2026-10-07']));

        expect($this->booking->refresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-08 03:45')
            ->and(mbStretches($this->booking))->toBe(['22:45-23:45'])
            // The event names the day it left on the salon's clock.
            ->and($this->booking->events()->get()->last()->note)->toBe('Movida desde el 08/10/2026 10:00');

        // The first hour of Thursday belongs to Thursday, not to Wednesday night.
        $wednesday = mbTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->booking, 'date' => '2026-10-07'])));
        $thursday = mbTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->booking, 'date' => '2026-10-08'])));

        expect($wednesday)->not->toContain('00:00')->not->toContain('01:00')
            ->and($thursday)->toContain('00:00')->toContain('01:00')->not->toContain('01:15')->not->toContain('22:45');
    });

    it('offers nothing and moves nothing on a day the professional does not work', function () {
        expect($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->booking, 'date' => '2026-10-10']))->json('slots'))->toBe([])
            ->and(fn () => mbMove($this->booking, '10:00', '2026-10-10'))->toThrow(BookingException::class);
    });
});

describe('moving a visit whose professional or service changed afterwards', function () {
    it('does not move a visit within the agenda of a professional who was paused, but lets the team hand it over', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        $this->pedro->forceFill(['is_active' => false])->save();

        expect($this->actingAs($this->customer)->getJson(route('bookings.slots', [$booking->code, 'date' => '2026-10-09']))->json('slots'))->toBe([])
            ->and($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-09']))->json('slots'))->toBe([]);

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp(), 'staff' => $this->kevin->id])
            ->assertRedirect(route('agenda.index', ['fecha' => '2026-10-09']));

        expect($booking->refresh()->staff_id)->toBe($this->kevin->id)
            ->and($booking->segments()->sole()->staff_id)->toBe($this->kevin->id);
    });

    it('answers with a message, not an error page, when the professional of the visit was removed, and lets the team hand it over', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        // Removed in spite of the visit (RemoveStaff would have refused).
        $this->pedro->delete();

        $this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-09']))->assertOk()->assertExactJson(['slots' => []]);
        $this->actingAs($this->customer)->getJson(route('bookings.slots', [$booking->code, 'date' => '2026-10-09']))->assertOk()->assertExactJson(['slots' => []]);
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp(), 'staff' => $this->pedro->id])
            ->assertInertiaFlash('toast.type', 'error');

        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp(), 'staff' => $this->kevin->id]);

        expect($booking->refresh()->staff_id)->toBe($this->kevin->id)
            ->and($booking->starts_at->getTimestamp())->toBe(mbAt('12:00', '2026-10-09')->getTimestamp());
    });

    it('does not hand a visit to a removed or paused colleague', function () {
        $booking = mbBook('10:00');
        $this->kevin->forceFill(['is_active' => false])->save();

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00')->getTimestamp(), 'staff' => $this->kevin->id])
            ->assertSessionHasErrors('staff');
        $this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08', 'staff' => $this->kevin->id]))->assertStatus(422);

        $this->kevin->forceFill(['is_active' => true])->save();
        $this->kevin->delete();

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00')->getTimestamp(), 'staff' => $this->kevin->id])
            ->assertSessionHasErrors('staff');
        expect(fn () => mbMove($booking, '12:00', staff: $this->kevin))->toThrow(BookingException::class, 'no puede atender');

        expect($booking->refresh()->staff_id)->toBe($this->pedro->id);
    });

    it('keeps the frozen durations and price when the service changed, was switched off or was deleted after booking', function () {
        $booking = mbBook('10:00');
        $this->cut->forceFill(['active_minutes' => 90, 'price_cents' => 9900, 'is_active' => false])->save();

        mbMove($booking, '17:30');
        expect(mbStretches($booking->refresh()))->toBe(['17:30-18:00'])->and($booking->price_cents)->toBe(3500);

        $this->cut->delete();
        mbMove($booking, '16:00', staff: $this->kevin);

        expect(mbStretches($booking->refresh()))->toBe(['16:00-16:30'])
            ->and($booking->staff_id)->toBe($this->kevin->id)
            ->and($booking->price_cents)->toBe(3500);
    });

    /*
    | Documents today's behaviour (reported as LOW): a service that no longer
    | exists (service_id null) is skipped by RescheduleBooking::canTake, so
    | the visit can be handed to a colleague who performs nothing at all.
    */
    it('does not hand a visit whose service no longer exists to a colleague, but still moves it in time', function () {
        $booking = mbBook('10:00');
        $newcomer = Staff::factory()->for($this->salon)->working([4], '09:00', '18:00')->create(['name' => 'Nuevo']);
        DB::table('booking_services')->where('booking_id', $booking->id)->update(['service_id' => null]);

        expect(fn () => mbMove($booking, '12:00', staff: $newcomer))->toThrow(BookingException::class, 'no puede atender');

        mbMove($booking, '12:00');

        expect($booking->refresh()->staff_id)->toBe($this->pedro->id)->and(mbStretches($booking))->toBe(['12:00-12:30']);
    });

    it('refuses a visit with two services to a colleague who performs only one of them', function () {
        $beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'active_minutes' => 15]);
        $this->pedro->services()->attach($beard->id);
        $booking = app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->salon->refresh(),
            client: SalonClient::factory()->for($this->salon)->create(),
            selections: [['service_id' => $this->cut->id, 'variant_id' => null], ['service_id' => $beard->id, 'variant_id' => null]],
            staff: $this->pedro,
            startsAt: mbAt('10:00'),
            source: BookingSource::Client,
        ));

        expect(fn () => mbMove($booking, '12:00', staff: $this->kevin))->toThrow(BookingException::class, 'no puede atender');
        expect($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08', 'staff' => $this->kevin->id]))->json('slots'))->toBe([]);

        $this->kevin->services()->attach($beard->id);
        mbMove($booking, '12:00', staff: $this->kevin);

        expect(mbStretches($booking->refresh()))->toBe(['12:00-12:45'])->and($booking->staff_id)->toBe($this->kevin->id);
    });
});

describe('moving to the very same place', function () {
    it('leaves no event and uses up none of the client\'s moves', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        $same = ['start' => mbAt('10:00', '2026-10-09')->getTimestamp()];

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), $same)->assertRedirect(route('bookings.show', $booking->code));
        }

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), [...$same, 'staff' => $this->pedro->id]);

        expect(mbMoves($booking))->toBe(0)
            ->and($booking->events()->count())->toBe(1)
            ->and($booking->refresh()->updated_at->equalTo($booking->created_at))->toBeTrue()
            ->and(mbStretches($booking))->toBe(['10:00-10:30']);

        // The three real moves are all still there.
        foreach (['11:00', '12:00', '13:00'] as $time) {
            $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt($time, '2026-10-09')->getTimestamp()]);
        }

        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('13:00', '2026-10-09')->getTimestamp())->and(mbMoves($booking))->toBe(3);
    });

    it('counts only the client\'s own moves towards their limit, not the team\'s', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);

        foreach (['11:00', '12:00', '13:00', '14:00'] as $time) {
            $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt($time, '2026-10-09')->getTimestamp()]);
        }

        foreach (['15:00', '16:00', '17:00', '09:00'] as $time) {
            $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt($time, '2026-10-09')->getTimestamp()]);
        }

        // Four by the team, then three by the client; the fourth of the client is refused.
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('17:00', '2026-10-09')->getTimestamp())
            ->and(mbMoves($booking))->toBe(7);
    });

    it('does not count a refused move', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        mbBook('12:00', '2026-10-09');

        foreach (range(1, 4) as $ignored) {
            $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
                ->assertInertiaFlash('toast.type', 'error');
        }

        expect(mbMoves($booking))->toBe(0);

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('13:00', '2026-10-09')->getTimestamp()]);
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('13:00', '2026-10-09')->getTimestamp());
    });
});

describe('moving a hold that is about to run out', function () {
    beforeEach(function () {
        $this->salon->forceFill(['deposit_percent' => 30, 'hold_minutes' => 30])->save();
        // Held until 08:30.
        $this->hold = mbBook('10:00', '2026-10-09', user: $this->customer);
    });

    it('moves it up to the last second of the hold and not at the instant it runs out', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:29:59', 'America/Lima'));
        mbMove($this->hold, '11:00', '2026-10-09');
        expect($this->hold->refresh()->starts_at->getTimestamp())->toBe(mbAt('11:00', '2026-10-09')->getTimestamp());

        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:30:00', 'America/Lima'));

        expect(fn () => mbMove($this->hold, '12:00', '2026-10-09'))->toThrow(BookingException::class, 'ya no admite ese cambio')
            ->and(fn () => mbMove($this->hold, '12:00', '2026-10-09', byClient: true, actor: $this->customer))->toThrow(BookingException::class)
            ->and($this->actingAs($this->customer)->getJson(route('bookings.slots', [$this->hold->code, 'date' => '2026-10-09']))->json('slots'))->toBe([])
            ->and($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$this->hold, 'date' => '2026-10-09']))->json('slots'))->toBe([]);
    });

    it('does not give the hold more time by moving it, and frees the new time when it runs out', function () {
        $expiresAt = $this->hold->hold_expires_at;

        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:29', 'America/Lima'));
        $this->actingAs($this->customer)->post(route('bookings.move', $this->hold->code), ['start' => mbAt('11:00', '2026-10-09')->getTimestamp()]);

        expect($this->hold->refresh()->hold_expires_at->equalTo($expiresAt))->toBeTrue()
            ->and($this->hold->status)->toBe(BookingStatus::Pending)
            ->and($this->hold->starts_at->getTimestamp())->toBe(mbAt('11:00', '2026-10-09')->getTimestamp());

        // 08:30: neither the old time nor the new one is held any more.
        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:30', 'America/Lima'));
        $taker = mbBook('11:00', '2026-10-09');
        mbBook('10:00', '2026-10-09');

        // And the late deposit cannot bring it back on top of whoever took it.
        expect(fn () => app(ChangeBookingStatus::class)->confirm($this->hold->refresh(), $this->owner))->toThrow(BookingException::class)
            ->and($taker->refresh()->status)->toBe(BookingStatus::Pending);
    });
});

describe('how far back and how far ahead a visit may move', function () {
    it('lets the team move a visit to a moment that started less than one grid step ago, and no further back', function () {
        $booking = mbBook('16:00', '2026-10-07');
        $other = mbBook('17:00', '2026-10-07');
        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:20', 'America/Lima'));

        expect(fn () => mbMove($booking, '10:00', '2026-10-07'))->toThrow(BookingException::class)
            ->and(fn () => mbMove($booking, '09:00', '2026-10-07'))->toThrow(BookingException::class)
            ->and(fn () => mbMove($booking, '10:00', '2026-10-06'))->toThrow(BookingException::class);

        $times = mbTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-07'])));
        expect($times->first())->toBe('10:15');

        mbMove($booking, '10:15', '2026-10-07');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:15', '2026-10-07')->getTimestamp());

        // A client never moves into the past, nor inside the salon's minimum notice.
        expect(fn () => mbMove($other, '10:15', '2026-10-07', byClient: true, actor: $this->customer))->toThrow(BookingException::class);
    });

    it('keeps the client inside the minimum notice and the booking horizon, which do not bind the team', function () {
        $this->salon->forceFill(['min_lead_minutes' => 120, 'max_days_ahead' => 2])->save();
        mbHours($this->pedro, [[1, '09:00', '18:00'], [3, '09:00', '18:00'], [5, '09:00', '18:00'], [6, '09:00', '18:00']]);
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        $slots = fn (string $date) => mbTimes($this->actingAs($this->customer)->getJson(route('bookings.slots', [$booking->code, 'date' => $date])));
        $move = fn (string $time, string $date) => $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt($time, $date)->getTimestamp()]);

        // Today from 10:00 (08:00 + 2 h); Friday is the last day; Saturday and Monday are beyond.
        expect($slots('2026-10-07')->first())->toBe('10:00')
            ->and($slots('2026-10-09'))->toContain('17:30')
            ->and($slots('2026-10-10'))->toBeEmpty()
            ->and($slots('2026-10-12'))->toBeEmpty()
            ->and($slots('2026-10-06'))->toBeEmpty();

        $move('09:45', '2026-10-07')->assertInertiaFlash('toast.type', 'error');
        $move('10:00', '2026-10-10')->assertInertiaFlash('toast.type', 'error');
        $move('10:00', '2026-10-12')->assertInertiaFlash('toast.type', 'error');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('10:00', '2026-10-12')->getTimestamp()]);
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-12')->getTimestamp());
    });

    it('lets a client bring a visit forward to today, after which the salon\'s notice stops further moves', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('09:00', '2026-10-07')->getTimestamp()]);
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('09:00', '2026-10-07')->getTimestamp());

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('10:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('09:00', '2026-10-07')->getTimestamp());
    });
});

describe('VIP and team-only hours when a client moves a booking', function () {
    beforeEach(function () {
        // Friday: public until 12:00, VIP until 14:00, team only until 16:00.
        mbHours($this->pedro, [
            [3, '09:00', '18:00'],
            [5, '09:00', '12:00'],
            [5, '12:00', '14:00', ScheduleAccess::Vip],
            [5, '14:00', '16:00', ScheduleAccess::StaffOnly],
        ]);
        $this->client = mbClientOf($this->customer);
        $this->url = fn (Booking $booking) => route('bookings.slots', [$booking->code, 'date' => '2026-10-09']);
    });

    it('never sends a standard client a VIP or team-only time, in the JSON itself', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);

        $response = $this->actingAs($this->customer)->getJson(($this->url)($booking))->assertOk();
        $latest = collect($response->json('slots'))->max('start');

        // 11:30–12:00 is the last one; 11:45 would end in VIP time.
        expect(mbTimes($response)->last())->toBe('11:30')
            ->and($latest)->toBe(mbAt('11:30', '2026-10-09')->getTimestamp())
            ->and($response->getContent())->not->toContain('"12:')->not->toContain('"13:')->not->toContain('"14:')->not->toContain('"15:');

        foreach (['11:45', '12:00', '13:30', '14:00', '15:30'] as $time) {
            $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt($time, '2026-10-09')->getTimestamp()])
                ->assertInertiaFlash('toast.type', 'error');
        }

        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());
    });

    it('sends a VIP client the VIP times and still no team-only time', function () {
        $this->client->forceFill(['is_vip' => true])->save();
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);

        $times = mbTimes($this->actingAs($this->customer)->getJson(($this->url)($booking)));

        // 11:45 crosses from public into VIP time: one window for a VIP.
        expect($times)->toContain('11:45')->toContain('13:30')->not->toContain('13:45')->not->toContain('14:00')->not->toContain('15:30');

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('14:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('13:30', '2026-10-09')->getTimestamp()]);

        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('13:30', '2026-10-09')->getTimestamp());
    });

    it('treats a client who lost their VIP after booking a VIP time as standard from then on', function () {
        $this->client->forceFill(['is_vip' => true])->save();
        $booking = mbBook('12:30', '2026-10-09', user: $this->customer);
        $this->client->forceFill(['is_vip' => false])->save();

        $response = $this->actingAs($this->customer)->getJson(($this->url)($booking));

        // Not even the VIP time the booking itself sits on is offered back.
        expect(mbTimes($response)->last())->toBe('11:30')
            ->and($response->getContent())->not->toContain('"12:')->not->toContain('"13:');

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('13:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('12:30', '2026-10-09')->getTimestamp());

        // Out of the VIP time it goes, and it cannot come back.
        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('11:00', '2026-10-09')->getTimestamp()]);
        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:30', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');

        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('11:00', '2026-10-09')->getTimestamp());
    });

    it('does not make someone VIP here because they are VIP in another salon', function () {
        $elsewhere = Salon::factory()->create();
        SalonClient::factory()->for($elsewhere)->forUser($this->customer)->vip()->create();
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);

        expect(mbTimes($this->actingAs($this->customer)->getJson(($this->url)($booking)))->last())->toBe('11:30');
    });

    it('lets the team move a client\'s booking into VIP and team-only times, which the client then cannot pick again', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);

        expect(mbTimes($this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-09']))))->toContain('13:30')->toContain('15:30')->not->toContain('15:45');

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('15:30', '2026-10-09')->getTimestamp()]);
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('15:30', '2026-10-09')->getTimestamp());

        $response = $this->actingAs($this->customer)->getJson(($this->url)($booking));
        expect(mbTimes($response)->last())->toBe('11:30')->and($response->getContent())->not->toContain('"15:');
    });
});

describe('a salon outside Lima and the day the clocks change', function () {
    beforeEach(function () {
        // Madrid leaves summer time on Sunday 2026-10-25: 03:00 becomes 02:00 again.
        $this->boss = User::factory()->create();
        $this->madrid = Salon::factory()->ownedBy($this->boss)->create(['timezone' => 'Europe/Madrid', 'min_lead_minutes' => 0]);
        $this->maria = Staff::factory()->for($this->madrid)->working([6, 1], '09:00', '18:00')->create(['name' => 'María']);
        StaffSchedule::withoutGlobalScopes()->forceCreate([
            'salon_id' => $this->madrid->id, 'staff_id' => $this->maria->id, 'weekday' => 7, 'start_time' => '00:00', 'end_time' => '06:00', 'access_level' => ScheduleAccess::Public,
        ]);
        $this->trim = Service::factory()->for($this->madrid)->create(['active_minutes' => 30]);
        $this->maria->services()->attach($this->trim->id);

        $this->book = fn (string $time, string $date): Booking => app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->madrid->refresh(),
            client: SalonClient::factory()->for($this->madrid)->create(),
            selections: [['service_id' => $this->trim->id, 'variant_id' => null]],
            staff: $this->maria,
            startsAt: mbAt($time, $date, 'Europe/Madrid'),
            source: BookingSource::Staff,
        ));
    });

    it('keeps the wall-clock time when a visit moves across the change of hour', function () {
        // Saturday 24th 10:00 is 08:00 UTC; Monday 26th 10:00 is 09:00 UTC.
        $booking = ($this->book)('10:00', '2026-10-24');
        expect($booking->starts_at->format('Y-m-d H:i'))->toBe('2026-10-24 08:00');

        $times = $this->actingAs($this->boss)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-26']))->json('slots');
        $ten = collect($times)->firstWhere('time', '10:00');

        expect($ten['start'])->toBe(mbAt('10:00', '2026-10-26', 'Europe/Madrid')->getTimestamp());

        $this->actingAs($this->boss)->post(route('agenda.bookings.move', $booking), ['start' => $ten['start']])
            ->assertRedirect(route('agenda.index', ['fecha' => '2026-10-26']));

        expect($booking->refresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-26 09:00')
            ->and(mbStretches($booking, 'Europe/Madrid'))->toBe(['10:00-10:30'])
            ->and($booking->events()->get()->last()->note)->toBe('Movida desde el 24/10/2026 10:00');
    });

    it('offers each real instant of the 25-hour day once and books both 02:30s without overlapping them', function () {
        $first = ($this->book)('10:00', '2026-10-24');
        $second = ($this->book)('11:00', '2026-10-24');

        $slots = collect($this->actingAs($this->boss)->getJson(route('agenda.bookings.slots', [$first, 'date' => '2026-10-25']))->json('slots'));

        // 00:00–06:00 lasts seven hours that night: 27 starts of 30 min on a 15 min grid.
        expect($slots)->toHaveCount(27)
            ->and($slots->pluck('start')->unique())->toHaveCount(27)
            // Documented ambiguity (reported as LOW): the two 02:30s carry the same label.
            ->and($slots->where('time', '02:30'))->toHaveCount(2);

        [$summer, $winter] = $slots->where('time', '02:30')->pluck('start')->values()->all();
        expect($winter - $summer)->toBe(3600);

        $this->actingAs($this->boss)->post(route('agenda.bookings.move', $first), ['start' => $summer]);
        $this->actingAs($this->boss)->post(route('agenda.bookings.move', $second), ['start' => $winter]);

        expect($first->refresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-25 00:30')
            ->and($second->refresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-25 01:30');

        // Each took its own instant: neither is offered to a third visit any more.
        $third = ($this->book)('12:00', '2026-10-24');
        $left = collect($this->actingAs($this->boss)->getJson(route('agenda.bookings.slots', [$third, 'date' => '2026-10-25']))->json('slots'))->pluck('start');

        expect($left)->not->toContain($summer)->not->toContain($winter)->not->toContain($summer + 900)->not->toContain($winter - 900);
    });

    it('does not move a visit of another salon\'s clock by the viewer\'s own', function () {
        $booking = ($this->book)('10:00', '2026-10-24');

        // The Lima owner is not a member there: the booking does not exist for them.
        $this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-26']))->assertNotFound();
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('10:00', '2026-10-26', 'Europe/Madrid')->getTimestamp()])->assertNotFound();

        expect($booking->refresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-24 08:00');
    });
});

describe('who may move what', function () {
    it('stops a professional from touching a visit once it was handed to a colleague', function () {
        $booking = mbBook('10:00', staff: $this->kevin);
        $kevin = $this->member->user;

        $this->actingAs($kevin)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08']))->assertOk();
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('11:00')->getTimestamp(), 'staff' => $this->pedro->id]);
        expect($booking->refresh()->staff_id)->toBe($this->pedro->id);

        $this->actingAs($kevin)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08']))->assertForbidden();
        $this->actingAs($kevin)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00')->getTimestamp()])->assertForbidden();
        $this->actingAs($kevin)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00')->getTimestamp(), 'staff' => $this->kevin->id])->assertForbidden();

        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('11:00')->getTimestamp())->and($booking->staff_id)->toBe($this->pedro->id);
    });

    it('lets whoever sees every agenda hand a visit over, and nobody with fewer permissions', function () {
        $desk = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ViewAllSchedules)->create();
        $cashier = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageCash, SalonPermission::ManageServices, SalonPermission::ManageVipClients)->create();
        $booking = mbBook('10:00');
        $payload = ['start' => mbAt('12:00')->getTimestamp(), 'staff' => $this->kevin->id];

        $this->actingAs($cashier->user)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08']))->assertForbidden();
        $this->actingAs($cashier->user)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08', 'staff' => $this->kevin->id]))->assertForbidden();
        $this->actingAs($cashier->user)->post(route('agenda.bookings.move', $booking), $payload)->assertForbidden();
        $this->actingAs($this->customer)->post(route('agenda.bookings.move', $booking), $payload)->assertForbidden();
        expect($booking->refresh()->staff_id)->toBe($this->pedro->id);

        $this->actingAs($desk->user)->post(route('agenda.bookings.move', $booking), $payload)->assertRedirect();
        expect($booking->refresh()->staff_id)->toBe($this->kevin->id);
    });

    it('reveals nothing about a professional of another salon through the times of a move', function () {
        $theirs = Salon::factory()->create();
        $foreign = Staff::factory()->for($theirs)->working([4], '09:00', '18:00')->create();
        $foreign->services()->attach(Service::factory()->for($theirs)->create()->id);
        $booking = mbBook('10:00');

        $this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08', 'staff' => $foreign->id]))
            ->assertStatus(422)->assertJsonMissingPath('slots');
        $this->actingAs($this->owner)->getJson(route('agenda.bookings.slots', [$booking, 'date' => '2026-10-08', 'staff' => 'x']))->assertStatus(422);

        // Straight to the action, as if the controller had let it through.
        expect(app(RescheduleBooking::class)->slots($booking, mbAt('10:00'), byClient: false, staff: $foreign))->toBe([])
            ->and(fn () => mbMove($booking, '12:00', staff: $foreign))->toThrow(BookingException::class, 'no puede atender');
    });

    it('ignores "staff", "byClient", "actor" and "booking" sent by a client, and never moves someone else\'s booking by its code', function () {
        $mine = mbBook('10:00', '2026-10-09', user: $this->customer);
        $theirs = mbBook('11:00', '2026-10-09', user: User::factory()->create());
        mbHours($this->kevin, [[5, '09:00', '20:00']]);

        // 19:00 exists only in Kevin's day.
        $this->actingAs($this->customer)->post(route('bookings.move', $mine->code), [
            'start' => mbAt('19:00', '2026-10-09')->getTimestamp(), 'staff' => $this->kevin->id, 'byClient' => 0, 'actor' => $this->owner->id, 'booking' => $theirs->id,
        ])->assertInertiaFlash('toast.type', 'error');
        $times = mbTimes($this->actingAs($this->customer)->getJson(route('bookings.slots', [$mine->code, 'date' => '2026-10-09', 'staff' => $this->kevin->id, 'byClient' => 0])));

        expect($times)->not->toContain('19:00')->not->toContain('11:00')
            ->and($mine->refresh()->staff_id)->toBe($this->pedro->id)
            ->and($mine->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());

        foreach ([$theirs->code, strtolower($theirs->code), (string) $theirs->id, '%25'] as $code) {
            $this->actingAs($this->customer)->post("/mis-reservas/{$code}/mover", ['start' => mbAt('16:00', '2026-10-09')->getTimestamp()])->assertNotFound();
            $this->actingAs($this->customer)->getJson("/mis-reservas/{$code}/horarios?date=2026-10-09")->assertNotFound();
        }

        expect($theirs->refresh()->starts_at->getTimestamp())->toBe(mbAt('11:00', '2026-10-09')->getTimestamp());
    });

    it('does not let a client reach the team\'s route for their own booking', function () {
        $mine = mbBook('10:00', '2026-10-09', user: $this->customer);

        // Without a salon of their own, the booking does not even resolve.
        $this->actingAs($this->customer)->post(route('agenda.bookings.move', $mine), ['start' => mbAt('16:00', '2026-10-09')->getTimestamp()])->assertNotFound();
        $this->actingAs($this->customer)->getJson(route('agenda.bookings.slots', [$mine, 'date' => '2026-10-09']))->assertNotFound();

        expect($mine->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());
    });
});

describe('a salon that cannot take changes', function () {
    it('moves nothing while the salon is suspended or its trial ran out', function (array $state) {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        $this->salon->forceFill($state)->save();

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');

        expect($this->actingAs($this->customer)->getJson(route('bookings.slots', [$booking->code, 'date' => '2026-10-09']))->json('slots'))->toBe([])
            ->and(fn () => mbMove($booking, '12:00', '2026-10-09'))->toThrow(BookingException::class)
            ->and($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());
    })->with([
        'suspended' => [['status' => SalonStatus::Suspended]],
        'trial over' => [['status' => SalonStatus::Trial, 'trial_ends_at' => '2026-09-20 00:00:00']], // past its 7 days of grace
    ]);

    it('hides the booking of a deleted salon from its client', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        $this->salon->delete();

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])->assertNotFound();
        $this->actingAs($this->customer)->getJson(route('bookings.slots', [$booking->code, 'date' => '2026-10-09']))->assertNotFound();
    });

    /*
    | Documents today's behaviour (reported as LOW): pausing online bookings
    | stops new ones, but clients can still move the ones they have.
    */
    it('does not let clients move their bookings while the salon is not taking online bookings, but the team can', function () {
        $booking = mbBook('10:00', '2026-10-09', user: $this->customer);
        $this->salon->forceFill(['accepts_online_bookings' => false])->save();

        $this->actingAs($this->customer)->post(route('bookings.move', $booking->code), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertInertiaFlash('toast.type', 'error');
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('10:00', '2026-10-09')->getTimestamp());

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => mbAt('12:00', '2026-10-09')->getTimestamp()]);
        expect($booking->refresh()->starts_at->getTimestamp())->toBe(mbAt('12:00', '2026-10-09')->getTimestamp());
    });
});

describe('bookings the team makes for clients', function () {
    it('does not count them against what the client may book online, nor the other way round', function () {
        $client = mbClientOf($this->customer);

        // Seven visits arranged at the desk for the same client: no limit.
        foreach (['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00'] as $time) {
            $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
                'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt($time, '2026-10-09')->getTimestamp(), 'client_id' => $client->id,
            ])->assertRedirect(route('agenda.index', ['fecha' => '2026-10-09']));
        }

        expect(Booking::withoutGlobalScopes()->where('salon_client_id', $client->id)->where('source', BookingSource::Staff)->count())->toBe(7);

        // The client still has their own five online.
        foreach (['13:00', '13:30', '14:00', '14:30', '15:00', '15:30'] as $time) {
            $this->actingAs($this->customer)->post(route('salons.booking.store', $this->salon), [
                'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt($time, '2026-10-09')->getTimestamp(),
            ]);
        }

        expect(Booking::withoutGlobalScopes()->where('salon_client_id', $client->id)->where('source', BookingSource::Client)->count())->toBe(CreateBooking::MAX_UPCOMING_PER_CLIENT);

        // And the desk can still add one more.
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt('16:00', '2026-10-09')->getTimestamp(), 'client_id' => $client->id,
        ]);
        expect(Booking::withoutGlobalScopes()->where('salon_client_id', $client->id)->count())->toBe(13);
    });

    it('leaves no client record behind when the booking is refused, and keeps one that already existed', function (array $override) {
        $payload = [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt('10:00')->getTimestamp(),
            'client_name' => 'Doña Rosa', 'client_phone' => '987654321', ...$override,
        ];

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), $payload);
        expect(SalonClient::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(0)
            ->and(Booking::withoutGlobalScopes()->count())->toBe(0);

        $known = SalonClient::factory()->for($this->salon)->create(['phone' => '987654321']);
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), $payload);

        expect(SalonClient::withoutGlobalScopes()->whereKey($known->id)->exists())->toBeTrue()
            ->and(Booking::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'a time outside the hours' => fn () => ['start' => mbAt('07:00')->getTimestamp()],
        'a day off' => fn () => ['start' => mbAt('10:00', '2026-10-10')->getTimestamp()],
        'a service of another salon' => fn () => ['services' => [['id' => Service::factory()->for(Salon::factory()->create())->create()->id]]],
        'a service nobody performs' => fn () => ['services' => [['id' => Service::factory()->for(test()->salon)->create()->id]]],
    ]);

    it('books with a walk-in\'s name and never makes them VIP or links them to an account', function () {
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt('10:00')->getTimestamp(),
            'client_name' => '  Doña   Rosa ', 'client_phone' => '987654321', 'is_vip' => true, 'user_id' => $this->customer->id, 'salon_id' => 999,
            'source' => 'client', 'deposit_cents' => 1, 'status' => 'completed', 'price_cents' => 1,
        ]);

        $booking = Booking::withoutGlobalScopes()->sole();
        $client = SalonClient::withoutGlobalScopes()->sole();

        expect($client->only(['is_vip', 'user_id', 'salon_id']))->toBe(['is_vip' => false, 'user_id' => null, 'salon_id' => $this->salon->id])
            ->and($booking->only(['is_vip', 'price_cents', 'deposit_cents']))->toBe(['is_vip' => false, 'price_cents' => 3500, 'deposit_cents' => 0])
            ->and($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->source)->toBe(BookingSource::Staff);
    });

    /*
    | Documents today's behaviour (reported as MEDIUM, a decision to review):
    | "same phone = same person" also matches the record of an ACCOUNT, and
    | the phone of an account is whatever its owner typed, never verified.
    | A visit the desk books for a walk-in who gives that phone lands in that
    | account's "Mis reservas", where it can be moved and cancelled.
    */
    it('keeps a visit booked at the desk by phone away from the account that declared that phone', function () {
        // Ana booked online once; her record carries the phone of her profile.
        $this->customer->forceFill(['phone' => '987111222'])->save();
        $this->actingAs($this->customer)->post(route('salons.booking.store', $this->salon), [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt('09:00', '2026-10-09')->getTimestamp(),
        ]);
        expect(mbClientOf($this->customer)->phone)->toBe('987111222');

        // Someone else calls the salon and gives that phone.
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => mbAt('15:00', '2026-10-09')->getTimestamp(),
            'client_name' => 'Doña Rosa', 'client_phone' => '987111222',
        ]);

        $desk = Booking::withoutGlobalScopes()->where('source', BookingSource::Staff)->sole();

        expect($desk->salon_client_id)->not->toBe(mbClientOf($this->customer)->id)
            ->and($desk->client()->withoutGlobalScopes()->sole()->name)->toBe('Doña Rosa');

        $this->actingAs($this->customer)->get(route('bookings.show', $desk->code))->assertNotFound();
        $this->actingAs($this->customer)->post(route('bookings.cancel', $desk->code))->assertNotFound();

        expect($desk->refresh()->status)->toBe(BookingStatus::Confirmed);
    });
});
