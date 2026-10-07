<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\FindAvailableSlots;
use App\Actions\Staff\AddTimeOff;
use App\Actions\Staff\RemoveStaff;
use App\Actions\Staff\ReplaceWeeklySchedule;
use App\Actions\Staff\SyncStaffServices;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Enums\ScheduleAccess;
use App\Enums\TimeOffReason;
use App\Exceptions\BookingException;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingSegment;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Support\CurrentSalon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Attempts to break the agenda, one invariant at a time. "Now" is Wednesday
| 2026-10-07 08:00 in Lima; the professional works Wed and Thu 09:00–18:00
| unless a test replaces the hours. Tests marked skip('BUG …') fail today
| and describe what the domain must guarantee.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'salon-breaker', 'timezone' => 'America/Lima']);
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 4000, 'active_minutes' => 30]);
    $this->color = Service::factory()->for($this->salon)->withIdle(20, 35, 25)->create(['name' => 'Tinte', 'price_cents' => 12000]);
    $this->staff->services()->attach([$this->cut->id, $this->color->id]);
    $this->client = SalonClient::factory()->for($this->salon)->create();
    $this->customer = User::factory()->create(['name' => 'Ana Cliente']);
});

function brkLocal(string $time, string $date = '2026-10-07'): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}", test()->salon->timezone);
}

/**
 * Replace the hours of a professional, bypassing the action.
 *
 * @param  list<array{0: int, 1: string, 2: string, 3?: ScheduleAccess}>  $ranges  [weekday, start, end, access]
 */
function brkHours(Staff $staff, array $ranges): void
{
    StaffSchedule::withoutGlobalScopes()->where('staff_id', $staff->id)->delete();

    foreach ($ranges as $range) {
        $schedule = new StaffSchedule;
        $schedule->forceFill([
            'salon_id' => $staff->salon_id,
            'staff_id' => $staff->id,
            'weekday' => $range[0],
            'start_time' => $range[1],
            'end_time' => $range[2],
            'access_level' => $range[3] ?? ScheduleAccess::Public,
        ])->save();
    }
}

function brkClient(bool $vip = false): SalonClient
{
    return SalonClient::factory()->for(test()->salon)->create(['is_vip' => $vip]);
}

/**
 * @param  array{client?: SalonClient, services?: list<Service>, staff?: Staff|null, source?: BookingSource}  $with
 */
function brkBook(string $time, array $with = [], string $date = '2026-10-07'): Booking
{
    $test = test();

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: ($with['client'] ?? $test->client)->refresh(),
        selections: array_map(fn (Service $service): array => ['service_id' => $service->id, 'variant_id' => null], $with['services'] ?? [$test->cut]),
        staff: array_key_exists('staff', $with) ? $with['staff'] : $test->staff,
        startsAt: brkLocal($time, $date),
        source: $with['source'] ?? BookingSource::Client,
    ));
}

/**
 * @param  list<Service>  $services
 * @return list<int> offered starts (Unix)
 */
function brkStarts(array $services, ?Staff $staff, ClientLevel $level = ClientLevel::Standard, string $date = '2026-10-07'): array
{
    return array_keys(app(FindAvailableSlots::class)->handle(
        test()->salon->refresh(),
        array_map(fn (Service $service): array => ['service_id' => $service->id, 'variant_id' => null], $services),
        $staff,
        brkLocal('12:00', $date),
        $level,
        CarbonImmutable::now(),
    ));
}

/**
 * @param  list<Service>  $services
 * @return list<string> offered starts as "HH:MM" on the salon clock
 */
function brkOffered(array $services, ?Staff $staff, ClientLevel $level = ClientLevel::Standard, string $date = '2026-10-07'): array
{
    return array_map(
        fn (int $start): string => CarbonImmutable::createFromTimestamp($start, test()->salon->timezone)->format('H:i'),
        brkStarts($services, $staff, $level, $date),
    );
}

/**
 * Pairs of busy stretches of one professional that overlap, among the
 * bookings that hold their slot right now. The invariant: always zero.
 */
function brkOverlaps(): int
{
    $now = now()->utc()->toDateTimeString();
    $holding = fn (string $alias): string => "({$alias}.status in ('confirmed', 'in_service') or ({$alias}.status = 'pending' and {$alias}.hold_expires_at > '{$now}'))";

    return (int) DB::scalar(
        'select count(*) from booking_segments a
           join booking_segments b on a.staff_id = b.staff_id and a.id < b.id and a.starts_at < b.ends_at and b.starts_at < a.ends_at
           join bookings ba on ba.id = a.booking_id
           join bookings bb on bb.id = b.booking_id
          where ba.id <> bb.id and '.$holding('ba').' and '.$holding('bb'),
    );
}

function brkAvailabilityUrl(array $query = []): string
{
    return route('salons.booking.availability', ['salon' => test()->salon->slug, 'date' => '2026-10-07', 'services' => [['id' => test()->cut->id]], ...$query]);
}

function brkPayload(string $time, array $overrides = [], string $date = '2026-10-07'): array
{
    return ['services' => [['id' => test()->cut->id]], 'staff' => test()->staff->id, 'start' => brkLocal($time, $date)->getTimestamp(), ...$overrides];
}

function brkStoreUrl(): string
{
    return route('salons.booking.store', test()->salon->slug);
}

function brkBookingCount(): int
{
    return Booking::withoutGlobalScopes()->count();
}

describe('partial overlaps', function () {
    it('refuses every start that overlaps 10:00–10:45 and accepts the ones that only touch it', function () {
        $long = Service::factory()->for($this->salon)->create(['active_minutes' => 45]);
        $this->staff->services()->attach($long->id);
        brkBook('10:00', ['services' => [$long]]);

        foreach (['09:45', '10:00', '10:15', '10:30'] as $time) {
            expect(fn () => brkBook($time, ['client' => brkClient()]))->toThrow(BookingException::class);
        }

        expect(brkBook('10:45', ['client' => brkClient()])->status)->toBe(BookingStatus::Confirmed)
            ->and(brkBook('09:30', ['client' => brkClient()])->status)->toBe(BookingStatus::Confirmed)
            ->and(brkOverlaps())->toBe(0)
            ->and(brkBookingCount())->toBe(3);
    });

    it('refuses a long visit that would swallow a short one', function () {
        $long = Service::factory()->for($this->salon)->create(['active_minutes' => 120]);
        $this->staff->services()->attach($long->id);
        brkBook('10:30');

        expect(fn () => brkBook('09:30', ['client' => brkClient(), 'services' => [$long]]))->toThrow(BookingException::class)
            ->and(brkOffered([$long], $this->staff))->not->toContain('09:00', '10:00', '10:30')->toContain('11:00');
    });
});

describe('closing time, lunch and manual blocks', function () {
    beforeEach(function () {
        $this->hour = Service::factory()->for($this->salon)->create(['active_minutes' => 60]);
        $this->staff->services()->attach($this->hour->id);
        brkHours($this->staff, [[3, '09:00', '13:00'], [3, '14:00', '18:00']]);
        StaffTimeOff::factory()->for($this->staff)->create(['starts_at' => brkLocal('15:00')->utc(), 'ends_at' => brkLocal('16:00')->utc()]);
    });

    it('neither offers nor accepts a visit that runs into lunch, a block or closing', function () {
        $offered = brkOffered([$this->hour], $this->staff);

        expect($offered)->toBe(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30', '11:45', '12:00', '14:00', '16:00', '16:15', '16:30', '16:45', '17:00']);

        foreach (['12:15', '12:45', '13:00', '13:30', '14:15', '15:00', '15:30', '17:15', '17:45', '18:00'] as $time) {
            expect(fn () => brkBook($time, ['client' => brkClient(), 'services' => [$this->hour]]))->toThrow(BookingException::class);
        }

        expect(brkBookingCount())->toBe(0);
    });

    it('refuses those starts to the team too, even though it books in any hours', function () {
        foreach (['12:30', '13:00', '15:30', '17:30'] as $time) {
            expect(fn () => brkBook($time, ['services' => [$this->hour], 'source' => BookingSource::Staff]))->toThrow(BookingException::class);
        }
    });

    it('accepts a visit that ends exactly at lunch, at the block and at closing', function () {
        foreach (['12:00', '14:00', '17:00'] as $time) {
            expect(brkBook($time, ['client' => brkClient(), 'services' => [$this->hour]])->status)->toBe(BookingStatus::Confirmed);
        }

        expect(brkOverlaps())->toBe(0);
    });

    it('sends "anyone" to the colleague when the professional\'s own longer time no longer fits before closing', function () {
        $colleague = Staff::factory()->for($this->salon)->working([3], '09:00', '18:00')->create();
        $colleague->services()->attach($this->cut->id);
        $this->staff->services()->updateExistingPivot($this->cut->id, ['active_minutes' => 90]);

        expect(brkOffered([$this->cut], $this->staff))->not->toContain('17:00')
            ->and(fn () => brkBook('17:00', ['client' => brkClient()]))->toThrow(BookingException::class)
            ->and(brkBook('17:00', ['client' => brkClient(), 'staff' => null])->staff_id)->toBe($colleague->id);
    });
});

describe('processing time of a color', function () {
    it('sells the gap to someone else and never lets the final stretch be stepped on', function () {
        $color = brkBook('10:00', ['services' => [$this->color]]);

        expect($color->segments()->count())->toBe(2)
            ->and($color->ends_at->equalTo(brkLocal('11:20')))->toBeTrue()
            ->and(brkOffered([$this->cut], $this->staff))->toContain('10:20', '11:20')->not->toContain('10:00', '10:15', '10:30', '10:45', '11:00', '11:15');

        foreach (['10:15', '10:30', '10:50', '11:00'] as $time) {
            expect(fn () => brkBook($time, ['client' => brkClient()]))->toThrow(BookingException::class);
        }

        // 10:20–10:50 sits in the gap; 11:20 starts when the color ends.
        brkBook('10:20', ['client' => brkClient()]);
        brkBook('11:20', ['client' => brkClient()]);

        expect(brkOverlaps())->toBe(0)
            ->and(brkOffered([$this->cut], $this->staff))->not->toContain('10:20', '11:20');
    });

    it('refuses a color whose final stretch would land on an existing booking', function () {
        brkBook('11:00');

        // 10:00 → finish 10:55–11:20, 10:10 is off the grid, 10:15 → 11:10–11:35.
        foreach (['10:00', '10:10', '10:15', '10:45'] as $time) {
            expect(fn () => brkBook($time, ['client' => brkClient(), 'services' => [$this->color]]))->toThrow(BookingException::class);
        }

        // 09:30 → finish 10:25–10:50: free.
        expect(brkBook('09:30', ['client' => brkClient(), 'services' => [$this->color]])->status)->toBe(BookingStatus::Confirmed)
            ->and(brkOverlaps())->toBe(0);
    });

    it('refuses a color whose final stretch would fall after closing or inside an absence', function () {
        StaffTimeOff::factory()->for($this->staff)->create(['starts_at' => brkLocal('13:00')->utc(), 'ends_at' => brkLocal('14:00')->utc()]);
        $offered = brkOffered([$this->color], $this->staff);

        // 12:15 → finish 13:10–13:35 (absence); 16:45 → finish 17:40–18:05 (closed).
        expect($offered)->not->toContain('12:15', '12:30', '12:45', '16:45', '17:00', '17:30')
            ->and(end($offered))->toBe('16:30')
            ->and(fn () => brkBook('12:15', ['services' => [$this->color]]))->toThrow(BookingException::class)
            ->and(fn () => brkBook('16:45', ['services' => [$this->color]]))->toThrow(BookingException::class);
    });

    it('books two colors and a cut in one visit with the right busy stretches', function () {
        $highlights = Service::factory()->for($this->salon)->withIdle(15, 30, 10)->create(['name' => 'Mechas']);
        $this->staff->services()->attach($highlights->id);

        $visit = brkBook('10:00', ['services' => [$this->color, $this->cut, $highlights]]);
        $stretches = $visit->segments()->get()->map(fn (BookingSegment $segment): string => $segment->starts_at->setTimezone('America/Lima')->format('H:i').'-'.$segment->ends_at->setTimezone('America/Lima')->format('H:i'));

        // 10:00 apply · 10:55 finish + cut + apply highlights until 12:05 · 12:35 finish.
        expect($stretches->all())->toBe(['10:00-10:20', '10:55-12:05', '12:35-12:45'])
            ->and($visit->ends_at->equalTo(brkLocal('12:45')))->toBeTrue()
            ->and(brkBook('10:20', ['client' => brkClient()])->status)->toBe(BookingStatus::Confirmed)
            ->and(brkBook('12:05', ['client' => brkClient()])->status)->toBe(BookingStatus::Confirmed)
            ->and(fn () => brkBook('12:15', ['client' => brkClient()]))->toThrow(BookingException::class)
            ->and(brkOverlaps())->toBe(0);
    });
});

describe('changing durations after people booked', function () {
    it('keeps existing visits as they were when the professional takes longer from now on', function () {
        app(CurrentSalon::class)->set($this->salon);
        $first = brkBook('10:00');
        $second = brkBook('10:30', ['client' => brkClient()]);

        app(SyncStaffServices::class)->handle($this->staff, [['service_id' => $this->cut->id, 'active_minutes' => 60, 'price_cents' => null]], false);

        expect($first->refresh()->ends_at->equalTo(brkLocal('10:30')))->toBeTrue()
            ->and($second->refresh()->starts_at->equalTo(brkLocal('10:30')))->toBeTrue()
            ->and(brkOverlaps())->toBe(0)
            // New visits take the hour: 09:00 fits before the first one, 09:15 does not.
            ->and(brkOffered([$this->cut], $this->staff))->toContain('09:00', '11:00')->not->toContain('09:15', '09:30', '10:00')
            ->and(brkBook('09:00', ['client' => brkClient()])->ends_at->equalTo(brkLocal('10:00')))->toBeTrue();
    });

    it('clamps an option with negative extra time to five minutes instead of a zero or negative visit', function () {
        $short = $this->cut->variants()->create(['label' => 'Solo retoque', 'price_cents' => 1000, 'extra_minutes' => -100, 'position' => 0]);

        $booking = app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->salon,
            client: $this->client,
            selections: [['service_id' => $this->cut->id, 'variant_id' => $short->id]],
            staff: $this->staff,
            startsAt: brkLocal('10:00'),
            source: BookingSource::Client,
        ));

        expect($booking->ends_at->equalTo(brkLocal('10:05')))->toBeTrue()->and($booking->segments()->count())->toBe(1);
    });

    it('warns about the next appointment when a visit already booked is made longer')
        ->todo('GAP: there is no action to lengthen or move an existing booking, so the rule "never overlap in silence" cannot be exercised yet. When it exists it must re-run AvailabilityCalculator::fits() with $ignoreBookingId under the professional\'s lock.');
});

describe('changes that leave future bookings stranded', function () {
    beforeEach(function () {
        app(CurrentSalon::class)->set($this->salon);
        $this->booked = brkBook('10:00');
    });

    it('still protects the time of a booking that new hours tried to leave outside', function () {
        // The change is refused (see the next test), so the hours stay as they were.
        expect(fn () => app(ReplaceWeeklySchedule::class)->handle($this->staff, [['weekday' => 3, 'start' => '14:00', 'end' => '18:00', 'access' => ScheduleAccess::Public]]))
            ->toThrow(BusinessRuleException::class);
        app(ReplaceWeeklySchedule::class)->handle($this->staff, [['weekday' => 3, 'start' => '09:00', 'end' => '18:00', 'access' => ScheduleAccess::Public]]);

        expect($this->booked->refresh()->status)->toBe(BookingStatus::Confirmed)
            ->and(fn () => brkBook('10:00', ['client' => brkClient()]))->toThrow(BookingException::class)
            ->and(brkOverlaps())->toBe(0);
    });

    it('says so when new working hours leave a future booking outside them', function () {
        expect(fn () => app(ReplaceWeeklySchedule::class)->handle($this->staff, [['weekday' => 3, 'start' => '14:00', 'end' => '18:00', 'access' => ScheduleAccess::Public]]))
            ->toThrow(BusinessRuleException::class);
    });

    it('says so when an absence is added over a future booking', function () {
        expect(fn () => app(AddTimeOff::class)->handle($this->staff, brkLocal('09:00')->utc(), brkLocal('12:00')->utc(), TimeOffReason::Vacation))
            ->toThrow(BusinessRuleException::class);
    });

    it('says so when a professional with future bookings is removed', function () {
        expect(fn () => app(RemoveStaff::class)->handle($this->staff))->toThrow(BusinessRuleException::class);
    });

    it('keeps the booking of a paused professional and offers them to nobody else', function () {
        $this->staff->forceFill(['is_active' => false])->save();

        expect($this->booked->refresh()->status)->toBe(BookingStatus::Confirmed)
            ->and(fn () => brkBook('11:00', ['client' => brkClient()]))->toThrow(BookingException::class)
            ->and(fn () => brkBook('11:00', ['client' => brkClient(), 'staff' => null]))->toThrow(BookingException::class)
            ->and(fn () => brkBook('11:00', ['source' => BookingSource::Staff]))->toThrow(BookingException::class)
            // The team can still close what was already booked.
            ->and(app(ChangeBookingStatus::class)->cancel($this->booked, null, byClient: false)->status)->toBe(BookingStatus::Cancelled);
    });

    it('refuses new bookings of a service switched off, and keeps the ones made', function () {
        $this->salon->forceFill(['deposit_percent' => 50])->save();
        $pending = brkBook('11:00', ['client' => brkClient()]);
        $this->cut->forceFill(['is_active' => false])->save();

        expect(fn () => brkBook('12:00', ['client' => brkClient()]))->toThrow(BookingException::class)
            ->and(app(ChangeBookingStatus::class)->confirm($pending, null)->status)->toBe(BookingStatus::Confirmed)
            ->and($this->booked->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('closes booking for a suspended salon over HTTP and by the action, keeping what was booked', function () {
        $this->salon->forceFill(['status' => 'suspended'])->save();

        $this->getJson(brkAvailabilityUrl())->assertNotFound();
        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('11:00'))->assertNotFound();

        expect(fn () => brkBook('11:00', ['client' => brkClient()]))->toThrow(BookingException::class)
            ->and(fn () => brkBook('11:00', ['source' => BookingSource::Staff]))->toThrow(BookingException::class)
            ->and($this->booked->refresh()->status)->toBe(BookingStatus::Confirmed)
            ->and(brkBookingCount())->toBe(1);
    });
});

describe('VIP and team-only hours over HTTP', function () {
    beforeEach(function () {
        brkHours($this->staff, [
            [3, '09:00', '12:00', ScheduleAccess::Public],
            [3, '12:00', '15:00', ScheduleAccess::Vip],
            [3, '15:00', '18:00', ScheduleAccess::StaffOnly],
            [4, '09:00', '18:00', ScheduleAccess::Vip],
            [5, '09:00', '18:00', ScheduleAccess::StaffOnly],
        ]);

        $this->vipUser = User::factory()->create();
        SalonClient::factory()->for($this->salon)->forUser($this->vipUser)->vip()->create();
    });

    it('sends a regular client, a guest and another salon\'s VIP zero VIP or team-only starts, whatever they add to the request', function () {
        $elsewhere = User::factory()->create();
        SalonClient::factory()->for(Salon::factory()->create())->forUser($elsewhere)->vip()->create();
        $forged = ['level' => 'vip', 'vip' => 1, 'is_vip' => true, 'source' => 'staff', 'client_level' => 'team', 'access_level' => 'vip'];

        foreach ([null, $this->customer, $elsewhere] as $user) {
            foreach ([[], $forged] as $extra) {
                $request = $user === null ? $this : $this->actingAs($user);
                $response = $request->getJson(brkAvailabilityUrl($extra))->assertOk();
                $times = collect($response->json('slots'))->pluck('time');

                expect($times->first())->toBe('09:00')
                    ->and($times->last())->toBe('11:30')
                    ->and($times->filter(fn (string $time) => $time >= '11:45')->all())->toBe([])
                    ->and(array_keys($response->json('slots.0')))->toBe(['start', 'time'])
                    ->and($response->getContent())->not->toContain('vip', 'staff_only');

                // Days with only VIP or team-only hours: nothing at all.
                $request->getJson(brkAvailabilityUrl([...$extra, 'date' => '2026-10-08']))->assertOk()->assertJsonCount(0, 'slots');
                $request->getJson(brkAvailabilityUrl([...$extra, 'date' => '2026-10-09']))->assertOk()->assertJsonCount(0, 'slots');
            }

            app('auth')->forgetGuards();
        }
    });

    it('sends a VIP the public and VIP starts and zero team-only ones', function () {
        $times = collect($this->actingAs($this->vipUser)->getJson(brkAvailabilityUrl())->assertOk()->json('slots'))->pluck('time');

        expect($times->first())->toBe('09:00')
            ->and($times)->toContain('11:45', '12:00')
            ->and($times->last())->toBe('14:30')
            ->and($times->filter(fn (string $time) => $time >= '14:45')->all())->toBe([]);

        $this->actingAs($this->vipUser)->getJson(brkAvailabilityUrl(['date' => '2026-10-08']))->assertOk()->assertJsonPath('slots.0.time', '09:00');
        $this->actingAs($this->vipUser)->getJson(brkAvailabilityUrl(['date' => '2026-10-09']))->assertOk()->assertJsonCount(0, 'slots');
    });

    it('refuses a regular client who POSTs a VIP or team-only start by hand', function () {
        $forged = ['level' => 'vip', 'is_vip' => true, 'source' => 'staff'];

        foreach (['11:45', '12:00', '14:30', '15:00', '17:30'] as $time) {
            $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload($time, $forged));
        }

        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('10:00', $forged, '2026-10-08'));

        expect(brkBookingCount())->toBe(0);

        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('11:30', $forged));

        $made = Booking::withoutGlobalScopes()->sole();
        expect($made->is_vip)->toBeFalse()->and($made->source)->toBe(BookingSource::Client);
    });

    it('lets a VIP book VIP hours but not team-only ones', function () {
        $this->actingAs($this->vipUser)->post(brkStoreUrl(), brkPayload('14:45'));
        $this->actingAs($this->vipUser)->post(brkStoreUrl(), brkPayload('15:00'));
        expect(brkBookingCount())->toBe(0);

        $this->actingAs($this->vipUser)->post(brkStoreUrl(), brkPayload('14:30'));
        expect(Booking::withoutGlobalScopes()->sole()->is_vip)->toBeTrue();
    });

    it('stops offering VIP hours the moment the salon removes the VIP mark, keeping the booking made', function () {
        $this->actingAs($this->vipUser)->post(brkStoreUrl(), brkPayload('13:00'));
        SalonClient::withoutGlobalScopes()->where('user_id', $this->vipUser->id)->update(['is_vip' => false]);

        $times = collect($this->actingAs($this->vipUser)->getJson(brkAvailabilityUrl())->json('slots'))->pluck('time');
        $this->actingAs($this->vipUser)->post(brkStoreUrl(), brkPayload('14:00'));

        expect($times->last())->toBe('11:30')
            ->and(brkBookingCount())->toBe(1)
            ->and(Booking::withoutGlobalScopes()->sole()->status)->toBe(BookingStatus::Confirmed);
    });

    it('marks days with only VIP or team-only hours as closed for a regular client', function () {
        $closed = fn (Assert $page): array => collect($page->toArray()['props']['days'])->take(3)->pluck('closed', 'date')->all();

        $this->get(route('salons.booking.create', $this->salon->slug))->assertInertia(fn (Assert $page) => expect($closed($page))
            ->toBe(['2026-10-07' => false, '2026-10-08' => true, '2026-10-09' => true]) && $page);

        $this->actingAs($this->vipUser)->get(route('salons.booking.create', $this->salon->slug))->assertInertia(fn (Assert $page) => expect($closed($page))
            ->toBe(['2026-10-07' => false, '2026-10-08' => false, '2026-10-09' => true]) && $page);
    });

    it('lets a regular color wait over VIP hours without ever working in them', function () {
        brkHours($this->staff, [[3, '09:00', '12:00'], [3, '12:00', '13:00', ScheduleAccess::Vip], [3, '13:00', '18:00']]);
        $long = Service::factory()->for($this->salon)->withIdle(20, 60, 25)->create();
        $this->staff->services()->attach($long->id);

        // 11:40 apply · 12:00–13:00 processing (VIP hour) · 13:00 finish.
        $offered = brkOffered([$long], $this->staff);

        expect($offered)->not->toContain('11:45', '12:00', '12:30')
            ->and(fn () => brkBook('11:45', ['services' => [$long]]))->toThrow(BookingException::class);

        foreach (brkStarts([$long], $this->staff) as $start) {
            $local = CarbonImmutable::createFromTimestamp($start, 'America/Lima');
            $finish = $local->addMinutes(80);

            // Neither busy stretch may touch 12:00–13:00.
            expect($local->addMinutes(20)->format('H:i') <= '12:00' || $local->format('H:i') >= '13:00')->toBeTrue()
                ->and($finish->addMinutes(25)->format('H:i') <= '12:00' || $finish->format('H:i') >= '13:00')->toBeTrue();
        }
    });
});

describe('time zones', function () {
    it('stores an evening visit in Lima as the next day in UTC and shows it on the salon\'s day', function () {
        brkHours($this->staff, [[3, '09:00', '23:55']]);
        $this->salon->forceFill(['max_days_ahead' => 60])->save();
        SalonClient::factory()->for($this->salon)->forUser($this->customer)->create();

        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('20:00'));
        $booking = Booking::withoutGlobalScopes()->sole();

        expect(DB::table('bookings')->value('starts_at'))->toBe('2026-10-08 01:00:00')
            ->and(DB::table('booking_segments')->value('ends_at'))->toBe('2026-10-08 01:30:00');

        $this->actingAs($this->customer)->get(route('bookings.show', $booking->code))
            ->assertInertia(fn (Assert $page) => $page->where('booking.date', '2026-10-07')->where('booking.time', '20:00')->where('booking.endTime', '20:30'));

        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-07']))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 1)->where('bookings.0.time', '20:00'));
        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-08']))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 0));
    });

    it('offers only starts of the requested salon day, and no visit that would cross midnight', function () {
        brkHours($this->staff, [[3, '00:00', '23:55'], [4, '00:00', '23:55']]);

        $slots = collect($this->getJson(brkAvailabilityUrl(['date' => '2026-10-07']))->assertOk()->json('slots'));
        $days = $slots->map(fn (array $slot): string => CarbonImmutable::createFromTimestamp($slot['start'], 'America/Lima')->toDateString())->unique()->values();

        expect($days->all())->toBe(['2026-10-07'])
            ->and($slots->first()['time'])->toBe('09:00')
            ->and($slots->last()['time'])->toBe('23:15');

        // Thursday from its first minute: 00:00 in Lima is 05:00 UTC.
        $thursday = collect($this->getJson(brkAvailabilityUrl(['date' => '2026-10-08']))->assertOk()->json('slots'));
        expect($thursday->first())->toBe(['start' => CarbonImmutable::parse('2026-10-08 05:00', 'UTC')->getTimestamp(), 'time' => '00:00'])
            ->and($thursday->last()['time'])->toBe('23:15');

        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('23:30'));
        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('23:45'));
        expect(brkBookingCount())->toBe(0);
    });

    it('reads the date and writes the start on the salon clock wherever the salon is', function (string $zone, string $now, string $date, string $firstUtc) {
        $this->travelTo(CarbonImmutable::parse($now, 'UTC'));
        $this->salon->forceFill(['timezone' => $zone])->save();
        brkHours($this->staff, array_map(fn (int $weekday): array => [$weekday, '09:00', '18:00'], range(1, 7)));

        $slots = collect($this->getJson(brkAvailabilityUrl(['date' => $date]))->assertOk()->json('slots'));

        expect($slots->first()['time'])->toBe('09:00')
            ->and(CarbonImmutable::createFromTimestampUTC($slots->first()['start'])->format('Y-m-d H:i'))->toBe($firstUtc)
            ->and($slots->last()['time'])->toBe('17:30');

        $this->actingAs($this->customer)->post(brkStoreUrl(), ['services' => [['id' => $this->cut->id]], 'staff' => $this->staff->id, 'start' => $slots->first()['start']]);

        expect(DB::table('bookings')->value('starts_at'))->toBe($firstUtc.':00');
    })->with([
        'Tokyo: tomorrow there is still today in UTC' => ['Asia/Tokyo', '2026-10-07 13:00', '2026-10-08', '2026-10-08 00:00'],
        'Kiritimati (UTC+14): its today started yesterday in UTC' => ['Pacific/Kiritimati', '2026-10-07 13:00', '2026-10-08', '2026-10-07 19:00'],
        'Niue (UTC-11): its today ends tomorrow in UTC' => ['Pacific/Niue', '2026-10-07 13:00', '2026-10-07', '2026-10-07 20:00'],
        'Lima' => ['America/Lima', '2026-10-07 13:00', '2026-10-07', '2026-10-07 14:00'],
    ]);

    it('offers and books real hours on the day Santiago loses one', function () {
        $this->salon->forceFill(['timezone' => 'America/Santiago', 'slot_step_minutes' => 60])->save();
        $this->travelTo(CarbonImmutable::parse('2026-09-05 12:00', 'America/Santiago'));
        brkHours($this->staff, [[7, '00:00', '06:00']]);
        $hour = Service::factory()->for($this->salon)->create(['active_minutes' => 60]);
        $this->staff->services()->attach($hour->id);

        expect(brkOffered([$hour], $this->staff, ClientLevel::Standard, '2026-09-06'))->toBe(['01:00', '02:00', '03:00', '04:00', '05:00']);

        foreach (brkStarts([$hour], $this->staff, ClientLevel::Standard, '2026-09-06') as $start) {
            app(CreateBooking::class)->handle(new NewBooking($this->salon->refresh(), brkClient(), [['service_id' => $hour->id, 'variant_id' => null]], $this->staff, CarbonImmutable::createFromTimestampUTC($start), BookingSource::Client));
        }

        expect(brkBookingCount())->toBe(5)
            ->and(brkOverlaps())->toBe(0)
            ->and(brkOffered([$hour], $this->staff, ClientLevel::Standard, '2026-09-06'))->toBe([]);
    });

    it('offers no clock time twice on the day Santiago repeats an hour, and the next day starts at its real midnight', function () {
        $this->salon->forceFill(['timezone' => 'America/Santiago'])->save();
        $this->travelTo(CarbonImmutable::parse('2027-04-02 12:00', 'America/Santiago'));
        brkHours($this->staff, [[6, '20:00', '23:55'], [7, '00:00', '02:00']]);

        $saturday = brkOffered([$this->cut], $this->staff, ClientLevel::Standard, '2027-04-03');
        $sunday = brkStarts([$this->cut], $this->staff, ClientLevel::Standard, '2027-04-04');

        expect($saturday)->toBe(array_values(array_unique($saturday)))
            ->and(end($saturday))->toBe('23:15')
            // Sunday 00:00 is already winter time (UTC-4).
            ->and(CarbonImmutable::createFromTimestampUTC($sunday[0])->format('Y-m-d H:i'))->toBe('2027-04-04 04:00');
    });

    it('survives an absence typed inside the hour that does not exist that night', function () {
        $this->salon->forceFill(['timezone' => 'America/Santiago'])->save();
        $this->travelTo(CarbonImmutable::parse('2026-09-05 12:00', 'America/Santiago'));
        brkHours($this->staff, [[7, '00:00', '06:00']]);

        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), [
            'starts_on' => '2026-09-06', 'ends_on' => '2026-09-06', 'all_day' => false,
            'start_time' => '00:30', 'end_time' => '01:15', 'reason' => TimeOffReason::Vacation->value,
        ]);

        // The picker of that day must keep answering, and no absence may end before it starts.
        $this->getJson(brkAvailabilityUrl(['date' => '2026-09-06']))->assertOk();
        expect(StaffTimeOff::withoutGlobalScopes()->whereColumn('ends_at', '<=', 'starts_at')->count())->toBe(0);
    });
});

describe('notice, horizon and the past', function () {
    it('offers a start exactly at the minimum notice and drops it one second later', function () {
        expect(brkOffered([$this->cut], $this->staff)[0])->toBe('09:00');

        $this->travel(1)->seconds();

        expect(brkOffered([$this->cut], $this->staff)[0])->toBe('09:15')
            ->and(fn () => brkBook('09:00'))->toThrow(BookingException::class)
            ->and(brkBook('09:15')->status)->toBe(BookingStatus::Confirmed);
    });

    it('accepts the last start of the last day of the horizon and nothing after it', function () {
        $this->salon->forceFill(['max_days_ahead' => 7])->save();
        brkHours($this->staff, array_map(fn (int $weekday): array => [$weekday, '09:00', '18:00'], range(1, 7)));

        expect(brkOffered([$this->cut], $this->staff, ClientLevel::Standard, '2026-10-14'))->toContain('09:00', '17:30')
            ->and(brkOffered([$this->cut], $this->staff, ClientLevel::Vip, '2026-10-15'))->toBe([])
            ->and(fn () => brkBook('09:00', ['client' => brkClient(true)], '2026-10-15'))->toThrow(BookingException::class)
            ->and(brkBook('17:30', [], '2026-10-14')->status)->toBe(BookingStatus::Confirmed);

        $this->getJson(brkAvailabilityUrl(['date' => '2026-10-15']))->assertOk()->assertJsonCount(0, 'slots');
        $this->getJson(brkAvailabilityUrl(['date' => '2026-10-06']))->assertOk()->assertJsonCount(0, 'slots');
        $this->getJson(brkAvailabilityUrl(['date' => '1999-01-01']))->assertOk()->assertJsonCount(0, 'slots');
    });

    it('refuses over HTTP starts in the past, inside the notice, off the grid or with stray seconds', function () {
        $this->travelTo(brkLocal('10:05'));
        $at = fn (string $time, string $date = '2026-10-07'): int => brkLocal($time, $date)->getTimestamp();

        $starts = [
            'yesterday' => $at('10:00', '2026-10-06'),
            'an hour ago' => $at('09:00'),
            'right now' => $at('10:05'),
            'inside the notice' => $at('11:00'),
            'off the grid' => $at('12:07'),
            'stray seconds' => $at('12:00') + 30,
            'next year' => $at('10:00', '2027-10-06'),
            'zero' => 0,
            'negative' => -3600,
        ];

        foreach ($starts as $start) {
            $this->actingAs($this->customer)->post(brkStoreUrl(), ['services' => [['id' => $this->cut->id]], 'staff' => $this->staff->id, 'start' => $start]);
        }

        expect(brkBookingCount())->toBe(0);

        $this->actingAs($this->customer)->post(brkStoreUrl(), brkPayload('11:15'));
        expect(brkBookingCount())->toBe(1);
    });

    it('lets only the team book for right now, and never further back than one grid step', function () {
        $this->travelTo(brkLocal('10:07'));

        expect(fn () => brkBook('10:00'))->toThrow(BookingException::class)
            ->and(fn () => brkBook('09:45', ['source' => BookingSource::Staff]))->toThrow(BookingException::class)
            ->and(brkBook('10:00', ['source' => BookingSource::Staff])->status)->toBe(BookingStatus::Confirmed)
            // Far beyond the clients' horizon (a Wednesday ten weeks ahead).
            ->and(brkBook('10:00', ['source' => BookingSource::Staff], '2026-12-16')->status)->toBe(BookingStatus::Confirmed)
            ->and(fn () => brkBook('10:00', ['client' => brkClient(true)], '2026-12-16'))->toThrow(BookingException::class);
    });
});

describe('unpaid holds', function () {
    beforeEach(function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
    });

    it('frees the slot at the exact instant the hold runs out, with no scheduled task', function () {
        $pending = brkBook('10:00');
        $other = brkClient();

        $this->travelTo($pending->hold_expires_at->subSecond());
        expect(brkOffered([$this->cut], $this->staff))->not->toContain('10:00')
            ->and(fn () => brkBook('10:00', ['client' => $other]))->toThrow(BookingException::class);

        $this->travelTo($pending->hold_expires_at);
        expect(brkOffered([$this->cut], $this->staff))->toContain('10:00')
            ->and($pending->refresh()->status)->toBe(BookingStatus::Pending)
            ->and(brkBook('10:00', ['client' => $other])->status)->toBe(BookingStatus::Pending)
            ->and(brkOverlaps())->toBe(0);
    });

    it('never revives an expired hold on top of whoever took the slot, by any route', function () {
        $late = brkBook('10:00', ['services' => [$this->color]]);
        $this->travelTo($late->hold_expires_at);

        // Someone takes only the final stretch of the expired color.
        $taker = brkBook('11:00', ['client' => brkClient()]);
        app(ChangeBookingStatus::class)->confirm($taker, null);

        expect(fn () => app(ChangeBookingStatus::class)->confirm($late, null))->toThrow(BookingException::class)
            ->and($late->refresh()->status)->toBe(BookingStatus::Pending)
            ->and(brkOverlaps())->toBe(0);

        $this->artisan('bookings:expire-holds')->assertSuccessful();

        expect($late->refresh()->status)->toBe(BookingStatus::Expired)
            ->and(fn () => app(ChangeBookingStatus::class)->confirm($late, null))->toThrow(BookingException::class)
            ->and(brkOverlaps())->toBe(0);
    });

    it('confirms an expired hold once the slot is free again, and not on a day that became a holiday or an absence', function () {
        $late = brkBook('10:00');
        $this->travelTo($late->hold_expires_at);
        $taker = brkBook('10:00', ['client' => brkClient()]);

        expect(fn () => app(ChangeBookingStatus::class)->confirm($late, null))->toThrow(BookingException::class);

        app(ChangeBookingStatus::class)->cancel($taker, null, byClient: false);
        $absence = StaffTimeOff::factory()->for($this->staff)->create(['starts_at' => brkLocal('09:00')->utc(), 'ends_at' => brkLocal('11:00')->utc()]);

        expect(fn () => app(ChangeBookingStatus::class)->confirm($late, null))->toThrow(BookingException::class);

        $absence->delete();

        expect(app(ChangeBookingStatus::class)->confirm($late, null)->status)->toBe(BookingStatus::Confirmed)
            ->and(brkOverlaps())->toBe(0);
    });
});

describe('state machine', function () {
    it('allows exactly the transitions of the diagram, and leaves no trace of a refused one', function (BookingStatus $from, string $action, BookingStatus $to) {
        $booking = brkBook('10:00');
        DB::table('bookings')->where('id', $booking->id)->update([
            'status' => $from->value,
            'hold_expires_at' => $from === BookingStatus::Pending ? now()->addHours(5) : null,
        ]);
        // After the start, so "no-show" is only refused for the status.
        $this->travelTo(brkLocal('10:05'));

        $status = app(ChangeBookingStatus::class);
        $run = fn () => match ($action) {
            'confirm' => $status->confirm($booking, null),
            'start' => $status->start($booking, null),
            'complete' => $status->complete($booking, null),
            'cancel' => $status->cancel($booking, null, byClient: false),
            'noShow' => $status->markNoShow($booking, null),
        };

        if ($from->canBecome($to)) {
            expect($run()->status)->toBe($to)->and($booking->events()->count())->toBe(2);

            return;
        }

        expect($run)->toThrow(BookingException::class)
            ->and($booking->refresh()->status)->toBe($from)
            ->and($booking->events()->count())->toBe(1)
            ->and($this->client->refresh()->no_show_count)->toBe(0);
    })->with(BookingStatus::cases())->with([
        ['confirm', BookingStatus::Confirmed],
        ['start', BookingStatus::InService],
        ['complete', BookingStatus::Completed],
        ['cancel', BookingStatus::Cancelled],
        ['noShow', BookingStatus::NoShow],
    ]);

    it('holds or frees the slot according to the status', function (BookingStatus $status, bool $holds) {
        $booking = brkBook('10:00');
        DB::table('bookings')->where('id', $booking->id)->update([
            'status' => $status->value,
            'hold_expires_at' => $status === BookingStatus::Pending ? now()->addMinutes(30) : null,
        ]);

        expect(in_array('10:00', brkOffered([$this->cut], $this->staff), true))->toBe(! $holds);

        if ($holds) {
            expect(fn () => brkBook('10:00', ['client' => brkClient()]))->toThrow(BookingException::class);
        } else {
            expect(brkBook('10:00', ['client' => brkClient()])->status)->toBe(BookingStatus::Confirmed);
        }

        expect(brkOverlaps())->toBe(0);
    })->with([
        'pending' => [BookingStatus::Pending, true],
        'confirmed' => [BookingStatus::Confirmed, true],
        'in service' => [BookingStatus::InService, true],
        'completed' => [BookingStatus::Completed, false],
        'cancelled' => [BookingStatus::Cancelled, false],
        'no-show' => [BookingStatus::NoShow, false],
        'expired' => [BookingStatus::Expired, false],
    ]);

    it('refuses a second cancel or no-show and counts the no-show once', function () {
        $status = app(ChangeBookingStatus::class);
        $cancelled = brkBook('10:00');
        $missed = brkBook('11:00');
        $status->cancel($cancelled, null, byClient: true);

        expect(fn () => $status->cancel($cancelled, null, byClient: true))->toThrow(BookingException::class)
            ->and(fn () => $status->cancel($cancelled, null, byClient: false))->toThrow(BookingException::class)
            ->and(fn () => $status->markNoShow($missed, null))->toThrow(BookingException::class);

        $this->travelTo(brkLocal('11:00'));
        $status->markNoShow($missed, null);

        expect(fn () => $status->markNoShow($missed, null))->toThrow(BookingException::class)
            ->and(fn () => $status->cancel($missed, null, byClient: false))->toThrow(BookingException::class)
            ->and($this->client->refresh()->no_show_count)->toBe(1);
    });

    it('does not put "in service" a visit of another day, which could then be neither cancelled nor marked as a no-show', function () {
        $tomorrow = brkBook('10:00', [], '2026-10-08');

        expect(fn () => app(ChangeBookingStatus::class)->start($tomorrow, null))->toThrow(BookingException::class)
            ->and($tomorrow->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('does not let the client cancel from the start time on', function () {
        $booking = brkBook('10:00');

        $this->travelTo(brkLocal('10:00'));
        expect(fn () => app(ChangeBookingStatus::class)->cancel($booking, null, byClient: true))->toThrow(BookingException::class)
            ->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('decides whether a paid deposit comes back', function (string $cancelAt, bool $byClient, ?bool $refundable) {
        $this->salon->forceFill(['deposit_percent' => 50, 'cancel_window_hours' => 24])->save();
        // Thursday 10:00, booked on Wednesday 08:00.
        $booking = brkBook('10:00', [], '2026-10-08');
        app(ChangeBookingStatus::class)->confirm($booking, null);

        $this->travelTo(brkLocal(...explode(' ', $cancelAt)));

        expect(app(ChangeBookingStatus::class)->cancel($booking, null, $byClient)->deposit_refundable)->toBe($refundable);
    })->with([
        'client, more than 24 h before' => ['09:59 2026-10-07', true, true],
        'client, exactly 24 h before' => ['10:00 2026-10-07', true, true],
        'client, one minute inside the window' => ['10:01 2026-10-07', true, false],
        'client, minutes before' => ['09:50 2026-10-08', true, false],
        'the salon, minutes before' => ['09:50 2026-10-08', false, true],
        'the salon, after the start' => ['10:30 2026-10-08', false, true],
    ]);

    it('leaves the refund undecided for an unpaid hold and forfeits it on a no-show', function () {
        $this->salon->forceFill(['deposit_percent' => 50])->save();
        $unpaid = brkBook('10:00');
        $paid = brkBook('11:00', ['client' => brkClient()]);
        app(ChangeBookingStatus::class)->confirm($paid, null);

        $this->travelTo(brkLocal('11:10'));

        expect(app(ChangeBookingStatus::class)->cancel($unpaid, null, byClient: false)->deposit_refundable)->toBeNull()
            ->and(app(ChangeBookingStatus::class)->markNoShow($paid, null)->deposit_refundable)->toBeFalse();
    });
});

describe('holidays and absences', function () {
    it('blocks exactly the salon days of an all-day absence, not the evening before nor the morning after', function () {
        brkHours($this->staff, array_map(fn (int $weekday): array => [$weekday, '06:00', '23:55'], range(1, 7)));

        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), [
            'starts_on' => '2026-10-08', 'ends_on' => '2026-10-08', 'all_day' => true, 'reason' => TimeOffReason::Vacation->value,
        ]);

        $absence = StaffTimeOff::withoutGlobalScopes()->sole();

        // Thursday in Lima is 05:00 UTC to 05:00 UTC.
        expect($absence->starts_at->toDateTimeString())->toBe('2026-10-08 05:00:00')
            ->and($absence->ends_at->toDateTimeString())->toBe('2026-10-09 05:00:00')
            ->and(brkOffered([$this->cut], $this->staff, ClientLevel::Standard, '2026-10-07'))->toContain('23:15')
            ->and(brkOffered([$this->cut], $this->staff, ClientLevel::Team, '2026-10-08'))->toBe([])
            ->and(brkOffered([$this->cut], $this->staff, ClientLevel::Standard, '2026-10-09'))->toContain('06:00');
    });

    it('closes a holiday for clients, VIPs and the team, and only that salon day', function () {
        brkHours($this->staff, array_map(fn (int $weekday): array => [$weekday, '06:00', '23:55'], range(1, 7)));

        $this->actingAs($this->owner)->post(route('holidays.store'), ['date' => '2026-10-08', 'name' => 'Combate de Angamos']);

        foreach (ClientLevel::cases() as $level) {
            expect(brkOffered([$this->cut], $this->staff, $level, '2026-10-08'))->toBe([]);
        }

        expect(fn () => brkBook('10:00', ['source' => BookingSource::Staff], '2026-10-08'))->toThrow(BookingException::class)
            ->and(brkOffered([$this->cut], $this->staff, ClientLevel::Standard, '2026-10-07'))->toContain('23:15')
            ->and(brkOffered([$this->cut], $this->staff, ClientLevel::Standard, '2026-10-09'))->toContain('06:00');
    });
});

describe('the picker and the save never disagree (seeded fuzz)', function () {
    it('books every offered start and refuses every other one, on generated days', function () {
        mt_srand(4242);
        $create = app(CreateBooking::class);
        $problems = [];
        $checked = 0;

        foreach (range(1, 6) as $case) {
            $this->salon->forceFill([
                'slot_step_minutes' => [5, 15, 20, 60][mt_rand(0, 3)],
                'min_lead_minutes' => [0, 60, 150][mt_rand(0, 2)],
                'deposit_percent' => [0, 30][mt_rand(0, 1)],
            ])->save();

            // Hours of the day: up to four ranges of mixed access, some touching.
            $ranges = [];
            $cursor = 8 * 60 + mt_rand(0, 12) * 5;

            foreach (range(1, mt_rand(2, 4)) as $ignored) {
                $from = $cursor + (mt_rand(0, 1) === 0 ? 0 : mt_rand(1, 12) * 5);
                $to = min($from + mt_rand(6, 48) * 5, 23 * 60 + 55);

                if ($to - $from < 15) {
                    break;
                }

                $ranges[] = [3, sprintf('%02d:%02d', intdiv($from, 60), $from % 60), sprintf('%02d:%02d', intdiv($to, 60), $to % 60), ScheduleAccess::cases()[mt_rand(0, 2)]];
                $cursor = $to;
            }

            brkHours($this->staff, $ranges);

            $services = [
                Service::factory()->for($this->salon)->create(['active_minutes' => mt_rand(1, 14) * 5]),
                Service::factory()->for($this->salon)->withIdle(mt_rand(1, 6) * 5, mt_rand(2, 10) * 5, mt_rand(0, 6) * 5)->create(),
            ];
            $this->staff->services()->attach(array_map(fn (Service $service): int => $service->id, $services));

            // What is already in the agenda: an absence and a few visits the team wrote down.
            $blockFrom = brkLocal('08:00')->addMinutes(mt_rand(0, 600));
            StaffTimeOff::factory()->for($this->staff)->create(['starts_at' => $blockFrom->utc(), 'ends_at' => $blockFrom->addMinutes(mt_rand(5, 90))->utc()]);

            foreach (range(1, mt_rand(2, 5)) as $ignored) {
                $service = $services[mt_rand(0, 1)];
                $free = brkStarts([$service], $this->staff, ClientLevel::Team);

                if ($free !== []) {
                    $create->handle(new NewBooking($this->salon->refresh(), brkClient(), [['service_id' => $service->id, 'variant_id' => null]], $this->staff, CarbonImmutable::createFromTimestampUTC($free[mt_rand(0, count($free) - 1)]), BookingSource::Staff));
                }
            }

            foreach ([[ClientLevel::Standard, BookingSource::Client], [ClientLevel::Vip, BookingSource::Client], [ClientLevel::Team, BookingSource::Staff]] as [$level, $source]) {
                $client = brkClient($level === ClientLevel::Vip);

                foreach ($services as $service) {
                    $offered = brkStarts([$service], $this->staff, $level);
                    $tries = $offered === [] ? [] : array_map(fn (int $index): int => $offered[$index], (array) array_rand($offered, min(12, count($offered))));

                    // Starts next to the offered ones, and anywhere in the day.
                    foreach ($tries as $start) {
                        $tries[] = $start - 300;
                        $tries[] = $start + 300;
                        $tries[] = $start + 60;
                    }

                    foreach (range(1, 25) as $ignored) {
                        $tries[] = brkLocal('00:00')->getTimestamp() + mt_rand(0, 287) * 300;
                    }

                    foreach (array_unique($tries) as $start) {
                        DB::beginTransaction();

                        try {
                            $create->handle(new NewBooking($this->salon, $client, [['service_id' => $service->id, 'variant_id' => null]], $this->staff, CarbonImmutable::createFromTimestampUTC($start), $source));
                            $booked = true;
                        } catch (BookingException) {
                            $booked = false;
                        }

                        $clean = $booked ? brkOverlaps() === 0 : true;
                        DB::rollBack();
                        $checked++;

                        if ($booked !== in_array($start, $offered, true) || ! $clean) {
                            $at = CarbonImmutable::createFromTimestamp($start, 'America/Lima')->format('H:i:s');
                            $problems[] = "case {$case} {$level->value} service {$service->id} at {$at}: offered=".var_export(in_array($start, $offered, true), true).' booked='.var_export($booked, true).' clean='.var_export($clean, true);
                        }
                    }
                }
            }

            // Next case starts from an empty agenda.
            Booking::withoutGlobalScopes()->delete();
            StaffTimeOff::withoutGlobalScopes()->delete();
            $this->staff->services()->detach(array_map(fn (Service $service): int => $service->id, $services));
        }

        expect(array_slice($problems, 0, 10))->toBe([])->and($checked)->toBeGreaterThan(500);
    });

    it('fills two agendas at random until nothing fits, cancels, refills, and never overlaps two visits', function () {
        mt_srand(777);
        $create = app(CreateBooking::class);
        $status = app(ChangeBookingStatus::class);
        $second = Staff::factory()->for($this->salon)->create();
        $quick = Service::factory()->for($this->salon)->create(['active_minutes' => 10]);
        $services = [$this->cut, $this->color, $quick];

        foreach ([$this->staff, $second] as $staff) {
            brkHours($staff, [[3, '09:00', '13:00'], [3, '14:00', '18:00']]);
            $staff->services()->syncWithoutDetaching(array_map(fn (Service $service): int => $service->id, $services));
        }

        StaffTimeOff::factory()->for($second)->create(['starts_at' => brkLocal('15:00')->utc(), 'ends_at' => brkLocal('16:10')->utc()]);

        $fill = function () use ($create, $services, $second): int {
            $made = 0;

            for ($round = 0; $round < 400; $round++) {
                $service = $services[mt_rand(0, 2)];
                $staff = [$this->staff, $second, null][mt_rand(0, 2)];
                $free = brkStarts([$service], $staff, ClientLevel::Team);

                if ($free === []) {
                    // Done when not even the shortest service fits anywhere.
                    if (brkStarts([$services[2]], null, ClientLevel::Team) === []) {
                        break;
                    }

                    continue;
                }

                $create->handle(new NewBooking($this->salon, brkClient(), [['service_id' => $service->id, 'variant_id' => null]], $staff, CarbonImmutable::createFromTimestampUTC($free[mt_rand(0, count($free) - 1)]), BookingSource::Staff));
                $made++;
            }

            return $made;
        };

        $first = $fill();
        expect($first)->toBeGreaterThan(20)->and(brkOverlaps())->toBe(0);

        foreach (Booking::withoutGlobalScopes()->inRandomOrder()->limit(8)->get() as $booking) {
            $status->cancel($booking, null, byClient: false);
        }

        expect($fill())->toBeGreaterThan(0)->and(brkOverlaps())->toBe(0);

        // Every busy stretch lies inside working hours and outside the absence.
        $outside = BookingSegment::query()
            ->whereIn('booking_id', Booking::withoutGlobalScopes()->holdingSlot()->select('id'))
            ->get()
            ->filter(function (BookingSegment $segment) use ($second): bool {
                $from = $segment->starts_at->setTimezone('America/Lima')->format('H:i');
                $to = $segment->ends_at->setTimezone('America/Lima')->format('H:i');
                $inHours = ($from >= '09:00' && $to <= '13:00') || ($from >= '14:00' && $to <= '18:00');
                $inAbsence = $segment->staff_id === $second->id && $from < '16:10' && $to > '15:00';

                return ! $inHours || $inAbsence;
            });

        expect($outside->count())->toBe(0);
    });

    it('accepts over HTTP the starts the availability endpoint returned, and only those', function () {
        $query = ['services' => [['id' => $this->color->id]]];
        brkBook('11:00');
        $slots = collect($this->getJson(brkAvailabilityUrl($query))->assertOk()->json('slots'))->pluck('start');
        $offered = [$slots->first(), $slots->get(intdiv($slots->count(), 2)), $slots->last()];
        $refused = array_values(array_diff([brkLocal('10:00')->getTimestamp(), brkLocal('10:15')->getTimestamp(), brkLocal('16:45')->getTimestamp()], $slots->all()));

        expect($refused)->toHaveCount(3);

        foreach ($refused as $start) {
            $this->actingAs(User::factory()->create())->post(brkStoreUrl(), ['services' => $query['services'], 'staff' => $this->staff->id, 'start' => $start]);
        }

        expect(brkBookingCount())->toBe(1);

        foreach ($offered as $index => $start) {
            DB::beginTransaction();
            $this->actingAs(User::factory()->create())->post(brkStoreUrl(), ['services' => $query['services'], 'staff' => $this->staff->id, 'start' => $start]);
            $made = brkBookingCount();
            DB::rollBack();

            expect($made)->toBe(2, "offered start #{$index} was refused");
        }
    });
});
