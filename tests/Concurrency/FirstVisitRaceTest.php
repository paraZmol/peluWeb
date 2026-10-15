<?php

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

/*
| booking-breaker against "Primera visita" (step 6.7 d) under real
| concurrency: real PHP processes (workers race.php and promo.php) against
| MySQL. Only someone with no live booking at the salon gets it, so two
| "first" bookings of one person at once must end with one price only.
| No transaction wraps these tests: each cleans up after itself.
|
| Corte S/ 35, 20 % off on the first visit. Ana and Beto work tomorrow and
| today, all day (Lima).
*/

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    $this->users = collect();
    $this->owner = fvrUser();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['min_lead_minutes' => 0, 'timezone' => 'America/Lima', 'deposit_percent' => 0]);
    $this->service = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->start = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0);
    $this->ana = fvrStaff('Ana');
    $this->beto = fvrStaff('Beto');
});

afterEach(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
    $this->salon->forceDelete();
    User::whereIn('id', $this->users->pluck('id'))->delete();
});

function fvrUser(): User
{
    $user = User::factory()->create();
    test()->users->push($user);

    return $user;
}

function fvrStaff(string $name): Staff
{
    $staff = Staff::factory()->for(test()->salon)->working([test()->start->dayOfWeekIso, CarbonImmutable::now('America/Lima')->dayOfWeekIso], '00:00', '23:45')->create(['name' => $name]);
    $staff->services()->attach(test()->service->id);

    return $staff;
}

function fvrClient(?User $user = null): SalonClient
{
    return SalonClient::factory()->for(test()->salon)->create(['user_id' => $user?->id]);
}

/**
 * @param  array<string, mixed>  $with
 */
function fvrPromo(array $with = []): Promotion
{
    $today = CarbonImmutable::now('America/Lima');
    $promotion = new Promotion;
    $promotion->forceFill([
        'salon_id' => test()->salon->id,
        'mechanic' => PromotionMechanic::Discount,
        'card' => 'first_visit',
        'name' => '20 % menos en Corte en tu primera visita',
        'status' => PromotionStatus::Published,
        'starts_on' => $today->subDay()->toDateString(),
        'ends_on' => $today->addDays(10)->toDateString(),
        'percent' => 20,
        'new_clients_only' => true,
        'share_code' => Str::random(10),
        ...$with,
    ])->save();
    $promotion->services()->sync([test()->service->id]);

    return $promotion;
}

/**
 * @return array<string, mixed>
 */
function fvrBook(SalonClient $client, Staff $staff, string $time, string $source = 'client'): array
{
    return [
        'worker' => 'promo.php',
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
 * The POST of the booking wizard, for an account that may have no record yet.
 *
 * @return array<string, mixed>
 */
function fvrHttpBook(User $user, Staff $staff, string $time): array
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
 * @param  array<string, mixed>  $job
 */
function fvrProcess(array $job, float $goAt): Process
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
function fvrResults(array $processes): array
{
    return array_map(function (Process $process): string {
        $process->wait();

        return trim(str_replace('READY', '', $process->getOutput())).trim($process->getErrorOutput());
    }, $processes);
}

/**
 * All of them fire at the same agreed instant.
 *
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function fvrAll(array $jobs): array
{
    $goAt = microtime(true) + 5;

    return fvrResults(array_map(fn (array $job): Process => fvrProcess($job, $goAt), $jobs));
}

/**
 * This process holds the rows until every worker queues behind them, runs
 * $meanwhile inside that same transaction, and commits: every worker
 * started from the world as it was before.
 *
 * @param  list<Model>  $gate
 * @param  list<array<string, mixed>>  $jobs
 * @return list<string>
 */
function fvrBehindGate(array $gate, array $jobs, ?Closure $meanwhile = null): array
{
    DB::beginTransaction();

    foreach ($gate as $row) {
        $row->newQueryWithoutScopes()->lockForUpdate()->findOrFail($row->getKey());
    }

    $processes = array_map(fn (array $job): Process => fvrProcess($job, 0.0), $jobs);
    $deadline = microtime(true) + 25;
    $waiting = 0;
    // The jobs that do not queue behind the gate must be done before it opens.
    $passing = array_filter($processes, fn (Process $process, int $i): bool => $jobs[$i]['passes'] ?? false, ARRAY_FILTER_USE_BOTH);

    foreach ($passing as $process) {
        $process->wait();
    }

    while ($waiting < count($jobs) - count($passing) && microtime(true) < $deadline) {
        usleep(200_000);

        try {
            $waiting = (int) DB::connection()->scalar(
                "select count(*) from information_schema.innodb_trx t
                   join information_schema.processlist p on p.id = t.trx_mysql_thread_id
                  where t.trx_state = 'LOCK WAIT' and p.db = database()",
            );
        } catch (Throwable) {
            sleep(8);

            break;
        }
    }

    if ($meanwhile !== null) {
        $meanwhile();
    }

    DB::commit();

    return fvrResults($processes);
}

/**
 * @param  list<string>  $results
 * @return array{ok: int, taken: int, error: int, all: string}
 */
function fvrTally(array $results): array
{
    return [
        'ok' => count(array_filter($results, fn (string $result) => str_starts_with($result, 'OK'))),
        'taken' => count(array_filter($results, fn (string $result) => str_starts_with($result, 'TAKEN'))),
        'error' => count(array_filter($results, fn (string $result) => ! str_starts_with($result, 'OK') && ! str_starts_with($result, 'TAKEN'))),
        'all' => implode(' | ', $results),
    ];
}

/**
 * Bookings of the client (or of the salon) at the first-visit price that
 * still count: a pending one whose hold ran out does not.
 */
function fvrWithPromotion(Promotion $promotion, ?SalonClient $client = null): int
{
    return Booking::withoutGlobalScopes()
        ->where('promotion_id', $promotion->id)
        ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
        ->where(fn ($live) => $live->where('status', '!=', BookingStatus::Pending)->orWhereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
        ->when($client !== null, fn ($query) => $query->where('salon_client_id', $client?->id))
        ->count();
}

/**
 * Her first booking with Ana, at the first-visit price, whose hold has just run out.
 */
function fvrExpiredFirstVisit(Promotion $promotion, SalonClient $client, string $time): Booking
{
    $booking = app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client,
        selections: [['service_id' => test()->service->id, 'variant_id' => null]],
        staff: test()->ana,
        startsAt: test()->start->setTimeFromTimeString($time),
        source: BookingSource::Client,
    ));
    expect($booking->promotion_id)->toBe($promotion->id)->and($booking->status)->toBe(BookingStatus::Pending);
    DB::table('bookings')->where('id', $booking->id)->update(['hold_expires_at' => now()->subMinute()]);

    return $booking->refresh();
}

describe('two first bookings of one person at once', function () {
    it('gives the price once when a client with a record books two professionals online', function () {
        $promotion = fvrPromo();
        $client = fvrClient(fvrUser());

        $tally = fvrTally(fvrBehindGate([$client], [
            fvrBook($client, $this->ana, '10:00'),
            fvrBook($client, $this->beto, '11:00'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(fvrWithPromotion($promotion, $client))->toBe(1, $tally['all']);
    });

    it('gives the price once to a brand new account sending two bookings at the same instant (HTTP)', function () {
        $promotion = fvrPromo();
        $rounds = [];

        foreach (range(1, 3) as $round) {
            $newcomer = fvrUser();

            $tally = fvrTally(fvrAll([
                fvrHttpBook($newcomer, $this->ana, (9 + $round).':00'),
                fvrHttpBook($newcomer, $this->beto, (9 + $round).':00'),
            ]));
            $records = SalonClient::withoutGlobalScopes()->where('salon_id', $this->salon->id)->where('user_id', $newcomer->id)->get();

            $rounds[] = [
                'errors' => $tally['error'],
                'booked' => $tally['ok'],
                'records' => $records->count(),
                'with promotion' => $records->sum(fn (SalonClient $record) => fvrWithPromotion($promotion, $record)),
                'all' => $tally['all'],
            ];
        }

        expect(array_sum(array_column($rounds, 'errors')))->toBe(0, json_encode($rounds))
            ->and(array_column($rounds, 'booked'))->toBe([2, 2, 2], json_encode($rounds))
            ->and(array_column($rounds, 'records'))->toBe([1, 1, 1], json_encode($rounds))
            ->and(array_column($rounds, 'with promotion'))->toBe([1, 1, 1], json_encode($rounds));
    });

    it('gives the price once when two people of the team book the same new client with two professionals', function () {
        $promotion = fvrPromo();
        $client = fvrClient();

        // Behind the promotion's row: both have already read the client as new.
        $tally = fvrTally(fvrBehindGate([$promotion], [
            fvrBook($client, $this->ana, '10:00', 'staff'),
            fvrBook($client, $this->beto, '11:00', 'staff'),
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['ok'])->toBe(2, $tally['all'])
            ->and(fvrWithPromotion($promotion, $client))->toBe(1, $tally['all']);
    });

    it('gives the price once when the team and the client book at the same instant', function () {
        $promotion = fvrPromo();
        $rounds = [];

        foreach (range(1, 3) as $round) {
            $client = fvrClient(fvrUser());

            $tally = fvrTally(fvrAll([
                fvrBook($client, $this->ana, (9 + $round).':00'),
                fvrBook($client, $this->beto, (9 + $round).':00', 'staff'),
            ]));

            $rounds[] = ['errors' => $tally['error'], 'booked' => $tally['ok'], 'with promotion' => fvrWithPromotion($promotion, $client), 'all' => $tally['all']];
        }

        expect(array_sum(array_column($rounds, 'errors')))->toBe(0, json_encode($rounds))
            ->and(array_column($rounds, 'booked'))->toBe([2, 2, 2], json_encode($rounds))
            ->and(array_column($rounds, 'with promotion'))->toBe([1, 1, 1], json_encode($rounds));
    });
});

describe('the rules change while someone books', function () {
    it('does not give a promotion that became first-visit-only to someone who already came', function () {
        $promotion = fvrPromo(['new_clients_only' => false, 'card' => 'discount']);
        $regular = fvrClient(fvrUser());
        $past = new Booking;
        $past->forceFill([
            'salon_id' => $this->salon->id, 'code' => Str::upper(Str::random(8)), 'salon_client_id' => $regular->id,
            'staff_id' => $this->ana->id, 'status' => BookingStatus::Completed, 'source' => 'staff',
            'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addMinutes(30), 'price_cents' => 3500,
        ])->save();

        // They read it as open to everyone; it turns first-visit-only before they lock it.
        $tally = fvrTally(fvrBehindGate([$promotion], [fvrBook($regular, $this->ana, '10:00', 'staff')], function () use ($promotion): void {
            DB::table('promotions')->where('id', $promotion->id)->update(['new_clients_only' => true]);
        }));

        expect($tally['ok'])->toBe(1, $tally['all'])
            ->and(fvrWithPromotion($promotion, $regular))->toBe(0, $tally['all']);
    });

    /*
    | ALTO, fixed 2026-10-14. Her first booking's hold ran out, so it stopped
    | counting and her new booking took the first-visit price; the team
    | then confirmed the old one late and she had two "first visits".
    | ApplyPromotion::stillHasRoomFor did not ask whether the client booked
    | again. Deterministic: the confirmation queues behind Ana's row while
    | the new booking (with Beto) goes through and commits.
    */
    it('refuses a late deposit at the first-visit price once the client booked again', function () {
        $promotion = fvrPromo();
        $this->salon->forceFill(['deposit_percent' => 50])->save();
        $user = fvrUser();
        $client = fvrClient($user);
        $first = fvrExpiredFirstVisit($promotion, $client, '16:00');

        $tally = fvrTally(fvrBehindGate([$this->ana], [
            ['worker' => 'race.php', 'op' => 'confirm', 'booking' => $first->id],
            [...fvrHttpBook($user, $this->beto, '10:00'), 'passes' => true],
        ]));

        expect($tally['error'])->toBe(0, $tally['all'])
            ->and($tally['all'])->toStartWith('TAKEN')
            ->and($first->refresh()->status)->toBe(BookingStatus::Pending)
            ->and(fvrWithPromotion($promotion, $client))->toBe(1, $tally['all']);
    });

    it('never leaves two first-visit prices when a late deposit is confirmed while the client books again', function () {
        $promotion = fvrPromo();
        $this->salon->forceFill(['deposit_percent' => 50])->save();
        $rounds = [];

        foreach (range(1, 3) as $round) {
            $user = fvrUser();
            $client = fvrClient($user);
            $first = fvrExpiredFirstVisit($promotion, $client, (15 + $round).':00');

            $tally = fvrTally(fvrAll([
                ['worker' => 'race.php', 'op' => 'confirm', 'booking' => $first->id],
                fvrHttpBook($user, $this->beto, (9 + $round).':00'),
            ]));

            $rounds[] = ['errors' => $tally['error'], 'with promotion' => fvrWithPromotion($promotion, $client), 'all' => $tally['all']];
        }

        expect(array_sum(array_column($rounds, 'errors')))->toBe(0, json_encode($rounds))
            ->and(array_column($rounds, 'with promotion'))->toBe([1, 1, 1], json_encode($rounds));
    });
});
