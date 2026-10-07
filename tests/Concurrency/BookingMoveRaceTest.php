<?php

use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Booking\StaffAvailability;
use App\Data\Booking\NewBooking;
use App\Domain\Booking\TimeSpan;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Models\Booking;
use App\Models\BookingSegment;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonHoliday;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
| Step 4.8 under real concurrency: bookings being moved (RescheduleBooking)
| against other moves, new bookings, status changes and agenda changes, and
| the team booking for clients. Real PHP processes (see move.php), through
| the actions and through the HTTP kernel. No transaction wraps these
| tests, so each cleans up after itself.
|
| Ana and Beto work on "the day" (three days from now, so clients have the
| 24 h notice) and the day after, 09:00–18:00. The service takes 30 min.
*/

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    $this->users = collect();
    $this->owner = mvUser();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['min_lead_minutes' => 0, 'timezone' => 'America/Lima']);
    $this->service = Service::factory()->for($this->salon)->create(['active_minutes' => 30]);
    $this->day = CarbonImmutable::now('America/Lima')->addDays(3)->setTime(10, 0);
    $this->ana = mvStaff('Ana');
    $this->beto = mvStaff('Beto');
});

afterEach(function () {
    // A failed assertion must not leave a gate's lock behind.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    DB::statement('SET foreign_key_checks = 1');
    DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
    $this->salon->forceDelete();
    User::whereIn('id', $this->users->pluck('id'))->delete();
});

function mvUser(): User
{
    $user = User::factory()->create();
    test()->users->push($user);

    return $user;
}

function mvStaff(string $name): Staff
{
    $day = test()->day;
    $staff = Staff::factory()->for(test()->salon)->working([$day->dayOfWeekIso, $day->addDay()->dayOfWeekIso], '09:00', '18:00')->create(['name' => $name]);
    $staff->services()->attach(test()->service->id);

    return $staff;
}

/**
 * An instant of "the day" (or $daysLater after it) on the salon's clock.
 */
function mvAt(string $time, int $daysLater = 0): CarbonImmutable
{
    return test()->day->addDays($daysLater)->setTimeFromTimeString($time);
}

function mvClient(?User $user = null): SalonClient
{
    $factory = SalonClient::factory()->for(test()->salon);

    return ($user === null ? $factory : $factory->forUser($user))->create();
}

/**
 * A booking made before the race, straight through the action.
 */
function mvBooking(string $time, ?Staff $staff = null, ?SalonClient $client = null): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client ?? mvClient(),
        selections: [['service_id' => test()->service->id, 'variant_id' => null]],
        staff: $staff ?? test()->ana,
        startsAt: mvAt($time),
        source: BookingSource::Client,
    ));
}

/**
 * @param  array<string, mixed>  $job
 */
function mvProcess(array $job, float $goAt): Process
{
    $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    $process = new Process([PHP_BINARY, __DIR__.'/move.php', (string) $goAt, base64_encode(json_encode($job, JSON_THROW_ON_ERROR))], base_path(), $env);
    $process->setTimeout(90);
    $process->start();

    return $process;
}

/**
 * @param  list<Process>  $processes
 * @return list<string>
 */
function mvResults(array $processes): array
{
    return array_map(function (Process $process): string {
        $process->wait();

        return trim(str_replace('READY', '', $process->getOutput())).trim($process->getErrorOutput());
    }, $processes);
}

/**
 * One worker per job, all firing at the same instant.
 *
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function mvAll(array $jobs): array
{
    $goAt = microtime(true) + 4;

    return mvResults(array_map(fn (array $job): Process => mvProcess($job, $goAt), $jobs));
}

/**
 * Wait until each worker has booted and sent its request, and give it time
 * to reach the lock it queues behind. (The test database user may not read
 * information_schema.innodb_trx, so the workers announce themselves.)
 *
 * @param  list<Process>  $processes
 */
function mvUntilQueued(array $processes): void
{
    $deadline = microtime(true) + 30;

    foreach ($processes as $process) {
        while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
            usleep(100_000);
        }
    }

    usleep(1_500_000);
}

/**
 * The deterministic race: this process holds row locks until every worker
 * is queued behind them, then lets go. All of them started while the
 * agenda was as the test left it. A job with "settle" queues before the
 * next one starts, so it is served first.
 *
 * @param  Staff|SalonClient|list<Staff|SalonClient>  $gate
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function mvBehindGate(Staff|SalonClient|array $gate, array $jobs): array
{
    DB::beginTransaction();

    foreach (is_array($gate) ? $gate : [$gate] as $row) {
        $row->newQueryWithoutScopes()->lockForUpdate()->findOrFail($row->id);
    }

    $processes = [];

    foreach ($jobs as $job) {
        $processes[] = mvProcess($job, 0.0);

        if ($job['settle'] ?? false) {
            mvUntilQueued($processes);
        }
    }

    mvUntilQueued($processes);
    DB::commit();

    return mvResults($processes);
}

/**
 * @param  list<string>  $results
 * @return array{ok: int, taken: int, error: int, all: string}
 */
function mvTally(array $results): array
{
    return [
        'ok' => count(array_filter($results, fn (string $result) => str_starts_with($result, 'OK'))),
        'taken' => count(array_filter($results, fn (string $result) => $result === 'TAKEN')),
        'error' => count(array_filter($results, fn (string $result) => ! str_starts_with($result, 'OK') && ! in_array($result, ['TAKEN', 'DONE'], true))),
        'all' => implode(' | ', $results),
    ];
}

/**
 * Pairs of busy stretches of one professional that overlap, among the
 * bookings of the salon that hold their slot right now. Must be zero.
 */
function mvOverlaps(): int
{
    $holding = fn (string $alias): string => "({$alias}.status in ('confirmed', 'in_service') or ({$alias}.status = 'pending' and {$alias}.hold_expires_at > utc_timestamp()))";

    return (int) DB::scalar(
        'select count(*) from booking_segments a
           join booking_segments b on a.staff_id = b.staff_id and a.id < b.id and a.starts_at < b.ends_at and b.starts_at < a.ends_at
           join bookings ba on ba.id = a.booking_id
           join bookings bb on bb.id = b.booking_id
          where ba.salon_id = ? and ba.id <> bb.id and '.$holding('ba').' and '.$holding('bb'),
        [test()->salon->id],
    );
}

/**
 * Bookings whose stored stretches do not match their own start, end and
 * professional. Must be empty: a move rewrites all of them together.
 *
 * @return list<string>
 */
function mvIncoherent(): array
{
    $problems = [];

    foreach (Booking::withoutGlobalScopes()->where('salon_id', test()->salon->id)->with('segments')->get() as $booking) {
        $shape = $booking->shape();
        $start = $booking->starts_at->getTimestamp();
        $expected = array_map(fn (TimeSpan $span): string => "{$booking->staff_id}:{$span->start}-{$span->end}", $shape->busyAt($start));
        $stored = $booking->segments
            ->map(fn (BookingSegment $segment): string => "{$segment->staff_id}:{$segment->starts_at->getTimestamp()}-{$segment->ends_at->getTimestamp()}")
            ->sort()->values()->all();
        sort($expected);

        if ($stored !== $expected || $booking->ends_at->getTimestamp() !== $shape->endsAt($start)) {
            $problems[] = "booking {$booking->id}: stored ".implode(',', $stored).' expected '.implode(',', $expected);
        }
    }

    return $problems;
}

/**
 * Bookings holding a slot that today nobody could have booked there: out
 * of the professional's hours, on a salon holiday, or over time off.
 *
 * @return list<string>
 */
function mvStranded(): array
{
    $salon = test()->salon->refresh();
    $availability = app(StaffAvailability::class);
    $problems = [];

    foreach (Booking::withoutGlobalScopes()->where('salon_id', $salon->id)->holdingSlot()->with('segments')->get() as $booking) {
        $staff = Staff::withoutGlobalScopes()->withTrashed()->findOrFail($booking->staff_id);
        $local = $booking->starts_at->setTimezone($salon->timezone);
        $windows = $availability->windows($salon, $staff, $local, ClientLevel::Team);
        $timeOff = StaffTimeOff::withoutGlobalScopes()->where('staff_id', $staff->id)->get();

        foreach ($booking->segments as $segment) {
            $span = new TimeSpan($segment->starts_at->getTimestamp(), $segment->ends_at->getTimestamp());
            $inside = collect($windows)->contains(fn (TimeSpan $window): bool => $window->covers($span));
            $away = $timeOff->contains(fn (StaffTimeOff $off): bool => $span->overlaps(new TimeSpan($off->starts_at->getTimestamp(), $off->ends_at->getTimestamp())));

            if (! $inside || $away) {
                $problems[] = "booking {$booking->id} at {$local->format('Y-m-d H:i')} with {$staff->name}: ".($inside ? 'over time off' : 'outside hours or on a holiday');
            }
        }
    }

    return $problems;
}

/**
 * @return array<string, mixed>
 */
function mvMoveJob(Booking $booking, CarbonImmutable $start, ?Staff $to = null): array
{
    return ['op' => 'move', 'booking' => $booking->id, 'start' => $start->getTimestamp(), 'staff' => $to?->id ?? 0];
}

/**
 * The team's POST agenda/{booking}/mover, sent by the owner.
 *
 * @return array<string, mixed>
 */
function mvHttpMoveJob(Booking $booking, CarbonImmutable $start, ?Staff $to = null): array
{
    return ['op' => 'httpMove', 'user' => test()->owner->id, 'booking' => $booking->id, 'start' => $start->getTimestamp(), 'staff' => $to?->id];
}

/**
 * @return array<string, mixed>
 */
function mvBookJob(CarbonImmutable $start, ?Staff $staff, ?SalonClient $client = null): array
{
    return [
        'op' => 'book',
        'salon' => test()->salon->id,
        'client' => ($client ?? mvClient())->id,
        'service' => test()->service->id,
        'staff' => $staff?->id ?? 0,
        'start' => $start->getTimestamp(),
    ];
}

/**
 * The public POST s/{salon}/reservas, sent by a new account.
 *
 * @return array<string, mixed>
 */
function mvHttpBookJob(CarbonImmutable $start, ?Staff $staff, ?User $user = null): array
{
    return [
        'op' => 'httpBook',
        'salon' => test()->salon->id,
        'user' => ($user ?? mvUser())->id,
        'service' => test()->service->id,
        'staff' => $staff?->id ?? 0,
        'start' => $start->getTimestamp(),
    ];
}

/**
 * The team's POST agenda, sent by the owner for a walk-in.
 *
 * @return array<string, mixed>
 */
function mvAgendaJob(CarbonImmutable $start, ?Staff $staff, string $name, ?string $phone): array
{
    return [
        'op' => 'httpAgenda',
        'user' => test()->owner->id,
        'service' => test()->service->id,
        'staff' => $staff?->id ?? 0,
        'start' => $start->getTimestamp(),
        'client_name' => $name,
        'client_phone' => $phone,
    ];
}

/**
 * Bookings of the salon that hold a slot of that professional starting then.
 */
function mvHoldersAt(Staff $staff, CarbonImmutable $start): int
{
    return Booking::withoutGlobalScopes()->where('staff_id', $staff->id)->where('starts_at', $start->utc())->holdingSlot()->count();
}

describe('two bookings of one professional moved to the same time', function () {
    it('lets exactly one in when both moves were queued while the time was free (action)', function () {
        $first = mvBooking('10:00');
        $second = mvBooking('11:00');

        $tally = mvTally(mvBehindGate($this->ana, [mvMoveJob($first, mvAt('14:00')), mvMoveJob($second, mvAt('14:00'))]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvHoldersAt($this->ana, mvAt('14:00')))->toBe(1)
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('lets exactly one in when both moves were queued while the time was free (HTTP)', function () {
        $first = mvBooking('10:00');
        $second = mvBooking('11:00');

        $tally = mvTally(mvBehindGate($this->ana, [mvHttpMoveJob($first, mvAt('14:00')), mvHttpMoveJob($second, mvAt('14:00'))]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvHoldersAt($this->ana, mvAt('14:00')))->toBe(1)
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('lets exactly one of six in when they all POST the move at the same instant', function () {
        $bookings = array_map(fn (string $time) => mvBooking($time), ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30']);

        $tally = mvTally(mvAll(array_map(fn (Booking $booking) => mvHttpMoveJob($booking, mvAt('15:00')), $bookings)));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($tally['taken'])->toBe(5, $tally['all'])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('never overlaps moves that only partly collide (14:00 and 14:15, 30 min each)', function () {
        $first = mvBooking('10:00');
        $second = mvBooking('11:00');
        $third = mvBooking('12:00');

        $tally = mvTally(mvBehindGate($this->ana, [
            mvHttpMoveJob($first, mvAt('14:00')),
            mvMoveJob($second, mvAt('14:15')),
            mvHttpMoveJob($third, mvAt('13:45')),
        ]));

        // 13:45 and 14:15 can both win (they only touch); 14:00 excludes both.
        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBeGreaterThanOrEqual(1, $tally['all'])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('leaves one coherent booking when the same booking is moved to two times at once', function () {
        $booking = mvBooking('10:00');

        $tally = mvTally(mvBehindGate($this->ana, [mvHttpMoveJob($booking, mvAt('14:00')), mvMoveJob($booking, mvAt('15:00'))]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(DB::table('booking_segments')->where('booking_id', $booking->id)->count())->toBe(1)
            ->and(mvIncoherent())->toBe([])
            ->and(mvOverlaps())->toBe(0);
    });
});

describe('a move against a new booking for the same time', function () {
    it('gives the time to exactly one of the move and the booking (actions)', function () {
        $booking = mvBooking('10:00');

        $tally = mvTally(mvBehindGate($this->ana, [mvMoveJob($booking, mvAt('14:00')), mvBookJob(mvAt('14:00'), $this->ana)]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvHoldersAt($this->ana, mvAt('14:00')))->toBe(1)
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('gives the time to exactly one of the move and the bookings (HTTP on both sides)', function () {
        $booking = mvBooking('10:00');

        $tally = mvTally(mvBehindGate($this->ana, [
            mvHttpBookJob(mvAt('14:00'), $this->ana),
            mvHttpMoveJob($booking, mvAt('14:00')),
            mvHttpBookJob(mvAt('14:00'), $this->ana),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvHoldersAt($this->ana, mvAt('14:00')))->toBe(1)
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('gives the time to exactly one when a move, the team and four clients ask for it at the same instant', function () {
        $booking = mvBooking('10:00');

        $tally = mvTally(mvAll([
            mvHttpMoveJob($booking, mvAt('14:00')),
            mvAgendaJob(mvAt('14:00'), $this->ana, 'Doña Rosa', '987000111'),
            mvHttpBookJob(mvAt('14:00'), $this->ana),
            mvHttpBookJob(mvAt('14:00'), $this->ana),
            mvBookJob(mvAt('14:00'), $this->ana),
            mvBookJob(mvAt('14:15'), $this->ana),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('frees the old time for others only once the move is done', function () {
        $booking = mvBooking('10:00');

        // The new booking for 10:00 is served first: the move has not happened yet.
        $tally = mvTally(mvBehindGate($this->ana, [
            [...mvHttpBookJob(mvAt('10:00'), $this->ana), 'settle' => true],
            mvHttpMoveJob($booking, mvAt('14:00')),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($booking->refresh()->starts_at->getTimestamp())->toBe(mvAt('14:00')->getTimestamp())
            ->and(mvHoldersAt($this->ana, mvAt('10:00')))->toBe(0)
            ->and(mvOverlaps())->toBe(0);
    });
});

describe('moves between professionals', function () {
    it('answers every move when bookings cross from Ana to Beto and from Beto to Ana at the same instant', function () {
        $times = ['09:00', '10:00', '11:00'];
        $anas = array_map(fn (string $time) => mvBooking($time, $this->ana), $times);
        $betos = array_map(fn (string $time) => mvBooking($time, $this->beto), $times);
        $targets = ['14:00', '15:00', '16:00'];

        $jobs = [];

        foreach ($targets as $index => $time) {
            $jobs[] = mvHttpMoveJob($anas[$index], mvAt($time), $this->beto);
            $jobs[] = mvMoveJob($betos[$index], mvAt($time), $this->ana);
        }

        $tally = mvTally(mvAll($jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(6, $tally['all'])
            ->and(Booking::withoutGlobalScopes()->whereIn('id', array_map(fn (Booking $booking) => $booking->id, $anas))->pluck('staff_id')->unique()->all())->toBe([$this->beto->id])
            ->and(Booking::withoutGlobalScopes()->whereIn('id', array_map(fn (Booking $booking) => $booking->id, $betos))->pluck('staff_id')->unique()->all())->toBe([$this->ana->id])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('answers both when two bookings try to swap professionals at the very same time', function () {
        $anas = mvBooking('10:00', $this->ana);
        $betos = mvBooking('10:00', $this->beto);

        $tally = mvTally(mvBehindGate([$this->ana, $this->beto], [
            mvHttpMoveJob($anas, mvAt('10:00'), $this->beto),
            mvHttpMoveJob($betos, mvAt('10:00'), $this->ana),
        ]));

        // Neither time is free while the other booking is still there.
        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(0, $tally['all'])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('answers every request across several rounds of crossed moves, status changes and "anyone" bookings', function () {
        $errors = [];

        foreach (range(1, 3) as $round) {
            DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
            $anas = mvBooking('10:00', $this->ana);
            $betos = mvBooking('10:00', $this->beto);
            $extra = mvBooking('11:00', $this->beto);

            $tally = mvTally(mvAll([
                mvMoveJob($anas, mvAt('12:00'), $this->beto),
                mvMoveJob($betos, mvAt('12:00'), $this->ana),
                ['op' => 'cancel', 'booking' => $extra->id],
                mvBookJob(mvAt('12:00'), null),
                mvBookJob(mvAt('12:00'), null),
                mvHttpBookJob(mvAt('10:00'), null),
            ]));

            if ($tally['error'] > 0 || mvOverlaps() > 0 || mvIncoherent() !== []) {
                $errors[] = "round {$round}: {$tally['all']} overlaps ".mvOverlaps().' '.implode('; ', mvIncoherent());
            }
        }

        expect($errors)->toBe([]);
    });

    it('gives the colleague\'s time to exactly one of the booking handed over and a new booking (actions)', function () {
        $booking = mvBooking('10:00', $this->ana);

        $tally = mvTally(mvBehindGate($this->beto, [mvMoveJob($booking, mvAt('12:00'), $this->beto), mvBookJob(mvAt('12:00'), $this->beto)]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvHoldersAt($this->beto, mvAt('12:00')))->toBe(1)
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('gives the colleague\'s time to exactly one of the booking handed over and the clients booking it (HTTP)', function () {
        $booking = mvBooking('10:00', $this->ana);

        $tally = mvTally(mvBehindGate($this->beto, [
            mvHttpBookJob(mvAt('12:00'), $this->beto),
            mvHttpMoveJob($booking, mvAt('12:00'), $this->beto),
            mvHttpBookJob(mvAt('12:15'), $this->beto),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });

    it('fills each professional once when a booking is handed over while five people ask for "anyone" at that time', function () {
        $booking = mvBooking('10:00', $this->ana);

        $tally = mvTally(mvAll([
            mvHttpMoveJob($booking, mvAt('12:00'), $this->beto),
            ...array_map(fn () => mvHttpBookJob(mvAt('12:00'), null), range(1, 5)),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(mvHoldersAt($this->ana, mvAt('12:00')))->toBe(1)
            ->and(mvHoldersAt($this->beto, mvAt('12:00')))->toBe(1)
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([]);
    });
});

describe('a move against a status change of the same booking', function () {
    it('ends cancelled whichever is served first: the cancellation', function () {
        $booking = mvBooking('10:00');

        $tally = mvTally(mvBehindGate($this->ana, [
            ['op' => 'cancel', 'booking' => $booking->id, 'settle' => true],
            mvHttpMoveJob($booking, mvAt('14:00')),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->starts_at->getTimestamp())->toBe(mvAt('10:00')->getTimestamp())
            ->and(mvIncoherent())->toBe([]);
    });

    it('ends cancelled whichever is served first: the move', function () {
        $booking = mvBooking('10:00');

        $tally = mvTally(mvBehindGate($this->ana, [
            [...mvHttpMoveJob($booking, mvAt('14:00')), 'settle' => true],
            ['op' => 'httpStatus', 'user' => $this->owner->id, 'booking' => $booking->id, 'action' => 'cancelar'],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->starts_at->getTimestamp())->toBe(mvAt('14:00')->getTimestamp())
            ->and(mvIncoherent())->toBe([])
            // The time it moved to is free again.
            ->and(mvHoldersAt($this->ana, mvAt('14:00')))->toBe(0);
    });

    it('stays coherent when it is handed to a colleague while it is being cancelled', function () {
        $booking = mvBooking('10:00', $this->ana);

        $tally = mvTally(mvBehindGate([$this->ana, $this->beto], [
            [...mvHttpMoveJob($booking, mvAt('14:00'), $this->beto), 'settle' => true],
            ['op' => 'cancel', 'booking' => $booking->id],
        ]));
        $booking->refresh();

        // The cancellation waited for Ana's row, which no longer guards the
        // booking: it is refused ("changed meanwhile") rather than applied blind.
        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($booking->staff_id)->toBe($this->beto->id)
            ->and($booking->status)->toBe(BookingStatus::Confirmed)
            ->and(mvIncoherent())->toBe([])
            ->and(mvOverlaps())->toBe(0);
    });

    it('lets either the late confirmation of an expired hold or a booking moved onto its time win, never both', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        $hold = mvBooking('14:00');
        DB::table('bookings')->where('id', $hold->id)->update(['hold_expires_at' => now()->subMinute()]);
        $this->salon->forceFill(['deposit_percent' => 0])->save();
        $moving = mvBooking('10:00');

        $tally = mvTally(mvAll([
            ['op' => 'confirm', 'booking' => $hold->id],
            mvHttpMoveJob($moving, mvAt('14:00')),
            mvMoveJob($moving, mvAt('14:15')),
            ['op' => 'confirm', 'booking' => $hold->id],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(mvOverlaps())->toBe(0)
            ->and(mvIncoherent())->toBe([])
            ->and(Booking::withoutGlobalScopes()->where('staff_id', $this->ana->id)->holdingSlot()->where('starts_at', '<', mvAt('14:30')->utc())->where('ends_at', '>', mvAt('14:00')->utc())->count())->toBe(1, $tally['all']);
    });

    it('does not move a hold that ran out, even while someone else takes its time', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        $hold = mvBooking('14:00');
        DB::table('bookings')->where('id', $hold->id)->update(['hold_expires_at' => now()->subMinute()]);

        $tally = mvTally(mvBehindGate($this->ana, [
            mvHttpMoveJob($hold, mvAt('15:00')),
            mvBookJob(mvAt('14:00'), $this->ana),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($hold->refresh()->starts_at->getTimestamp())->toBe(mvAt('14:00')->getTimestamp())
            ->and(mvOverlaps())->toBe(0);
    });

    it('ends in service with coherent stretches when it is moved while the client is marked as arrived', function () {
        $now = CarbonImmutable::now('America/Lima');

        if ($now->hour >= 21) {
            $this->markTestSkipped('Too close to midnight in Lima to move a visit within today.');
        }

        // Ana also works today, all day; the visit is in a little while.
        StaffSchedule::withoutGlobalScopes()->forceCreate([
            'salon_id' => $this->salon->id, 'staff_id' => $this->ana->id, 'weekday' => $now->dayOfWeekIso,
            'start_time' => '00:00', 'end_time' => '23:59', 'access_level' => 'public',
        ]);
        $soon = $now->startOfHour()->addHours(2);
        $booking = mvBooking('10:00');
        DB::table('bookings')->where('id', $booking->id)->update(['starts_at' => $soon->utc(), 'ends_at' => $soon->addMinutes(30)->utc()]);
        DB::table('booking_segments')->where('booking_id', $booking->id)->update(['starts_at' => $soon->utc(), 'ends_at' => $soon->addMinutes(30)->utc()]);

        $tally = mvTally(mvAll([
            mvHttpMoveJob($booking, $soon->subMinutes(30)),
            ['op' => 'httpStatus', 'user' => $this->owner->id, 'booking' => $booking->id, 'action' => 'iniciar'],
            mvMoveJob($booking, $soon->addMinutes(30)),
            ['op' => 'start', 'booking' => $booking->id],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($booking->refresh()->status)->toBe(BookingStatus::InService)
            ->and($booking->events()->where('to_status', 'in_service')->count())->toBe(1)
            ->and(mvIncoherent())->toBe([])
            ->and(mvOverlaps())->toBe(0);
    });
});

describe('the client\'s limit of moves', function () {
    it('allows exactly three when the client POSTs six moves that were all queued at once', function () {
        $user = mvUser();
        $booking = mvBooking('10:00', $this->ana, mvClient($user));
        $jobs = array_map(
            fn (string $time) => ['op' => 'httpClientMove', 'user' => $user->id, 'code' => $booking->code, 'start' => mvAt($time)->getTimestamp()],
            ['11:00', '12:00', '13:00', '14:00', '15:00', '16:00'],
        );

        $tally = mvTally(mvBehindGate($this->ana, $jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(RescheduleBooking::MAX_BY_CLIENT, $tally['all'])
            ->and($booking->events()->whereColumn('from_status', 'to_status')->count())->toBe(RescheduleBooking::MAX_BY_CLIENT)
            ->and(mvIncoherent())->toBe([]);
    });

    it('allows exactly three when the client fires six moves at the same instant', function () {
        $user = mvUser();
        $booking = mvBooking('10:00', $this->ana, mvClient($user));
        $jobs = array_map(
            fn (string $time) => ['op' => 'httpClientMove', 'user' => $user->id, 'code' => $booking->code, 'start' => mvAt($time)->getTimestamp()],
            ['11:00', '12:00', '13:00', '14:00', '15:00', '16:00'],
        );

        $tally = mvTally(mvAll($jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(RescheduleBooking::MAX_BY_CLIENT, $tally['all'])
            ->and($booking->events()->whereColumn('from_status', 'to_status')->count())->toBe(RescheduleBooking::MAX_BY_CLIENT)
            ->and(mvIncoherent())->toBe([]);
    });
});

describe('a move against a change of the agenda', function () {
    it('never leaves a visit outside the hours when the hours shrink first', function () {
        $booking = mvBooking('15:00');
        $ranges = [['weekday' => $this->day->dayOfWeekIso, 'start' => '14:00', 'end' => '18:00', 'access' => 'public']];

        $tally = mvTally(mvBehindGate($this->ana, [
            ['op' => 'schedule', 'staff' => $this->ana->id, 'ranges' => $ranges, 'settle' => true],
            mvHttpMoveJob($booking, mvAt('10:00')),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvStranded())->toBe([]);
    });

    it('never leaves a visit outside the hours when the move is served first', function () {
        $booking = mvBooking('15:00');
        $ranges = [['weekday' => $this->day->dayOfWeekIso, 'start' => '14:00', 'end' => '18:00', 'access' => 'public']];

        $tally = mvTally(mvBehindGate($this->ana, [
            [...mvHttpMoveJob($booking, mvAt('10:00')), 'settle' => true],
            ['op' => 'schedule', 'staff' => $this->ana->id, 'ranges' => $ranges],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvStranded())->toBe([]);
    });

    it('never hands a visit to a colleague whose hours are being cut at that moment', function () {
        $booking = mvBooking('15:00', $this->ana);
        $ranges = [['weekday' => $this->day->dayOfWeekIso, 'start' => '14:00', 'end' => '18:00', 'access' => 'public']];

        $tally = mvTally(mvAll([
            ['op' => 'schedule', 'staff' => $this->beto->id, 'ranges' => $ranges],
            mvHttpMoveJob($booking, mvAt('10:00'), $this->beto),
            mvMoveJob($booking, mvAt('11:00'), $this->beto),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])->and(mvStranded())->toBe([], $tally['all']);
    });

    it('never moves a visit onto an absence, whichever of the two is served first', function (bool $absenceFirst) {
        $booking = mvBooking('15:00');
        $absence = ['op' => 'timeoff', 'staff' => $this->ana->id, 'from' => mvAt('09:00')->getTimestamp(), 'to' => mvAt('12:00')->getTimestamp()];
        $move = mvHttpMoveJob($booking, mvAt('10:00'));

        $tally = mvTally(mvBehindGate($this->ana, $absenceFirst
            ? [[...$absence, 'settle' => true], $move]
            : [[...$move, 'settle' => true], $absence]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvStranded())->toBe([]);
    })->with([true, false]);

    it('never moves a visit onto an absence when both are sent at the same instant', function () {
        $booking = mvBooking('15:00', $this->ana);

        $tally = mvTally(mvAll([
            ['op' => 'timeoff', 'staff' => $this->beto->id, 'from' => mvAt('09:00')->getTimestamp(), 'to' => mvAt('12:00')->getTimestamp()],
            mvHttpMoveJob($booking, mvAt('10:00'), $this->beto),
            mvMoveJob($booking, mvAt('11:30'), $this->beto),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])->and(mvStranded())->toBe([], $tally['all']);
    });

    it('never hands a visit to a colleague who is being removed or paused, whichever is served first', function (string $change, bool $changeFirst) {
        $booking = mvBooking('15:00', $this->ana);
        $leaving = ['op' => $change, 'staff' => $this->beto->id];
        $move = mvHttpMoveJob($booking, mvAt('10:00'), $this->beto);

        $tally = mvTally(mvBehindGate($this->beto, $changeFirst
            ? [[...$leaving, 'settle' => true], $move]
            : [[...$move, 'settle' => true], $leaving]));
        $beto = Staff::withoutGlobalScopes()->withTrashed()->findOrFail($this->beto->id);
        $booking->refresh();

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(mvIncoherent())->toBe([])
            ->and(mvOverlaps())->toBe(0);

        if ($beto->trashed() || ! $beto->is_active) {
            // Pausing does not look at the agenda, so it may follow the move;
            // a removal must never leave the visit with someone who is gone.
            expect($change === 'pause' && ! $changeFirst ? $this->beto->id : $this->ana->id)->toBe($booking->staff_id, $tally['all']);
        } else {
            expect($booking->staff_id)->toBe($this->beto->id, $tally['all']);
        }
    })->with([
        'removed first' => ['remove', true],
        'moved first, then removed' => ['remove', false],
        'paused first' => ['pause', true],
        'moved first, then paused' => ['pause', false],
    ]);

    /*
    | AddHoliday checks "nobody has a visit that day" and then inserts the
    | holiday, holding only the salon's row. A move reads the holidays under
    | the professional's lock. Neither waits for the other, so each can pass
    | its check before the other commits. The gate below stops the holiday
    | exactly between its check and its insert (an uncommitted row with the
    | same unique key), which is the window a real request can hit.
    */
    it('never leaves a visit on a day that becomes a holiday while it is being moved there', function () {
        $booking = mvBooking('10:00');
        $closed = mvAt('10:00', daysLater: 1);

        DB::statement('SET foreign_key_checks = 0');
        DB::beginTransaction();
        DB::table('salon_holidays')->insert(['salon_id' => $this->salon->id, 'date' => $closed->toDateString(), 'name' => 'gate']);

        $holiday = mvProcess(['op' => 'holiday', 'salon' => $this->salon->id, 'date' => $closed->toDateString()], 0.0);
        mvUntilQueued([$holiday]);
        // The move now waits for the holiday, which holds the professionals.
        $move = mvProcess(mvHttpMoveJob($booking, $closed), 0.0);
        mvUntilQueued([$move]);

        DB::rollBack();
        DB::statement('SET foreign_key_checks = 1');
        $tally = mvTally(mvResults([$holiday, $move]));

        expect($tally['error'])->toBe(0, $tally['all'])
            // Either the day closes or the visit moves there, not both.
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvStranded())->toBe([], $tally['all']);
    });

    it('never leaves a new booking on a day that becomes a holiday while it is being made', function () {
        $closed = mvAt('10:00', daysLater: 1);
        // Someone who already is a client of the salon, created before the
        // gate's transaction so the worker can see them.
        $user = mvUser();
        mvClient($user);
        $job = mvHttpBookJob($closed, $this->ana, $user);

        DB::statement('SET foreign_key_checks = 0');
        DB::beginTransaction();
        DB::table('salon_holidays')->insert(['salon_id' => $this->salon->id, 'date' => $closed->toDateString(), 'name' => 'gate']);

        $holiday = mvProcess(['op' => 'holiday', 'salon' => $this->salon->id, 'date' => $closed->toDateString()], 0.0);
        mvUntilQueued([$holiday]);
        // The booking reads the holidays, then waits for the salon's row to insert.
        $book = mvProcess($job, 0.0);
        mvUntilQueued([$book]);

        DB::rollBack();
        DB::statement('SET foreign_key_checks = 1');
        $tally = mvTally(mvResults([$holiday, $book]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvStranded())->toBe([], $tally['all']);
    });

    it('never leaves a visit on a holiday when the move and the holiday are sent at the same instant', function () {
        $stranded = [];

        foreach (range(1, 3) as $round) {
            DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
            SalonHoliday::withoutGlobalScopes()->where('salon_id', $this->salon->id)->delete();
            $closed = mvAt('10:00', daysLater: 1);
            $bookings = array_map(fn (string $time) => mvBooking($time), ['10:00', '11:00', '12:00', '13:00']);

            $tally = mvTally(mvAll([
                ['op' => 'holiday', 'salon' => $this->salon->id, 'date' => $closed->toDateString()],
                ...array_map(fn (Booking $booking) => mvHttpMoveJob($booking, $closed->setTimeFrom($booking->starts_at->setTimezone('America/Lima'))), $bookings),
            ]));

            expect($tally['error'])->toBe(0, $tally['all']);
            $stranded = [...$stranded, ...mvStranded()];
        }

        expect($stranded)->toBe([]);
    });
});

describe('the team books for clients at the same time', function () {
    it('gives the time to exactly one of two people at the desk, and keeps no record of the client who did not get it', function () {
        $tally = mvTally(mvBehindGate($this->ana, [
            mvAgendaJob(mvAt('10:00'), $this->ana, 'Doña Rosa', '987000111'),
            mvAgendaJob(mvAt('10:00'), $this->ana, 'Don Lucho', '987000222'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(Booking::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1)
            ->and(SalonClient::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1)
            ->and(mvOverlaps())->toBe(0);
    });

    it('gives the time to exactly one of six requests from the desk sent at the same instant', function () {
        $tally = mvTally(mvAll(array_map(
            fn (int $index) => mvAgendaJob(mvAt('10:00'), $this->ana, "Cliente {$index}", '98700033'.$index),
            range(1, 6),
        )));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(Booking::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1)
            ->and(SalonClient::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1)
            ->and(mvOverlaps())->toBe(0);
    });

    it('books both visits when the same new phone is entered twice at once for different times', function () {
        $tally = mvTally(mvAll([
            mvAgendaJob(mvAt('10:00'), $this->ana, 'Doña Rosa', '987000444'),
            mvAgendaJob(mvAt('11:00'), $this->ana, 'Doña Rosa', '987000444'),
            mvAgendaJob(mvAt('12:00'), $this->beto, 'Doña Rosa', '987000444'),
        ]));

        // Duplicate client records for one phone are tolerable here (no
        // unique index on the phone); losing a visit is not.
        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(3, $tally['all'])
            ->and(Booking::withoutGlobalScopes()->where('salon_id', $this->salon->id)->holdingSlot()->count())->toBe(3)
            ->and(SalonClient::withoutGlobalScopes()->where('salon_id', $this->salon->id)->where('phone', '987000444')->count())->toBeLessThanOrEqual(3);
    });

    /*
    | Request 1 creates the client record for a new phone and then loses its
    | time. Meanwhile request 2 found that record by the same phone and
    | booked with it. Request 1 "tidies up" the record it created, and the
    | cascade on bookings.salon_client_id takes request 2's visit with it.
    */
    it('keeps a visit booked for a client whose record another failed request had just created', function () {
        mvBooking('10:00', $this->ana);

        DB::beginTransaction();
        Staff::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->ana->id);

        // Asks for the time that is taken: it creates the record and queues for Ana.
        $loser = mvProcess(mvAgendaJob(mvAt('10:00'), $this->ana, 'Doña Rosa', '987000555'), 0.0);
        mvUntilQueued([$loser]);
        // Same phone, with Beto, who is free: booked and answered at once.
        $winner = mvResults([mvProcess(mvAgendaJob(mvAt('11:00'), $this->beto, 'Doña Rosa', '987000555'), 0.0)]);
        $bookedBefore = mvHoldersAt($this->beto, mvAt('11:00'));

        DB::commit();
        $tally = mvTally([...$winner, ...mvResults([$loser])]);

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($winner[0])->toStartWith('OK')
            ->and($bookedBefore)->toBe(1)
            // The team was told "Cita agendada": it must still be there.
            ->and(mvHoldersAt($this->beto, mvAt('11:00')))->toBe(1, 'the visit vanished: '.$tally['all']);
    });

    /*
    | The same on the public route (Public\BookingController::store): the
    | first request of a new client is refused and deletes "its" client
    | record, which a second request of the same account (another tab, a
    | retry) had already booked with.
    */
    it('keeps the booking a new client made in another tab while their first request was being refused', function () {
        $user = mvUser();
        mvBooking('10:00', $this->ana);

        DB::beginTransaction();
        Staff::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->ana->id);

        // First visit: creates the client record, then queues for Ana, whose 10:00 is taken.
        $loser = mvProcess(mvHttpBookJob(mvAt('10:00'), $this->ana, $user), 0.0);
        mvUntilQueued([$loser]);
        // The same account books 11:00 with Beto, who is free: done at once.
        $winner = mvResults([mvProcess(mvHttpBookJob(mvAt('11:00'), $this->beto, $user), 0.0)]);
        $bookedBefore = mvHoldersAt($this->beto, mvAt('11:00'));

        DB::commit();
        $tally = mvTally([...$winner, ...mvResults([$loser])]);

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($winner[0])->toStartWith('OK')
            ->and($bookedBefore)->toBe(1)
            // They were told "¡Reserva confirmada!": it must still be there.
            ->and(mvHoldersAt($this->beto, mvAt('11:00')))->toBe(1, 'the booking vanished: '.$tally['all']);
    });

    it('keeps the first booking of a new client when it is sent six times at the same instant', function () {
        $user = mvUser();

        $tally = mvTally(mvAll(array_map(fn () => mvHttpBookJob(mvAt('10:00'), $this->ana, $user), range(1, 6))));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(mvHoldersAt($this->ana, mvAt('10:00')))->toBe(1, 'the booking vanished: '.$tally['all']);
    });
});
