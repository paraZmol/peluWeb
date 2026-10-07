<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\FindAvailableSlots;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Enums\ScheduleAccess;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonHoliday;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
| The booking engine through its Actions, against MySQL. "Now" is Wednesday
| 2026-10-07 08:00 in Lima; the professional works Wed and Thu 09:00–18:00.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->salon = Salon::factory()->create(['timezone' => 'America/Lima']);
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create();
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);
    $this->client = SalonClient::factory()->for($this->salon)->create();
});

function lima(string $time, string $date = '2026-10-07'): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima');
}

/**
 * @param  array{client?: SalonClient, services?: list<Service|array{Service, int}>, staff?: Staff|null, source?: BookingSource, note?: string}  $with
 */
function book(string $time, array $with = [], string $date = '2026-10-07'): Booking
{
    $test = test();
    $services = $with['services'] ?? [$test->cut];

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: ($with['client'] ?? $test->client)->refresh(),
        selections: array_map(fn (Service|array $service): array => is_array($service)
            ? ['service_id' => $service[0]->id, 'variant_id' => $service[1]]
            : ['service_id' => $service->id, 'variant_id' => null], $services),
        staff: array_key_exists('staff', $with) ? $with['staff'] : $test->staff,
        startsAt: lima($time, $date),
        source: $with['source'] ?? BookingSource::Client,
        note: $with['note'] ?? null,
    ));
}

/**
 * @return list<string> offered starts as "HH:MM" in Lima
 */
function offered(array $services, ?Staff $staff, ClientLevel $level = ClientLevel::Standard, string $date = '2026-10-07'): array
{
    $slots = app(FindAvailableSlots::class)->handle(
        test()->salon->refresh(),
        array_map(fn (Service $service): array => ['service_id' => $service->id, 'variant_id' => null], $services),
        $staff,
        lima('12:00', $date),
        $level,
        CarbonImmutable::now(),
    );

    return array_map(fn (int $start): string => CarbonImmutable::createFromTimestamp($start, 'America/Lima')->format('H:i'), array_keys($slots));
}

describe('creating a booking', function () {
    it('confirms at once when the salon asks for no deposit', function () {
        $booking = book('10:00', ['note' => 'Fade bajo']);

        expect($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->code)->toMatch('/^[A-HJKMNP-Z2-9]{8}$/')
            ->and($booking->starts_at->toDateTimeString())->toBe('2026-10-07 15:00:00')
            ->and($booking->ends_at->toDateTimeString())->toBe('2026-10-07 15:30:00')
            ->and($booking->price_cents)->toBe(3500)
            ->and($booking->deposit_cents)->toBe(0)
            ->and($booking->hold_expires_at)->toBeNull()
            ->and($booking->client_note)->toBe('Fade bajo')
            ->and($booking->services()->count())->toBe(1)
            ->and($booking->segments()->count())->toBe(1)
            ->and($booking->events()->first()->to_status)->toBe(BookingStatus::Confirmed);
    });

    it('holds the slot as pending while the deposit is awaited', function () {
        $this->salon->forceFill(['deposit_percent' => 30, 'hold_minutes' => 30])->save();

        $booking = book('10:00');

        expect($booking->status)->toBe(BookingStatus::Pending)
            ->and($booking->deposit_cents)->toBe(1050)
            ->and($booking->hold_expires_at->equalTo(now()->addMinutes(30)))->toBeTrue();
    });

    it('rounds the deposit to the céntimo without floats', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();
        $this->cut->forceFill(['price_cents' => 3333])->save();

        expect(book('10:00')->deposit_cents)->toBe(1000);
    });

    it('freezes name, price and durations at booking time', function () {
        $booking = book('10:00');

        $this->cut->forceFill(['name' => 'Corte premium', 'price_cents' => 9900, 'active_minutes' => 90])->save();

        $frozen = $booking->services()->first();
        expect($frozen->name)->toBe('Corte')
            ->and($frozen->price_cents)->toBe(3500)
            ->and($frozen->active_minutes)->toBe(30)
            ->and($booking->refresh()->price_cents)->toBe(3500);
    });

    it('uses the time and price the professional set for themselves', function () {
        $this->staff->services()->updateExistingPivot($this->cut->id, ['active_minutes' => 45, 'price_cents' => 4000]);

        $booking = book('10:00');

        expect($booking->price_cents)->toBe(4000)->and($booking->ends_at->toDateTimeString())->toBe('2026-10-07 15:45:00');
    });

    it('prices a chosen option and adds its extra time', function () {
        $long = $this->cut->variants()->create(['label' => 'Cabello largo', 'price_cents' => 5000, 'extra_minutes' => 15, 'position' => 0]);

        $booking = book('10:00', ['services' => [[$this->cut, $long->id]]]);

        expect($booking->price_cents)->toBe(5000)
            ->and($booking->services()->first()->name)->toBe('Corte · Cabello largo')
            ->and($booking->ends_at->toDateTimeString())->toBe('2026-10-07 15:45:00');
    });

    it('books several services back to back as one visit', function () {
        $beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 20]);
        $this->staff->services()->attach($beard->id);

        $booking = book('10:00', ['services' => [$this->cut, $beard]]);

        expect($booking->price_cents)->toBe(5500)
            ->and($booking->ends_at->toDateTimeString())->toBe('2026-10-07 15:50:00')
            ->and($booking->services()->pluck('name')->all())->toBe(['Corte', 'Barba'])
            ->and($booking->segments()->count())->toBe(1);
    });

    it('stores one segment per working stretch of a color', function () {
        $color = Service::factory()->for($this->salon)->withIdle(20, 35, 25)->create();
        $this->staff->services()->attach($color->id);

        $booking = book('10:00', ['services' => [$color]]);

        $segments = $booking->segments()->get();
        expect($segments)->toHaveCount(2)
            ->and($segments[0]->ends_at->toDateTimeString())->toBe('2026-10-07 15:20:00')
            ->and($segments[1]->starts_at->toDateTimeString())->toBe('2026-10-07 15:55:00')
            ->and($booking->ends_at->toDateTimeString())->toBe('2026-10-07 16:20:00');
    });
});

describe('never double booking', function () {
    it('refuses a start that overlaps another booking and accepts the adjacent ones', function () {
        book('10:00');
        $other = SalonClient::factory()->for($this->salon)->create();

        expect(fn () => book('10:00', ['client' => $other]))->toThrow(BookingException::class)
            ->and(fn () => book('10:15', ['client' => $other]))->toThrow(BookingException::class)
            ->and(fn () => book('09:45', ['client' => $other]))->toThrow(BookingException::class);

        expect(book('10:30', ['client' => $other])->status)->toBe(BookingStatus::Confirmed)
            ->and(book('09:30', ['client' => $other])->status)->toBe(BookingStatus::Confirmed)
            ->and(DB::table('booking_segments')->where('staff_id', $this->staff->id)->count())->toBe(3);
    });

    it('lets two professionals attend at the same time', function () {
        $second = Staff::factory()->for($this->salon)->working([3], '09:00', '18:00')->create();
        $second->services()->attach($this->cut->id);
        book('10:00');

        expect(book('10:00', ['staff' => $second, 'client' => SalonClient::factory()->for($this->salon)->create()])->staff_id)->toBe($second->id);
    });

    it('sells the processing time of a color but protects its final stretch', function () {
        $color = Service::factory()->for($this->salon)->withIdle(20, 35, 25)->create();
        $this->staff->services()->attach($color->id);
        book('10:00', ['services' => [$color]]);
        $other = SalonClient::factory()->for($this->salon)->create();

        expect(offered([$this->cut], $this->staff))->toContain('10:20')->not->toContain('10:30')->not->toContain('10:45');
        expect(book('10:20', ['client' => $other])->status)->toBe(BookingStatus::Confirmed)
            ->and(fn () => book('10:45', ['client' => $other]))->toThrow(BookingException::class);
    });

    it('only accepts starts the time picker would offer', function () {
        expect(fn () => book('10:07'))->toThrow(BookingException::class)
            ->and(fn () => book('17:45'))->toThrow(BookingException::class)
            ->and(fn () => book('08:45'))->toThrow(BookingException::class)
            ->and(Booking::withoutGlobalScopes()->count())->toBe(0);
    });

    it('frees the slot when a booking is cancelled', function () {
        $booking = book('10:00');
        app(ChangeBookingStatus::class)->cancel($booking, null, byClient: true);

        expect(book('10:00', ['client' => SalonClient::factory()->for($this->salon)->create()])->status)->toBe(BookingStatus::Confirmed);
    });
});

describe('when clients may book', function () {
    it('needs the minimum notice and stays inside the horizon', function () {
        $this->salon->forceFill(['min_lead_minutes' => 120, 'max_days_ahead' => 7])->save();

        expect(offered([$this->cut], $this->staff))->not->toContain('09:45')->toContain('10:00')
            ->and(fn () => book('09:30'))->toThrow(BookingException::class)
            ->and(offered([$this->cut], $this->staff, date: '2026-10-14'))->not->toBeEmpty()
            ->and(offered([$this->cut], $this->staff, date: '2026-10-15'))->toBe([])
            ->and(fn () => book('10:00', date: '2026-10-15'))->toThrow(BookingException::class);
    });

    it('lets the team book right now and beyond the horizon', function () {
        $this->travelTo(lima('10:05'));
        $this->salon->forceFill(['max_days_ahead' => 1])->save();

        expect(book('10:00', ['source' => BookingSource::Staff])->status)->toBe(BookingStatus::Confirmed)
            ->and(book('10:00', ['source' => BookingSource::Staff], '2026-10-15')->status)->toBe(BookingStatus::Confirmed)
            ->and(fn () => book('09:30', ['source' => BookingSource::Staff]))->toThrow(BookingException::class);
    });

    it('never asks the team for a deposit', function () {
        $this->salon->forceFill(['deposit_percent' => 50])->save();

        $booking = book('10:00', ['source' => BookingSource::Staff]);

        expect($booking->deposit_cents)->toBe(0)->and($booking->status)->toBe(BookingStatus::Confirmed);
    });

    it('blocks time off and salon holidays', function () {
        StaffTimeOff::factory()->for($this->staff)->create(['starts_at' => lima('09:00')->utc(), 'ends_at' => lima('12:00')->utc()]);
        $holiday = new SalonHoliday;
        $holiday->forceFill(['salon_id' => $this->salon->id, 'date' => '2026-10-08', 'name' => 'Feriado'])->save();

        expect(offered([$this->cut], $this->staff))->not->toContain('11:30')->toContain('12:00')
            ->and(fn () => book('10:00'))->toThrow(BookingException::class)
            ->and(offered([$this->cut], $this->staff, ClientLevel::Team, '2026-10-08'))->toBe([])
            ->and(fn () => book('10:00', ['source' => BookingSource::Staff], '2026-10-08'))->toThrow(BookingException::class);
    });

    it('offers nothing on a day the professional does not work', function () {
        expect(offered([$this->cut], $this->staff, date: '2026-10-09'))->toBe([]);
    });

    it('stops online bookings when the salon pauses them or is suspended', function () {
        $this->salon->forceFill(['accepts_online_bookings' => false])->save();

        expect(fn () => book('10:00'))->toThrow(BookingException::class)
            ->and(book('10:00', ['source' => BookingSource::Staff])->status)->toBe(BookingStatus::Confirmed);

        $this->salon->forceFill(['accepts_online_bookings' => true, 'status' => 'suspended'])->save();

        expect(fn () => book('11:00', ['source' => BookingSource::Staff]))->toThrow(BookingException::class);
    });

    it('limits how many upcoming bookings one client can hold', function () {
        foreach (['09:00', '09:30', '10:00', '10:30', '11:00'] as $time) {
            book($time, date: '2026-10-08');
        }

        expect(fn () => book('11:30', date: '2026-10-08'))->toThrow(BookingException::class)
            ->and(book('11:30', ['source' => BookingSource::Staff], '2026-10-08')->status)->toBe(BookingStatus::Confirmed);
    });
});

describe('VIP and team-only hours', function () {
    beforeEach(function () {
        $this->vipStaff = Staff::factory()->for($this->salon)
            ->working([3], '09:00', '12:00')
            ->working([3], '12:00', '14:00', ScheduleAccess::Vip)
            ->working([3], '18:00', '20:00', ScheduleAccess::StaffOnly)
            ->create();
        $this->vipStaff->services()->attach($this->cut->id);
        $this->vip = SalonClient::factory()->for($this->salon)->vip()->create();
    });

    it('gives a regular client zero VIP or team-only starts', function () {
        $slots = offered([$this->cut], $this->vipStaff);

        expect($slots)->toContain('11:30')->not->toContain('11:45')->not->toContain('12:00')->not->toContain('13:00')->not->toContain('18:00')
            ->and(fn () => book('12:00', ['staff' => $this->vipStaff]))->toThrow(BookingException::class)
            ->and(fn () => book('18:00', ['staff' => $this->vipStaff]))->toThrow(BookingException::class);
    });

    it('lets a VIP client book VIP hours, straddling public and VIP ranges, but not team-only ones', function () {
        expect(offered([$this->cut], $this->vipStaff, ClientLevel::Vip))->toContain('11:45')->toContain('13:30')->not->toContain('18:00');

        $booking = book('11:45', ['staff' => $this->vipStaff, 'client' => $this->vip]);

        expect($booking->is_vip)->toBeTrue()
            ->and(fn () => book('18:00', ['staff' => $this->vipStaff, 'client' => $this->vip]))->toThrow(BookingException::class);
    });

    it('charges VIP clients their own deposit', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'vip_deposit_percent' => 0])->save();

        expect(book('10:00', ['staff' => $this->vipStaff, 'client' => $this->vip])->status)->toBe(BookingStatus::Confirmed)
            ->and(book('10:30', ['staff' => $this->vipStaff])->deposit_cents)->toBe(1750);
    });

    it('lets the team use every range', function () {
        expect(book('18:00', ['staff' => $this->vipStaff, 'source' => BookingSource::Staff])->status)->toBe(BookingStatus::Confirmed);
    });
});

describe('unpaid holds', function () {
    beforeEach(function () {
        $this->salon->forceFill(['deposit_percent' => 30, 'hold_minutes' => 30])->save();
        $this->other = SalonClient::factory()->for($this->salon)->create();
    });

    it('blocks the slot while the hold lasts and frees it afterwards without any cron', function () {
        $pending = book('10:00');

        $this->travel(29)->minutes();
        expect(fn () => book('10:00', ['client' => $this->other]))->toThrow(BookingException::class);

        $this->travel(1)->minutes();
        expect($pending->refresh()->status)->toBe(BookingStatus::Pending)
            ->and($pending->effectiveStatus())->toBe(BookingStatus::Expired)
            ->and(book('10:00', ['client' => $this->other])->status)->toBe(BookingStatus::Pending);
    });

    it('records the expiry with the scheduled command', function () {
        $pending = book('10:00');
        $alive = book('11:00', ['client' => $this->other]);
        $this->travelTo($pending->hold_expires_at);
        $alive->forceFill(['hold_expires_at' => now()->addMinutes(5)])->save();

        $this->artisan('bookings:expire-holds')->assertSuccessful();

        expect($pending->refresh()->status)->toBe(BookingStatus::Expired)
            ->and($alive->refresh()->status)->toBe(BookingStatus::Pending)
            ->and($pending->events()->get()->last()->to_status)->toBe(BookingStatus::Expired);
    });

    it('confirms a late deposit only if nobody took the slot meanwhile', function () {
        $late = book('10:00');
        $lost = book('11:00');
        $this->travel(31)->minutes();
        book('11:00', ['client' => $this->other]);

        $confirmed = app(ChangeBookingStatus::class)->confirm($late, null);

        expect($confirmed->status)->toBe(BookingStatus::Confirmed)
            ->and($confirmed->deposit_paid_at)->not->toBeNull()
            ->and($confirmed->hold_expires_at)->toBeNull()
            ->and(fn () => app(ChangeBookingStatus::class)->confirm($lost, null))->toThrow(BookingException::class)
            ->and($lost->refresh()->status)->toBe(BookingStatus::Pending);
    });
});

describe('who attends', function () {
    beforeEach(function () {
        $this->second = Staff::factory()->for($this->salon)->working([3], '09:00', '18:00')->create(['position' => 5]);
        $this->second->services()->attach($this->cut->id);
    });

    it('with "anyone", gives the visit to whoever has fewer bookings that day', function () {
        book('09:00');
        book('09:30');

        $booking = book('15:00', ['staff' => null]);

        expect($booking->staff_id)->toBe($this->second->id);
    });

    it('with "anyone", falls back to whoever is free at that time', function () {
        book('10:00', ['staff' => $this->second]);

        expect(book('10:00', ['staff' => null, 'client' => SalonClient::factory()->for($this->salon)->create()])->staff_id)->toBe($this->staff->id)
            ->and(fn () => book('10:00', ['staff' => null, 'client' => SalonClient::factory()->for($this->salon)->create()]))->toThrow(BookingException::class);
    });

    it('offers the union of the team for "anyone" and leaves out who does not do the service', function () {
        $beard = Service::factory()->for($this->salon)->create(['active_minutes' => 20]);
        $this->second->services()->attach($beard->id);
        book('10:00', ['staff' => $this->second]);

        expect(offered([$this->cut], null))->toContain('10:00')
            ->and(offered([$beard], null))->not->toContain('10:00')->toContain('10:30')
            ->and(book('11:00', ['staff' => null, 'services' => [$beard]])->staff_id)->toBe($this->second->id)
            ->and(fn () => book('12:00', ['staff' => $this->staff, 'services' => [$beard]]))->toThrow(BookingException::class);
    });

    it('refuses paused professionals and professionals of another salon', function () {
        $this->second->forceFill(['is_active' => false])->save();
        $foreign = Staff::factory()->for(Salon::factory()->create())->working([3], '09:00', '18:00')->create();

        expect(fn () => book('10:00', ['staff' => $this->second]))->toThrow(BookingException::class)
            ->and(fn () => book('10:00', ['staff' => $foreign]))->toThrow(BookingException::class);
    });
});

describe('what can be booked', function () {
    it('refuses services that are paused, deleted, repeated, of another salon or only add-ons', function (Closure $services) {
        expect(fn () => book('10:00', ['services' => $services($this)]))->toThrow(BookingException::class);
    })->with([
        'paused' => [fn ($t) => [tap($t->cut, fn (Service $s) => $s->forceFill(['is_active' => false])->save())]],
        'deleted' => [fn ($t) => [tap($t->cut)->delete()]],
        'repeated' => [fn ($t) => [$t->cut, $t->cut]],
        'another salon' => [fn ($t) => [Service::factory()->for(Salon::factory()->create())->create()]],
        'only an add-on' => [fn ($t) => [tap($t->cut, fn (Service $s) => $s->forceFill(['is_addon' => true])->save())]],
        'none' => [fn ($t) => []],
    ]);

    it('needs an option for services that have them and refuses a foreign one', function () {
        $this->cut->variants()->create(['label' => 'Corto', 'price_cents' => 3000, 'extra_minutes' => 0, 'position' => 0]);
        $other = Service::factory()->for($this->salon)->create();
        $foreign = $other->variants()->create(['label' => 'X', 'price_cents' => 100, 'extra_minutes' => 0, 'position' => 0]);

        expect(fn () => book('10:00'))->toThrow(BookingException::class)
            ->and(fn () => book('10:00', ['services' => [[$this->cut, $foreign->id]]]))->toThrow(BookingException::class);
    });

    it('refuses more services than a visit allows', function () {
        $many = Service::factory()->for($this->salon)->count(6)->create(['active_minutes' => 5]);
        $this->staff->services()->attach($many->modelKeys());

        expect(fn () => book('10:00', ['services' => $many->all()]))->toThrow(BookingException::class);
    });
});

describe('state machine', function () {
    beforeEach(function () {
        $this->status = app(ChangeBookingStatus::class);
    });

    it('walks the happy path and records every step', function () {
        $booking = book('10:00');
        $this->travelTo(lima('10:00'));

        $this->status->start($booking, null);
        $done = $this->status->complete($booking, null);

        expect($done->status)->toBe(BookingStatus::Completed)
            ->and($booking->events()->pluck('to_status')->all())
            ->toBe([BookingStatus::Confirmed, BookingStatus::InService, BookingStatus::Completed]);
    });

    it('refuses every jump the diagram does not have', function (Closure $jump) {
        $booking = book('10:00');

        expect(fn () => $jump($this->status, $booking))->toThrow(BookingException::class);
    })->with([
        'complete before starting' => [fn ($status, $booking) => $status->complete($booking, null)],
        'confirm twice' => [fn ($status, $booking) => $status->confirm($booking, null)],
        'cancel after completing' => [function ($status, $booking) {
            $status->start($booking, null);
            $status->complete($booking, null);
            $status->cancel($booking, null, byClient: false);
        }],
        'start a cancelled one' => [function ($status, $booking) {
            $status->cancel($booking, null, byClient: false);
            $status->start($booking, null);
        }],
        'no-show while in service' => [function ($status, $booking) {
            $status->start($booking, null);
            test()->travelTo(lima('10:10'));
            $status->markNoShow($booking, null);
        }],
    ]);

    it('keeps or forfeits a paid deposit depending on who cancels and when', function () {
        $this->salon->forceFill(['deposit_percent' => 30, 'cancel_window_hours' => 24])->save();
        $early = $this->status->confirm(book('10:00', date: '2026-10-08'), null);
        $late = $this->status->confirm(book('15:00'), null);
        $bySalon = $this->status->confirm(book('16:00'), null);
        $unpaid = book('17:00');

        // 08:00 Wednesday: Thursday 10:00 is 26 h away, today 15:00 is 7 h away.
        expect($this->status->cancel($early, null, byClient: true)->deposit_refundable)->toBeTrue()
            ->and($this->status->cancel($late, null, byClient: true)->deposit_refundable)->toBeFalse()
            ->and($this->status->cancel($bySalon, null, byClient: false)->deposit_refundable)->toBeTrue()
            ->and($this->status->cancel($unpaid, null, byClient: true)->deposit_refundable)->toBeNull();
    });

    it('does not let a client cancel once the visit started', function () {
        $booking = book('10:00');
        $this->travelTo(lima('10:00'));

        expect(fn () => $this->status->cancel($booking, null, byClient: true))->toThrow(BookingException::class)
            ->and($this->status->cancel($booking, null, byClient: false)->status)->toBe(BookingStatus::Cancelled);
    });

    it('marks a no-show only after the start time and counts it for the client', function () {
        $booking = book('10:00');

        expect(fn () => $this->status->markNoShow($booking, null))->toThrow(BookingException::class);

        $this->travelTo(lima('10:01'));
        $this->status->markNoShow($booking, null);

        expect($booking->refresh()->status)->toBe(BookingStatus::NoShow)
            ->and($this->client->refresh()->no_show_count)->toBe(1);
    });
});
