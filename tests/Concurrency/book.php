<?php

/*
| Worker for ConcurrentBookingTest: one real PHP process that tries to book
| a slot at an agreed instant. Prints OK:<id>, TAKEN or ERROR:<message>.
|
|   php book.php <salon> <client> <service> <staff|0> <start-unix> <go-at-unix-float>
*/

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Exceptions\BookingException;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $salonId, $clientId, $serviceId, $staffId, $start, $goAt] = $argv;

try {
    $salon = Salon::findOrFail((int) $salonId);
    $data = new NewBooking(
        salon: $salon,
        client: SalonClient::withoutGlobalScope('salon')->findOrFail((int) $clientId),
        selections: [['service_id' => (int) $serviceId, 'variant_id' => null]],
        staff: (int) $staffId === 0 ? null : Staff::withoutGlobalScope('salon')->findOrFail((int) $staffId),
        startsAt: CarbonImmutable::createFromTimestampUTC((int) $start),
        source: BookingSource::Client,
    );

    // Everyone fires at the same instant, with the app already booted.
    $wait = (float) $goAt - microtime(true);

    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }

    echo 'OK:'.app(CreateBooking::class)->handle($data)->id;
} catch (BookingException) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR:'.$e::class.' '.$e->getMessage();
}
