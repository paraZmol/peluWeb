<?php

namespace App\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The "when" of booking requests: the day whose free times are asked for,
 * and the start that was picked from them.
 *
 * @mixin FormRequest
 */
trait ParsesBookingTime
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function dayRules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d']];
    }

    /**
     * Unix seconds (UTC), exactly as the availability endpoint offered it.
     * Nothing after 2100-01-01 is a real appointment.
     *
     * @return array<string, array<int, string>>
     */
    protected function startRules(): array
    {
        return ['start' => ['required', 'integer', 'min:1', 'max:4102444800']];
    }

    /**
     * The requested calendar day on the given salon clock.
     */
    public function localDate(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('date')->toString(), $timezone)
            ?: CarbonImmutable::now($timezone);
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC($this->integer('start'));
    }
}
