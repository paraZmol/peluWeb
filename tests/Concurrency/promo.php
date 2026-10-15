<?php

/*
| Worker for PromotionRaceBreakerTest: one real PHP process that makes one
| booking through CreateBooking and tells how many transactions it took.
| More than one for a booking with a named professional means MySQL broke
| a deadlock and CreateBooking retried: nobody saw an error, but two
| requests were waiting on each other.
|
|   OK:<id>:tx<n>   booked, in n transactions
|   TAKEN:tx<n>     a business rule said no
|   ERROR:…         an exception: a failure on its own
|
|   php promo.php <go-at-unix-float> <base64 json>
*/

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Exceptions\BusinessRuleException;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $goAt, $payload] = $argv;

/** @var array<string, mixed> $job */
$job = json_decode((string) base64_decode($payload, true), true, 16, JSON_THROW_ON_ERROR);
$transactions = 0;

try {
    $data = new NewBooking(
        salon: Salon::findOrFail($job['salon']),
        client: SalonClient::withoutGlobalScope('salon')->findOrFail($job['client']),
        selections: [['service_id' => $job['service'], 'variant_id' => null]],
        staff: Staff::withoutGlobalScope('salon')->findOrFail($job['staff']),
        startsAt: CarbonImmutable::createFromTimestampUTC($job['start']),
        source: BookingSource::from($job['source'] ?? 'client'),
    );

    Event::listen(function (TransactionBeginning $event) use (&$transactions): void {
        $transactions++;
    });

    $wait = (float) $goAt - microtime(true);

    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }

    echo 'OK:'.app(CreateBooking::class)->handle($data)->id.':tx'.$transactions;
} catch (BusinessRuleException) {
    echo 'TAKEN:tx'.$transactions;
} catch (Throwable $e) {
    echo 'ERROR:'.$e::class.' '.substr($e->getMessage(), 0, 300);
}
