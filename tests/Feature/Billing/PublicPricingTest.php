<?php

use App\Models\Plan;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 7.2: the landing reads its prices from the plans table and shows
| the founder offer only while it has places and before its last day.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 2, 'billing.founder.ends_on' => '2026-12-31']);
});

it('shows the public plans with their prices and the founder places left', function () {
    Salon::factory()->create(['founder_since' => now()]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->has('pricing.plans', 2)
        ->where('pricing.plans.0', ['code' => 'solo', 'name' => 'Solo', 'priceCents' => 3900, 'founderPriceCents' => 2900, 'maxStaff' => 1])
        ->where('pricing.plans.1.code', 'equipo')
        ->where('pricing.founder', ['slotsLeft' => 1, 'endsOn' => '2026-12-31']));
});

it('follows a price changed in the platform panel', function () {
    Plan::query()->where('code', 'equipo')->update(['price_cents' => 7900]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.plans.1.priceCents', 7900));
});

it('drops the founder offer once its places run out or its last day passed', function () {
    Salon::factory()->count(2)->create(['founder_since' => now()]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.founder', null));

    Salon::query()->update(['founder_since' => null]);
    $this->travelTo(CarbonImmutable::parse('2027-01-01 10:00', 'America/Lima'));
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.founder', null));
});
