<?php

use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
| The invariant that matters most: real processes racing for the same
| professional and time end with exactly one booking. No transaction wraps
| these tests (other processes must see the data), so each cleans up.
*/

const RACERS = 6;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    $this->salon = Salon::factory()->create(['min_lead_minutes' => 0, 'timezone' => 'America/Lima']);
    $this->service = Service::factory()->for($this->salon)->create(['active_minutes' => 30]);
    $this->clients = SalonClient::factory()->for($this->salon)->count(RACERS)->create();

    // Tomorrow 10:00 in Lima, whatever weekday that is.
    $this->start = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0);
});

afterEach(function () {
    DB::table('bookings')->where('salon_id', $this->salon->id)->delete();
    $this->salon->forceDelete();
});

function workingTomorrow(Salon $salon, Service $service, CarbonImmutable $start): Staff
{
    $staff = Staff::factory()->for($salon)->working([$start->dayOfWeekIso], '09:00', '18:00')->create();
    $staff->services()->attach($service->id);

    return $staff;
}

/**
 * Launch one worker per client at the same instant and collect what each printed.
 *
 * @return list<string>
 */
function race(Salon $salon, Service $service, ?Staff $staff, CarbonImmutable $start, iterable $clients): array
{
    $goAt = microtime(true) + 4;
    $php = PHP_BINARY;
    $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array'];

    $processes = [];

    foreach ($clients as $client) {
        $process = new Process(
            [$php, __DIR__.'/book.php', $salon->id, $client->id, $service->id, $staff?->id ?? 0, $start->getTimestamp(), $goAt],
            base_path(),
            $env,
        );
        $process->setTimeout(60);
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $process): string {
        $process->wait();

        return trim($process->getOutput()).trim($process->getErrorOutput());
    }, $processes);
}

it('gives a slot to exactly one of several people booking it at the same instant', function () {
    $staff = workingTomorrow($this->salon, $this->service, $this->start);

    $results = race($this->salon, $this->service, $staff, $this->start, $this->clients);

    $won = array_filter($results, fn (string $result) => str_starts_with($result, 'OK:'));
    $lost = array_filter($results, fn (string $result) => $result === 'TAKEN');

    expect($won)->toHaveCount(1, implode(' | ', $results))
        ->and($lost)->toHaveCount(RACERS - 1, implode(' | ', $results))
        ->and(Booking::withoutGlobalScopes()->where('staff_id', $staff->id)->count())->toBe(1)
        ->and(DB::table('booking_segments')->where('staff_id', $staff->id)->count())->toBe(1);
});

it('fills each professional once when everyone asks for "anyone"', function () {
    $first = workingTomorrow($this->salon, $this->service, $this->start);
    $second = workingTomorrow($this->salon, $this->service, $this->start);

    $results = race($this->salon, $this->service, null, $this->start, $this->clients);

    $won = array_filter($results, fn (string $result) => str_starts_with($result, 'OK:'));

    expect($won)->toHaveCount(2, implode(' | ', $results))
        ->and(Booking::withoutGlobalScopes()->where('staff_id', $first->id)->count())->toBe(1)
        ->and(Booking::withoutGlobalScopes()->where('staff_id', $second->id)->count())->toBe(1);
});
