<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** Transactions CreateBooking allows itself for one booking (its DEADLOCK_ATTEMPTS). */
const BRK_RETRY_BUDGET = 3;

/*
| booking-breaker against the promotions engine (step 6.7 a) under real
| concurrency: places ("cupos") and "one per client" with real PHP
| processes against MySQL (workers: race.php and money.php). No transaction
| wraps these tests, so each cleans up after itself.
|
| Tests named "BUG:" fail on purpose: each one is a finding.
|
| Corte costs S/ 35 and the promotion takes 20 % off. Ana and Beto both
| work tomorrow, 9 to 18 (Lima). The salon asks no deposit, so a booking is
| born confirmed.
*/

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    $this->users = collect();
    $this->owner = brkRaceUser();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['min_lead_minutes' => 0, 'timezone' => 'America/Lima', 'deposit_percent' => 0]);
    $this->service = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->start = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0);
    $this->ana = brkRaceStaff('Ana');
    $this->beto = brkRaceStaff('Beto');
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

function brkRaceUser(): User
{
    $user = User::factory()->create();
    test()->users->push($user);

    return $user;
}

function brkRaceStaff(string $name): Staff
{
    $start = test()->start;
    $staff = Staff::factory()->for(test()->salon)->working([$start->dayOfWeekIso, CarbonImmutable::now('America/Lima')->dayOfWeekIso], '00:00', '23:45')->create(['name' => $name]);
    $staff->services()->attach(test()->service->id);

    return $staff;
}

function brkRaceClient(?User $user = null): SalonClient
{
    return SalonClient::factory()->for(test()->salon)->create(['user_id' => $user?->id]);
}

/**
 * @param  array<string, mixed>  $with
 */
function brkRacePromo(array $with = []): Promotion
{
    $today = CarbonImmutable::now('America/Lima');
    $promotion = new Promotion;
    $promotion->forceFill([
        'salon_id' => test()->salon->id,
        'mechanic' => PromotionMechanic::Discount,
        'card' => 'discount',
        'name' => '20 % menos en Corte',
        'status' => PromotionStatus::Published,
        'starts_on' => $today->subDay()->toDateString(),
        'ends_on' => $today->addDays(10)->toDateString(),
        'percent' => 20,
        'share_code' => Str::random(10),
        ...$with,
    ])->save();
    $promotion->services()->sync([test()->service->id]);

    return $promotion;
}

/**
 * A booking through the action (race.php "book").
 *
 * @return array<string, mixed>
 */
function brkRaceBook(SalonClient $client, Staff $staff, string $time = '10:00', string $source = 'client'): array
{
    return [
        'worker' => 'race.php',
        'op' => 'book',
        'salon' => test()->salon->id,
        'client' => $client->id,
        'service' => test()->service->id,
        'staff' => $staff->id,
        'start' => test()->start->setTimeFromTimeString($time)->getTimestamp(),
        'source' => $source,
    ];
}

/**
 * The POST a browser sends to book online (race.php "http"): the account
 * may have no client record in the salon yet.
 *
 * @return array<string, mixed>
 */
function brkRaceHttpBook(User $user, Staff $staff, string $time = '10:00'): array
{
    return [
        'worker' => 'race.php',
        'op' => 'http',
        'salon' => test()->salon->id,
        'user' => $user->id,
        'service' => test()->service->id,
        'staff' => $staff->id,
        'start' => test()->start->setTimeFromTimeString($time)->getTimestamp(),
    ];
}

/**
 * A POST of the owner through the HTTP kernel (money.php "http").
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function brkRaceOwnerPost(string $uri, array $data = []): array
{
    return ['worker' => 'money.php', 'op' => 'http', 'user' => test()->owner->id, 'uri' => $uri, 'data' => $data];
}

/**
 * @param  array<string, mixed>  $job
 */
function brkRaceProcess(array $job, float $goAt): Process
{
    $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    $process = new Process([PHP_BINARY, __DIR__.'/'.$job['worker'], (string) $goAt, base64_encode(json_encode($job, JSON_THROW_ON_ERROR))], base_path(), $env);
    $process->setTimeout(90);
    $process->start();

    return $process;
}

/**
 * @param  list<Process>  $processes
 * @return list<string>
 */
function brkRaceResults(array $processes): array
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
function brkRaceAll(array $jobs): array
{
    $goAt = microtime(true) + 5;

    return brkRaceResults(array_map(fn (array $job): Process => brkRaceProcess($job, $goAt), $jobs));
}

/**
 * The deterministic race: this process holds row locks until every worker
 * waits behind them (or behind each other), then lets go. No timing luck:
 * all of them started while the promotion had room. A job with "settle"
 * gets a head start over the next ones.
 *
 * @param  Model|list<Model>  $gate
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function brkRaceBehindGate(Model|array $gate, array $jobs): array
{
    DB::beginTransaction();

    foreach (is_array($gate) ? $gate : [$gate] as $row) {
        $row->newQueryWithoutScopes()->lockForUpdate()->findOrFail($row->getKey());
    }

    $processes = [];

    foreach ($jobs as $job) {
        $processes[] = brkRaceProcess($job, 0.0);

        if ($job['settle'] ?? false) {
            sleep(5);
        }
    }

    $deadline = microtime(true) + 25;
    $waiting = 0;

    while ($waiting < count($jobs) && microtime(true) < $deadline) {
        usleep(200_000);

        try {
            // Only this database's sessions: other test lanes share the server.
            $waiting = (int) DB::connection()->scalar(
                "select count(*) from information_schema.innodb_trx t
                   join information_schema.processlist p on p.id = t.trx_mysql_thread_id
                  where t.trx_state = 'LOCK WAIT' and p.db = database()",
            );
        } catch (Throwable) {
            // No privilege to look: give the workers time to boot and queue instead.
            sleep(8);

            break;
        }
    }

    DB::commit();

    return brkRaceResults($processes);
}

/**
 * @param  list<string>  $results
 * @return array{ok: int, taken: int, error: int, all: string}
 */
function brkRaceTally(array $results): array
{
    return [
        'ok' => count(array_filter($results, fn (string $result) => str_starts_with($result, 'OK'))),
        'taken' => count(array_filter($results, fn (string $result) => $result === 'TAKEN')),
        'error' => count(array_filter($results, fn (string $result) => ! str_starts_with($result, 'OK') && ! in_array($result, ['TAKEN', 'DONE'], true))),
        'all' => implode(' | ', $results),
    ];
}

/**
 * Bookings of the salon that hold a place of the promotion right now.
 */
function brkRaceTaken(Promotion $promotion, ?SalonClient $client = null): int
{
    return Booking::withoutGlobalScopes()
        ->where('promotion_id', $promotion->id)
        ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::InService, BookingStatus::Completed])
        ->when($client !== null, fn ($query) => $query->where('salon_client_id', $client?->id))
        ->count();
}

function brkRaceBookings(): int
{
    return Booking::withoutGlobalScopes()->where('salon_id', test()->salon->id)->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])->count();
}

/**
 * Pairs of busy stretches of one professional that overlap. Must be zero.
 */
function brkRaceOverlaps(): int
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

describe('1. the last place of a promotion', function () {
    /*
    | CRÍTICO. ApplyPromotion::hasRoom locks the promotion's row and then
    | counts with a plain SELECT. Under REPEATABLE READ that count sees the
    | world as of the transaction's first plain read (QuoteBooking::services,
    | long before the lock), so the second booking does not see the first,
    | which committed while it waited: both take the single place.
    | The lock only serializes; it does not make the count current.
    | The same lesson as 2026-10-08 (skill booking-domain: "las lecturas bajo
    | bloqueo son lecturas bloqueantes").
    */
    it('fixed: gives the single place to two clients the team books with two different professionals while it was free', function () {
        $promotion = brkRacePromo(['quota' => 1]);

        $tally = brkRaceTally(brkRaceBehindGate($promotion, [
            brkRaceBook(brkRaceClient(), $this->ana, '10:00', 'staff'),
            brkRaceBook(brkRaceClient(), $this->beto, '10:00', 'staff'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(brkRaceOverlaps())->toBe(0)
            ->and(brkRaceTaken($promotion))->toBe(1, 'bookings holding the single place of the promotion');
    });

    /*
    | The same two bookings made online. Each of them has counted its
    | client's bookings with a locking read (CreateBooking::ensureWithinLimitsOf)
    | before it waits for the promotion, and those range locks make the
    | two inserts wait on each other: MySQL kills one and CreateBooking
    | retries it. When the retry starts after the winner committed the
    | count is right; when it starts before, the place is given twice.
    | Either the place count or the transactions tell.
    */
    it('gives the single place once to two online clients with two professionals', function () {
        $promotion = brkRacePromo(['quota' => 1]);
        $viaPromo = fn (array $job): array => [...$job, 'worker' => 'promo.php'];

        $tally = brkRaceTally(brkRaceBehindGate($promotion, [
            $viaPromo(brkRaceBook(brkRaceClient(), $this->ana)),
            $viaPromo(brkRaceBook(brkRaceClient(), $this->beto)),
        ]));
        preg_match_all('/tx(\d+)/', $tally['all'], $transactions);

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(brkRaceOverlaps())->toBe(0)
            ->and(brkRaceTaken($promotion))->toBe(1, "places taken: {$tally['all']}")
            ->and(max(array_map(intval(...), $transactions[1])))->toBeLessThanOrEqual(BRK_RETRY_BUDGET, $tally['all']);
    });

    /*
    | BAJO, and older than promotions (no promotion here; the gate is the
    | salon's row, which the booking's insert reads): two online bookings
    | of different clients with different professionals already wait on
    | each other. Both have counted their client's bookings with
    | sharedLock() (CreateBooking::ensureWithinLimitsOf), which locks the
    | gap of bookings(salon_client_id) where the other one must insert.
    | The retry hides it; promotions turn that retry into a second place.
    */
    it('known, older than promotions: two online clients with two professionals may deadlock; the retry books both', function () {
        $viaPromo = fn (array $job): array => [...$job, 'worker' => 'promo.php'];

        $tally = brkRaceTally(brkRaceBehindGate($this->salon, [
            $viaPromo(brkRaceBook(brkRaceClient(), $this->ana)),
            $viaPromo(brkRaceBook(brkRaceClient(), $this->beto)),
        ]));
        preg_match_all('/tx(\d+)/', $tally['all'], $transactions);

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(max(array_map(intval(...), $transactions[1])))->toBeLessThanOrEqual(BRK_RETRY_BUDGET, $tally['all']);
    });

    it('fixed: gives the single place more than once when six bookings arrive at the same instant', function () {
        $promotion = brkRacePromo(['quota' => 1]);
        $rounds = [];

        foreach (range(1, 3) as $round) {
            DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
            $same = brkRaceClient(brkRaceUser());

            $tally = brkRaceTally(brkRaceAll([
                brkRaceBook(brkRaceClient(), $this->ana, '10:00'),
                brkRaceBook(brkRaceClient(), $this->beto, '10:00'),
                // The same hour of Ana again: one of the two must lose it.
                brkRaceBook(brkRaceClient(), $this->ana, '10:00'),
                brkRaceBook($same, $this->ana, '11:00'),
                brkRaceBook($same, $this->beto, '11:00', 'staff'),
                brkRaceHttpBook(brkRaceUser(), $this->beto, '12:00'),
            ]));

            $rounds[] = ['errors' => $tally['error'], 'booked' => $tally['ok'], 'overlaps' => brkRaceOverlaps(), 'with promotion' => brkRaceTaken($promotion), 'all' => $tally['all']];
        }

        expect(array_sum(array_column($rounds, 'errors')))->toBe(0, json_encode($rounds))
            ->and(array_sum(array_column($rounds, 'overlaps')))->toBe(0, json_encode($rounds))
            ->and(array_column($rounds, 'booked'))->toBe([5, 5, 5], json_encode($rounds))
            ->and(array_column($rounds, 'with promotion'))->toBe([1, 1, 1], json_encode($rounds));
    });

    it('gives the single place once when two clients book different hours of the same professional', function () {
        $promotion = brkRacePromo(['quota' => 1]);

        // Behind Ana's row: the first statement of each booking, before anything is read.
        $tally = brkRaceTally(brkRaceBehindGate($this->ana, [
            brkRaceBook(brkRaceClient(), $this->ana, '10:00'),
            brkRaceBook(brkRaceClient(), $this->ana, '11:00'),
            brkRaceBook(brkRaceClient(), $this->ana, '10:00'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(brkRaceOverlaps())->toBe(0)
            ->and(brkRaceTaken($promotion))->toBe(1);
    });

    it('never ends above the quota when a place is freed by a cancellation while others book', function () {
        $promotion = brkRacePromo(['quota' => 1]);
        $holder = app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->salon->refresh(),
            client: brkRaceClient(),
            selections: [['service_id' => $this->service->id, 'variant_id' => null]],
            staff: $this->ana,
            startsAt: $this->start->setTime(15, 0),
            source: BookingSource::Client,
        ));
        expect($holder->promotion_id)->toBe($promotion->id);

        $tally = brkRaceTally(brkRaceAll([
            brkRaceOwnerPost("/agenda/{$holder->id}/cancelar"),
            brkRaceBook(brkRaceClient(), $this->ana, '10:00'),
            brkRaceBook(brkRaceClient(), $this->beto, '10:00'),
            brkRaceBook(brkRaceClient(), $this->beto, '11:00'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($holder->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and(brkRaceBookings())->toBe(3, $tally['all'])
            ->and(brkRaceTaken($promotion))->toBeLessThanOrEqual(1, $tally['all']);
    });
});

describe('2. one per client', function () {
    it('gives it once when the same client books two professionals online at once', function () {
        $promotion = brkRacePromo(['once_per_client' => true]);
        $client = brkRaceClient(brkRaceUser());

        // Online bookings take the client's row before reading anything.
        $tally = brkRaceTally(brkRaceBehindGate($client, [
            brkRaceBook($client, $this->ana, '10:00'),
            brkRaceBook($client, $this->beto, '11:00'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(brkRaceTaken($promotion, $client))->toBe(1);
    });

    // ALTO. The same stale count as above. Bookings made by the team do not
    // take the client's row first, so nothing else serializes them.
    it('fixed: gives it twice when two people of the team book the same client with two professionals', function () {
        $promotion = brkRacePromo(['once_per_client' => true]);
        $client = brkRaceClient();

        $tally = brkRaceTally(brkRaceBehindGate($promotion, [
            brkRaceBook($client, $this->ana, '10:00', 'staff'),
            brkRaceBook($client, $this->beto, '11:00', 'staff'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(brkRaceTaken($promotion, $client))->toBe(1, 'bookings of one client with the "one per client" promotion');
    });

    /*
    | ALTO. Someone who never booked here: the record is stored inside the
    | transaction (CreateBooking::client). The second request finds it
    | already there (unique key), locks it and goes on to count — with the
    | view it had before the first booking was committed. Two tabs, two
    | professionals, the same instant; left to chance, four rounds (3 of 4
    | gave it twice on 2026-10-12). One client record every time: that part
    | holds.
    */
    it('fixed: gives it twice to a brand new client who sends two bookings at the same instant (HTTP)', function () {
        $promotion = brkRacePromo(['once_per_client' => true]);
        $rounds = [];

        foreach (range(1, 4) as $round) {
            $newcomer = brkRaceUser();

            $tally = brkRaceTally(brkRaceAll([
                brkRaceHttpBook($newcomer, $this->ana, (9 + $round).':00'),
                brkRaceHttpBook($newcomer, $this->beto, (9 + $round).':00'),
            ]));
            $records = SalonClient::withoutGlobalScopes()->where('salon_id', $this->salon->id)->where('user_id', $newcomer->id)->get();

            $rounds[] = ['errors' => $tally['error'], 'booked' => $tally['ok'], 'records' => $records->count(), 'with promotion' => $records->sum(fn (SalonClient $record) => brkRaceTaken($promotion, $record)), 'all' => $tally['all']];
        }

        expect(array_sum(array_column($rounds, 'errors')))->toBe(0, json_encode($rounds))
            ->and(array_column($rounds, 'booked'))->toBe([2, 2, 2, 2], json_encode($rounds))
            ->and(array_column($rounds, 'records'))->toBe([1, 1, 1, 1], json_encode($rounds))
            ->and(array_column($rounds, 'with promotion'))->toBe([1, 1, 1, 1], json_encode($rounds));
    });
});

describe('lock order: professional, client, booking, promotion, salon', function () {
    /*
    | BAJO, and older than promotions: the same deadlock shows with and
    | without a promotion (the two tests below). A booking made by the team
    | does not lock the client: it inserts the booking and that insert reads
    | the client's row (foreign key). An online booking of the same client
    | holds the client's row and counts their bookings with sharedLock(),
    | which stops at the row the team's booking just inserted. MySQL kills
    | one; the retry in CreateBooking hides it from the user.
    |
    | Promotions add one more edge of the same shape, which these tests
    | cannot tell apart from the old one: the promotion's row is taken
    | BEFORE the booking is inserted, so the team's booking holds
    | promotion → wants client while the online one holds client → wants
    | promotion. The declared order says client before promotion.
    |
    | Seen through promo.php: a booking that needed a second transaction
    | was the victim of a deadlock.
    */
    it('known, older than promotions: a team booking and an online one of the same client may deadlock; both end booked, with a promotion', function () {
        brkRacePromo(['once_per_client' => true]);
        $client = brkRaceClient(brkRaceUser());
        $viaPromo = fn (array $job): array => [...$job, 'worker' => 'promo.php'];

        // The online one queues for the client first; the team's takes the promotion and then queues for the client too.
        $results = brkRaceBehindGate($client, [
            [...$viaPromo(brkRaceBook($client, $this->ana, '10:00')), 'settle' => true],
            $viaPromo(brkRaceBook($client, $this->beto, '11:00', 'staff')),
        ]);
        $tally = brkRaceTally($results);
        preg_match_all('/tx(\d+)/', $tally['all'], $transactions);

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(max(array_map(intval(...), $transactions[1])))->toBeLessThanOrEqual(BRK_RETRY_BUDGET, "transactions each booking needed: {$tally['all']}");
    });

    it('known, older than promotions: the same two bookings of one client, without any promotion, both end booked', function () {
        $client = brkRaceClient(brkRaceUser());
        $viaPromo = fn (array $job): array => [...$job, 'worker' => 'promo.php'];

        $tally = brkRaceTally(brkRaceBehindGate($client, [
            [...$viaPromo(brkRaceBook($client, $this->ana, '10:00')), 'settle' => true],
            $viaPromo(brkRaceBook($client, $this->beto, '11:00', 'staff')),
        ]));
        preg_match_all('/tx(\d+)/', $tally['all'], $transactions);

        expect($tally['ok'])->toBe(2, $tally['all'])->and(max(array_map(intval(...), $transactions[1])))->toBeLessThanOrEqual(BRK_RETRY_BUDGET, $tally['all']);
    });

    it('control: two bookings of different clients with a promotion need one transaction each', function () {
        brkRacePromo(['quota' => 5, 'once_per_client' => true]);
        $viaPromo = fn (array $job): array => [...$job, 'worker' => 'promo.php'];

        $tally = brkRaceTally(brkRaceBehindGate($this->ana, [
            $viaPromo(brkRaceBook(brkRaceClient(), $this->ana, '10:00')),
            $viaPromo(brkRaceBook(brkRaceClient(), $this->ana, '11:00', 'staff')),
        ]));
        preg_match_all('/tx(\d+)/', $tally['all'], $transactions);

        expect($tally['ok'])->toBe(2, $tally['all'])->and(array_map(intval(...), $transactions[1]))->toBe([1, 1], $tally['all']);
    });

    it('answers everyone when bookings with a promotion race charges, a cancellation and the till', function () {
        $promotion = brkRacePromo(['quota' => 3]);
        $book = fn (string $time, Staff $staff): Booking => app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->salon->refresh(),
            client: brkRaceClient(),
            selections: [['service_id' => $this->service->id, 'variant_id' => null]],
            staff: $staff,
            startsAt: $this->start->setTimeFromTimeString($time),
            source: BookingSource::Client,
        ));
        $problems = [];

        foreach (range(1, 2) as $round) {
            foreach (['cash_sessions', 'payments', 'bookings'] as $table) {
                DB::table($table)->where('salon_id', $this->salon->id)->delete();
            }

            // One in the chair right now (S/ 28 with the promotion), one to cancel.
            $inService = $book('15:00', $this->ana);
            $times = ['starts_at' => now()->utc(), 'ends_at' => now()->utc()->addMinutes(30)];
            DB::table('bookings')->where('id', $inService->id)->update($times);
            DB::table('booking_segments')->where('booking_id', $inService->id)->update($times);
            $inService = app(ChangeBookingStatus::class)->start($inService->refresh(), $this->owner);
            $toCancel = $book('16:00', $this->beto);

            $tally = brkRaceTally(brkRaceAll([
                brkRaceOwnerPost("/agenda/{$inService->id}/completar", ['payments' => [['method' => 'cash', 'amount' => '28.00']]]),
                brkRaceOwnerPost("/agenda/{$toCancel->id}/cancelar"),
                brkRaceOwnerPost('/caja/abrir', ['amount' => '50']),
                brkRaceBook(brkRaceClient(), $this->ana, '10:00'),
                brkRaceBook(brkRaceClient(), $this->beto, '10:00'),
                brkRaceBook(brkRaceClient(), $this->ana, '11:00', 'staff'),
                brkRaceHttpBook(brkRaceUser(), $this->beto, '11:00'),
            ]));
            $inService->refresh();

            if ($tally['error'] > 0 || $inService->status !== BookingStatus::Completed || $inService->charged_cents !== 2800
                || $toCancel->refresh()->status !== BookingStatus::Cancelled || brkRaceBookings() !== 4 || brkRaceOverlaps() !== 0) {
                $problems[] = "round {$round}: {$tally['all']} — in service: {$inService->status->value} charged ".var_export($inService->charged_cents, true).', live bookings '.brkRaceBookings();
            }
        }

        expect($problems)->toBe([]);
    });
});
