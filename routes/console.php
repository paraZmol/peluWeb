<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping: an expired hold already freed its slot; this records it.
Schedule::command('bookings:expire-holds')->everyFiveMinutes()->withoutOverlapping();
