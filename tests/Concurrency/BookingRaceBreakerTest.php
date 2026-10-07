<?php

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
| Attempts to break the agenda with real processes: booking against
| cancelling, confirming, schedule changes, absences, removals and the
| HTTP route itself. No transaction wraps these tests (other processes
| must see the data), so each cleans up after itself.
|
| Workers print OK[:id], TAKEN or ERROR:… (see race.php). An ERROR is a
| failure on its own: it is a 500 for whoever sent the request.
*/

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    $this->salon = Salon::factory()->create(['min_lead_minutes' => 0, 'timezone' => 'America/Lima']);
    $this->service = Service::factory()->for($this->salon)->create(['active_minutes' => 30]);
    $this->users = collect();

    // Tomorrow 10:00 in Lima, whatever weekday that is.
    $this->start = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0);
    $this->staff = raceStaff($this->salon, $this->service, $this->start);
});

afterEach(function () {
    // A failed assertion must not leave the gate's lock behind.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
    $this->salon->forceDelete();
    User::whereIn('id', $this->users->pluck('id'))->delete();
});

function raceStaff(Salon $salon, Service $service, CarbonImmutable $start): Staff
{
    $staff = Staff::factory()->for($salon)->working([$start->dayOfWeekIso], '09:00', '18:00')->create();
    $staff->services()->attach($service->id);

    return $staff;
}

/**
 * @return list<SalonClient>
 */
function raceClients(int $count): array
{
    return SalonClient::factory()->for(test()->salon)->count($count)->create()->all();
}

function raceUser(): User
{
    $user = User::factory()->create();
    test()->users->push($user);

    return $user;
}

/**
 * A booking made before the race, straight through the action.
 */
function raceBooking(SalonClient $client, CarbonImmutable $start, ?Staff $staff = null): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client,
        selections: [['service_id' => test()->service->id, 'variant_id' => null]],
        staff: $staff ?? test()->staff,
        startsAt: $start,
        source: BookingSource::Client,
    ));
}

/**
 * @param  array<string, mixed>  $job
 */
function raceProcess(array $job, float $goAt): Process
{
    $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    $process = new Process([PHP_BINARY, __DIR__.'/race.php', (string) $goAt, base64_encode(json_encode($job, JSON_THROW_ON_ERROR))], base_path(), $env);
    $process->setTimeout(90);
    $process->start();

    return $process;
}

/**
 * @param  list<Process>  $processes
 * @return list<string>
 */
function raceResults(array $processes): array
{
    return array_map(function (Process $process): string {
        $process->wait();

        return trim($process->getOutput()).trim($process->getErrorOutput());
    }, $processes);
}

/**
 * Launch one worker per job, all firing at the same instant.
 *
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function raceAll(array $jobs): array
{
    $goAt = microtime(true) + 4;

    return raceResults(array_map(fn (array $job): Process => raceProcess($job, $goAt), $jobs));
}

/**
 * The deterministic version of a race: this process holds a row lock (the
 * professional's, or a client's) until every worker is queued behind it,
 * then lets go. No timing luck involved: all of them started while the
 * slot was free. A job with "settle" gets a head start over the next ones.
 *
 * @param  Staff|SalonClient|list<Staff|SalonClient>  $gate
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function raceBehindGate(Staff|SalonClient|array $gate, array $jobs): array
{
    DB::beginTransaction();

    foreach (is_array($gate) ? $gate : [$gate] as $row) {
        $row->newQueryWithoutScopes()->lockForUpdate()->findOrFail($row->id);
    }

    $processes = [];

    foreach ($jobs as $job) {
        $processes[] = raceProcess($job, 0.0);

        // A job flagged "settle" is left to queue before the next one starts.
        if ($job['settle'] ?? false) {
            sleep(4);
        }
    }

    $deadline = microtime(true) + 20;
    $waiting = 0;

    while ($waiting < count($jobs) && microtime(true) < $deadline) {
        usleep(200_000);

        try {
            $waiting = (int) DB::connection()->scalar("select count(*) from information_schema.innodb_trx where trx_state = 'LOCK WAIT'");
        } catch (Throwable) {
            // No privilege to look: give the workers time to boot and queue instead.
            sleep(8);

            break;
        }
    }

    DB::commit();

    return raceResults($processes);
}

/**
 * Pairs of busy stretches of one professional that overlap, among the
 * bookings of the salon that hold their slot right now. Must be zero.
 */
function raceOverlaps(Salon $salon): int
{
    $holding = fn (string $alias): string => "({$alias}.status in ('confirmed', 'in_service') or ({$alias}.status = 'pending' and {$alias}.hold_expires_at > utc_timestamp()))";

    return (int) DB::scalar(
        'select count(*) from booking_segments a
           join booking_segments b on a.staff_id = b.staff_id and a.id < b.id and a.starts_at < b.ends_at and b.starts_at < a.ends_at
           join bookings ba on ba.id = a.booking_id
           join bookings bb on bb.id = b.booking_id
          where ba.salon_id = ? and ba.id <> bb.id and '.$holding('ba').' and '.$holding('bb'),
        [$salon->id],
    );
}

/**
 * @param  list<string>  $results
 * @return array{ok: int, taken: int, error: int, all: string}
 */
function raceTally(array $results): array
{
    return [
        'ok' => count(array_filter($results, fn (string $result) => str_starts_with($result, 'OK'))),
        'taken' => count(array_filter($results, fn (string $result) => $result === 'TAKEN')),
        'error' => count(array_filter($results, fn (string $result) => ! str_starts_with($result, 'OK') && $result !== 'TAKEN')),
        'all' => implode(' | ', $results),
    ];
}

function raceBookJob(SalonClient $client, CarbonImmutable $start, ?Staff $staff, ?Service $service = null): array
{
    return [
        'op' => 'book',
        'salon' => test()->salon->id,
        'client' => $client->id,
        'service' => ($service ?? test()->service)->id,
        'staff' => $staff?->id ?? 0,
        'start' => $start->getTimestamp(),
    ];
}

function raceHttpJob(User $user, CarbonImmutable $start, ?Staff $staff): array
{
    return [
        'op' => 'http',
        'salon' => test()->salon->id,
        'user' => $user->id,
        'service' => test()->service->id,
        'staff' => $staff?->id ?? 0,
        'start' => $start->getTimestamp(),
    ];
}

describe('the real HTTP route', function () {
    it('control: the action alone gives the slot to exactly one of the people queued behind the lock', function () {
        $jobs = array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), raceClients(3));

        $tally = raceTally(raceBehindGate($this->staff, $jobs));

        expect($tally['ok'])->toBe(1, $tally['all'])
            ->and($tally['error'])->toBe(0, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('gives the slot to exactly one of the people who POST the booking while it was free', function () {
        $jobs = array_map(fn () => raceHttpJob(raceUser(), $this->start, $this->staff), range(1, 3));

        $tally = raceTally(raceBehindGate($this->staff, $jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(Booking::withoutGlobalScopes()->where('staff_id', $this->staff->id)->count())->toBe(1)
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('gives the slot to exactly one when the action runs inside a transaction that had already read (what the controller does)', function () {
        $jobs = array_map(fn (SalonClient $client) => [...raceBookJob($client, $this->start, $this->staff), 'wrap' => true], raceClients(3));

        $tally = raceTally(raceBehindGate($this->staff, $jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('gives the slot to exactly one of six people who POST the booking at the same instant', function () {
        $jobs = array_map(fn () => raceHttpJob(raceUser(), $this->start, $this->staff), range(1, 6));

        $tally = raceTally(raceAll($jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('answers both people when "anyone" sends each of them to the professional the other one is trying', function () {
        $tomorrow = $this->start;
        $dayAfter = $this->start->addDay();
        $first = Staff::factory()->for($this->salon)->working([$tomorrow->dayOfWeekIso, $dayAfter->dayOfWeekIso])->create(['position' => 1]);
        $second = Staff::factory()->for($this->salon)->working([$tomorrow->dayOfWeekIso, $dayAfter->dayOfWeekIso])->create(['position' => 2]);
        $first->services()->attach($this->service->id);
        $second->services()->attach($this->service->id);
        $this->staff->forceFill(['is_active' => false])->save();

        // Tomorrow the first one is the least busy but away at 10:00; the day after it is the other way round.
        raceBooking(raceClients(1)[0], $tomorrow->addHour(), $second);
        raceBooking(raceClients(1)[0], $dayAfter->addHour(), $first);
        StaffTimeOff::factory()->for($first)->create(['starts_at' => $tomorrow->subHour()->utc(), 'ends_at' => $tomorrow->addMinutes(30)->utc()]);
        StaffTimeOff::factory()->for($second)->create(['starts_at' => $dayAfter->subHour()->utc(), 'ends_at' => $dayAfter->addMinutes(30)->utc()]);

        [$ana, $beto] = [raceUser(), raceUser()];
        $gate = [
            SalonClient::factory()->for($this->salon)->forUser($ana)->create(),
            SalonClient::factory()->for($this->salon)->forUser($beto)->create(),
        ];

        // Each request locks its first choice and stops at its client row
        // (held here). Both then need the professional the other one holds.
        $tally = raceTally(raceBehindGate($gate, [raceHttpJob($ana, $tomorrow, null), raceHttpJob($beto, $dayAfter, null)]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('keeps the cap of five upcoming bookings when one account POSTs six at once', function () {
        $user = raceUser();
        SalonClient::factory()->for($this->salon)->forUser($user)->create();
        $jobs = array_map(fn (int $index) => raceHttpJob($user, $this->start->addMinutes(30 * $index), $this->staff), range(0, 5));

        $tally = raceTally(raceBehindGate($this->staff, $jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(CreateBooking::MAX_UPCOMING_PER_CLIENT, $tally['all']);
    });
});

describe('booking against other changes of the same agenda', function () {
    it('never books a slot twice while its booking is being cancelled', function () {
        $racers = raceClients(6);
        $holder = array_shift($racers);
        $existing = raceBooking($holder, $this->start);

        $tally = raceTally(raceAll([
            ['op' => 'cancel', 'booking' => $existing->id],
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), $racers),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0)
            ->and(Booking::withoutGlobalScopes()->where('staff_id', $this->staff->id)->holdingSlot()->count())->toBeLessThanOrEqual(1, $tally['all'])
            ->and($existing->refresh()->status)->toBe(BookingStatus::Cancelled);
    });

    it('lets either the late confirmation of an expired hold or one new booking have the slot, never both', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        $racers = raceClients(5);
        $holder = array_shift($racers);
        $hold = raceBooking($holder, $this->start);
        DB::table('bookings')->where('id', $hold->id)->update(['hold_expires_at' => now()->subMinute()]);

        $tally = raceTally(raceAll([
            ['op' => 'confirm', 'booking' => $hold->id],
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), $racers),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0)
            ->and(Booking::withoutGlobalScopes()->where('staff_id', $this->staff->id)->holdingSlot()->count())->toBe(1);
    });

    it('lets exactly one of "expire" and "confirm" win on the same expired hold', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        $hold = raceBooking(raceClients(1)[0], $this->start);
        DB::table('bookings')->where('id', $hold->id)->update(['hold_expires_at' => now()->subMinute()]);

        $tally = raceTally(raceAll([
            ['op' => 'confirm', 'booking' => $hold->id],
            ['op' => 'expire', 'booking' => $hold->id],
            ['op' => 'confirm', 'booking' => $hold->id],
            ['op' => 'expire', 'booking' => $hold->id],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(1, $tally['all'])
            ->and($hold->refresh()->status)->toBeIn([BookingStatus::Confirmed, BookingStatus::Expired])
            // Created, then exactly one change.
            ->and($hold->events()->count())->toBe(2);
    });

    it('cancels once and counts one no-show when the same button is pressed several times at once', function () {
        [$first, $second] = raceClients(2);
        $toCancel = raceBooking($first, $this->start);
        // Already started: only then can it be marked as a no-show.
        $missed = raceBooking($second, $this->start->addHour());
        DB::table('bookings')->where('id', $missed->id)->update(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(30)]);

        $tally = raceTally(raceAll([
            ['op' => 'cancel', 'booking' => $toCancel->id],
            ['op' => 'cancel', 'booking' => $toCancel->id, 'byClient' => true],
            ['op' => 'cancel', 'booking' => $toCancel->id],
            ['op' => 'noshow', 'booking' => $missed->id],
            ['op' => 'noshow', 'booking' => $missed->id],
            ['op' => 'noshow', 'booking' => $missed->id],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and($toCancel->events()->count())->toBe(2)
            ->and($missed->events()->count())->toBe(2)
            ->and($second->refresh()->no_show_count)->toBe(1);
    });

    it('answers every request (no deadlock) when hours are replaced while people book them', function () {
        $day = $this->start->dayOfWeekIso;

        $tally = raceTally(raceAll([
            ['op' => 'schedule', 'staff' => $this->staff->id, 'ranges' => [['weekday' => $day, 'start' => '14:00', 'end' => '18:00', 'access' => 'public']]],
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), raceClients(4)),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBeLessThanOrEqual(2, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('answers every request (no deadlock) when an absence is added over the slot being booked', function () {
        $tally = raceTally(raceAll([
            ['op' => 'timeoff', 'staff' => $this->staff->id, 'from' => $this->start->subHour()->getTimestamp(), 'to' => $this->start->addHours(2)->getTimestamp()],
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), raceClients(4)),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBeLessThanOrEqual(2, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('answers every request (no deadlock) when the professional is removed while people book them', function () {
        $tally = raceTally(raceAll([
            ['op' => 'remove', 'staff' => $this->staff->id],
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), raceClients(4)),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBeLessThanOrEqual(2, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('answers every request (no deadlock) when the professional is paused while people book them', function () {
        $errors = [];

        // The window is narrow: several rounds, each with fresh bookers.
        foreach (range(1, 3) as $round) {
            DB::table('staff')->where('id', $this->staff->id)->update(['is_active' => true]);
            DB::table('bookings')->where('salon_id', $this->salon->id)->delete();

            $tally = raceTally(raceAll([
                ['op' => 'pause', 'staff' => $this->staff->id],
                ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, $this->staff), raceClients(5)),
            ]));

            if ($tally['error'] > 0) {
                $errors[] = "round {$round}: {$tally['all']}";
            }

            expect($tally['ok'])->toBeLessThanOrEqual(2, $tally['all']);
        }

        expect($errors)->toBe([]);
    });

    it('answers both sides when the owner pauses a professional in the middle of a booking for them', function () {
        $client = raceClients(1)[0];

        // The booker locks the professional and stops at the client's row
        // (held here); the pause locks the salon and queues for the
        // professional. Letting go must not end in a deadlock.
        $tally = raceTally(raceBehindGate($client, [
            [...raceBookJob($client, $this->start, $this->staff), 'settle' => true],
            ['op' => 'pause', 'staff' => $this->staff->id],
        ]));

        expect($tally['error'])->toBe(0, $tally['all']);
    });

    it('never overlaps two visits when the duration of the service changes while they are booked', function () {
        [$first, $second, $third, $fourth] = raceClients(4);

        $tally = raceTally(raceAll([
            ['op' => 'sync', 'staff' => $this->staff->id, 'entries' => [['service_id' => $this->service->id, 'active_minutes' => 60, 'price_cents' => null]]],
            raceBookJob($first, $this->start, $this->staff),
            raceBookJob($second, $this->start->addMinutes(30), $this->staff),
            raceBookJob($third, $this->start->addMinutes(60), $this->staff),
            raceBookJob($fourth, $this->start->addMinutes(90), $this->staff),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])->and(raceOverlaps($this->salon))->toBe(0, $tally['all']);
    });
});

describe('many people, many professionals', function () {
    it('books each of three professionals once when eight people ask for "anyone" at the same time', function () {
        raceStaff($this->salon, $this->service, $this->start);
        raceStaff($this->salon, $this->service, $this->start);

        $tally = raceTally(raceAll(array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, null), raceClients(8))));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(3, $tally['all'])
            ->and($tally['taken'])->toBe(5, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('never overlaps visits that only partly collide (10:00 for 60 min against 10:30 for 30 min) with "anyone"', function () {
        raceStaff($this->salon, $this->service, $this->start);
        $long = Service::factory()->for($this->salon)->create(['active_minutes' => 60]);
        Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->get()->each(fn (Staff $staff) => $staff->services()->attach($long->id));
        $clients = raceClients(8);

        $tally = raceTally(raceAll([
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start, null, $long), array_slice($clients, 0, 4)),
            ...array_map(fn (SalonClient $client) => raceBookJob($client, $this->start->addMinutes(30), null), array_slice($clients, 4)),
        ]));

        // Two professionals: at 10:30 at most two visits can be under way.
        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(raceOverlaps($this->salon))->toBe(0);
    });

    it('keeps the cap of five upcoming bookings when one client fires six requests at once', function () {
        $client = raceClients(1)[0];
        $second = raceStaff($this->salon, $this->service, $this->start);

        // Different professionals too, so the requests are not serialized by one lock.
        $jobs = array_map(
            fn (int $index) => raceBookJob($client, $this->start->addMinutes(30 * intdiv($index, 2)), $index % 2 === 0 ? $this->staff : $second),
            range(0, 5),
        );

        $tally = raceTally(raceAll($jobs));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(CreateBooking::MAX_UPCOMING_PER_CLIENT, $tally['all'])
            ->and(Booking::withoutGlobalScopes()->where('salon_client_id', $client->id)->count())->toBe(CreateBooking::MAX_UPCOMING_PER_CLIENT);
    });
});
