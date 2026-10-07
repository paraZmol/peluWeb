<?php

use App\Enums\ScheduleAccess;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Wednesday (ISO 3), 10:00 in Lima = 15:00 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    $this->salon = Salon::factory()->ownedBy()->create(['slug' => 'barberia-lucho']);
});

it('shows no hours until some professional has a schedule', function () {
    Staff::factory()->for($this->salon)->create();

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page->where('salon.openingHours', null));
});

it('shows the union of the team hours and that the salon is open now', function () {
    Staff::factory()->for($this->salon)->working([3], '09:00', '13:00')->create();
    Staff::factory()->for($this->salon)->working([3, 4], '12:00', '20:00')->create();

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->has('salon.openingHours.days', 7)
        ->where('salon.openingHours.days.2', ['weekday' => 3, 'ranges' => [['start' => '09:00', 'end' => '20:00']]])
        ->where('salon.openingHours.days.0.ranges', [])
        ->where('salon.openingHours.today', 3)
        ->where('salon.openingHours.status.isOpen', true)
        ->where('salon.openingHours.status.time', '20:00'));
});

it('uses the salon clock, not the server clock', function () {
    Staff::factory()->for($this->salon)->working([3], '09:00', '13:00')->create();

    // 13:30 in Lima is still Wednesday 18:30 UTC: closed, opens next week.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 13:30', 'America/Lima'));

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->where('salon.openingHours.status.isOpen', false)
        ->where('salon.openingHours.status.daysAhead', 7)
        ->where('salon.openingHours.status.time', '09:00'));
});

it('ignores paused and removed professionals and other salons', function () {
    Staff::factory()->for($this->salon)->inactive()->working([3])->create();
    Staff::factory()->for($this->salon)->working([3])->create()->delete();
    Staff::factory()->for(Salon::factory()->create())->working([3])->create();

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page->where('salon.openingHours', null));
});

it('is closed on a holiday and says why', function () {
    Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create();
    $holiday = new SalonHoliday;
    $holiday->forceFill(['salon_id' => $this->salon->id, 'date' => '2026-10-07', 'name' => 'Aniversario'])->save();

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->where('salon.openingHours.closedToday', 'Aniversario')
        ->where('salon.openingHours.status.isOpen', false)
        ->where('salon.openingHours.status.daysAhead', 1));
});

it('does not reveal which hours are VIP or staff only', function () {
    Staff::factory()->for($this->salon)->working([3], '09:00', '18:00', ScheduleAccess::Vip)->create();

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->where('salon.openingHours.days.2.ranges', [['start' => '09:00', 'end' => '18:00']])
        ->missing('salon.openingHours.days.2.ranges.0.accessLevel'));
});

it('marks the schedule step of the setup checklist once hours exist', function () {
    $owner = $this->salon->members()->firstOrFail()->user;
    $step = fn () => collect($this->actingAs($owner)->get(route('dashboard'))->inertiaProps('setup'))->firstWhere('key', 'schedule');

    expect($step()['done'])->toBeFalse();

    Staff::factory()->for($this->salon)->working([1])->create();

    expect($step()['done'])->toBeTrue();
});
