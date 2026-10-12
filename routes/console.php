<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping: an expired hold already freed its slot; this records it.
Schedule::command('bookings:expire-holds')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
// Whatever opened a time up (a cancellation, a hold that ran out, new hours), this finds it.
Schedule::command('waitlist:offer')->everyMinute()->withoutOverlapping(5);
Schedule::command('clients:purge-expired-data')->dailyAt('03:30')->withoutOverlapping();

// API tokens past their 90 days are dead weight.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
