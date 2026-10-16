<?php

use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
| The Libro de Reclamaciones numbers its sheets per year with no gaps and
| no repeats, even when several are filed at the same instant: real PHP
| processes against MySQL (worker complaint.php). No transaction wraps
| this test, so it cleans up after itself.
*/

const COMPLAINT_RACE_DOMAIN = '@race.example.test';

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
});

afterEach(function () {
    // Sheets are immutable for the app; a test cleans its own rows directly.
    DB::table('complaints')->where('email', 'like', '%'.COMPLAINT_RACE_DOMAIN)->delete();
    DB::table('complaint_counters')->delete();
});

it('hands out consecutive numbers to sheets filed at the same instant', function () {
    $goAt = microtime(true) + 4;
    $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];

    $processes = collect(range(1, 6))->map(function (int $i) use ($goAt, $env): Process {
        $process = new Process([PHP_BINARY, __DIR__.'/complaint.php', (string) $goAt, "persona{$i}".COMPLAINT_RACE_DOMAIN], base_path(), $env);
        $process->setTimeout(90);
        $process->start();

        return $process;
    });

    $results = $processes->map(function (Process $process): string {
        $process->wait();

        return trim($process->getOutput()).trim($process->getErrorOutput());
    })->all();

    expect($results)->each->toStartWith('OK:');
    $numbers = Complaint::query()->where('email', 'like', '%'.COMPLAINT_RACE_DOMAIN)->orderBy('number')->pluck('number')->all();
    expect($numbers)->toBe([1, 2, 3, 4, 5, 6]);
});
