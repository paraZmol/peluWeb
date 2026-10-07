<?php

use App\Enums\TimeOffReason;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A Wednesday morning in Lima (UTC-5).
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
    $this->staff = Staff::factory()->for($this->salon)->create();
});

function timeOffPayload(array $overrides = []): array
{
    return [
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-14',
        'all_day' => true,
        'reason' => 'vacation',
        'note' => 'Viaje',
        ...$overrides,
    ];
}

it('stores whole days as instants on the salon clock', function () {
    $this->actingAs($this->owner)
        ->post(route('staff.timeoff.store', $this->staff), timeOffPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('staff.schedule.show', $this->staff));

    $timeOff = StaffTimeOff::withoutGlobalScopes()->firstOrFail();
    expect($timeOff->starts_at->toDateTimeString())->toBe('2026-10-12 05:00:00')
        ->and($timeOff->ends_at->toDateTimeString())->toBe('2026-10-15 05:00:00')
        ->and($timeOff->reason)->toBe(TimeOffReason::Vacation)
        ->and($timeOff->salon_id)->toBe($this->salon->id);
});

it('stores a block of a few hours', function () {
    $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), timeOffPayload([
        'starts_on' => '2026-10-07', 'ends_on' => '2026-10-07',
        'all_day' => false, 'start_time' => '13:00', 'end_time' => '15:30', 'reason' => 'block',
    ]))->assertSessionHasNoErrors();

    $timeOff = StaffTimeOff::withoutGlobalScopes()->firstOrFail();
    expect($timeOff->starts_at->toDateTimeString())->toBe('2026-10-07 18:00:00')
        ->and($timeOff->ends_at->toDateTimeString())->toBe('2026-10-07 20:30:00');
});

it('shows upcoming time off back on the salon clock and hides what already ended', function () {
    StaffTimeOff::factory()->for($this->staff)->create([
        'starts_at' => '2026-10-12 05:00:00', 'ends_at' => '2026-10-15 05:00:00',
    ]);
    StaffTimeOff::factory()->for($this->staff)->create([
        'starts_at' => '2026-09-01 05:00:00', 'ends_at' => '2026-09-02 05:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get(route('staff.schedule.show', $this->staff))
        ->assertInertia(fn (Assert $page) => $page
            ->has('staff.timeOff', 1)
            ->where('staff.timeOff.0.startsOn', '2026-10-12')
            ->where('staff.timeOff.0.endsOn', '2026-10-14')
            ->where('staff.timeOff.0.allDay', true)
            ->where('today', '2026-10-07'));
});

it('validates the period', function (array $override, string $field) {
    $this->actingAs($this->owner)
        ->post(route('staff.timeoff.store', $this->staff), timeOffPayload($override))
        ->assertSessionHasErrors($field);

    expect(StaffTimeOff::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'ends before it starts' => [['ends_on' => '2026-10-10'], 'ends_on'],
    'in the past' => [['starts_on' => '2026-09-01', 'ends_on' => '2026-09-02'], 'starts_on'],
    'longer than a year' => [['ends_on' => '2027-12-01'], 'ends_on'],
    'hours missing' => [['all_day' => false], 'start_time'],
    'returns before leaving' => [['starts_on' => '2026-10-12', 'ends_on' => '2026-10-12', 'all_day' => false, 'start_time' => '15:00', 'end_time' => '13:00'], 'end_time'],
    'unknown reason' => [['reason' => 'party'], 'reason'],
    'markup in the note' => [['note' => '<i>x</i>'], 'note'],
]);

it('lets professionals manage their own time off only', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $own = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();
    $colleagueTimeOff = StaffTimeOff::factory()->for($this->staff)->create();

    $this->actingAs($member->user)->post(route('staff.timeoff.store', $own), timeOffPayload())->assertSessionHasNoErrors();
    $this->actingAs($member->user)->post(route('staff.timeoff.store', $this->staff), timeOffPayload())->assertForbidden();
    $this->actingAs($member->user)
        ->delete(route('staff.timeoff.destroy', [$this->staff, $colleagueTimeOff]))
        ->assertForbidden();

    expect(StaffTimeOff::withoutGlobalScopes()->where('staff_id', $own->id)->count())->toBe(1)
        ->and($colleagueTimeOff->fresh())->not->toBeNull();
});

it('does not delete a colleague\'s time off through one\'s own URL', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $own = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();
    $colleagueTimeOff = StaffTimeOff::factory()->for($this->staff)->create();

    $this->actingAs($member->user)
        ->delete(route('staff.timeoff.destroy', [$own, $colleagueTimeOff]))
        ->assertNotFound();

    expect($colleagueTimeOff->fresh())->not->toBeNull();
});

it('hides time off of another salon', function () {
    $foreignStaff = Staff::factory()->for(Salon::factory()->ownedBy()->create())->create();
    $foreign = StaffTimeOff::factory()->for($foreignStaff)->create();

    $this->actingAs($this->owner)->delete(route('staff.timeoff.destroy', [$this->staff, $foreign]))->assertNotFound();
    $this->actingAs($this->owner)->post(route('staff.timeoff.store', $foreignStaff), timeOffPayload())->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

it('deletes time off', function () {
    $timeOff = StaffTimeOff::factory()->for($this->staff)->create();

    $this->actingAs($this->owner)->delete(route('staff.timeoff.destroy', [$this->staff, $timeOff]));

    expect($timeOff->fresh())->toBeNull();
});

it('adds a salon holiday once per date and lists upcoming ones', function () {
    $this->actingAs($this->owner)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad'])
        ->assertRedirect(route('staff.index'));
    $this->actingAs($this->owner)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad (cerrado)']);

    expect(SalonHoliday::withoutGlobalScopes()->count())->toBe(1);

    $this->actingAs($this->owner)->get(route('staff.index'))->assertInertia(fn (Assert $page) => $page
        ->has('holidays', 1)
        ->where('holidays.0.date', '2026-12-25')
        ->where('holidays.0.name', 'Navidad (cerrado)'));
});

it('validates holidays', function (array $payload, string $field) {
    $this->actingAs($this->owner)->post(route('holidays.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'past date' => [['date' => '2026-01-01', 'name' => 'Año nuevo'], 'date'],
    'not a date' => [['date' => 'mañana', 'name' => 'Cierre'], 'date'],
    'no name' => [['date' => '2026-12-25', 'name' => ''], 'name'],
    'markup' => [['date' => '2026-12-25', 'name' => '[link](x)'], 'name'],
]);

it('keeps holidays for owners and inside their own salon', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $foreign = new SalonHoliday;
    $foreign->forceFill(['salon_id' => Salon::factory()->ownedBy()->create()->id, 'date' => '2026-12-25', 'name' => 'Ajeno'])->save();

    $this->actingAs($member->user)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad'])->assertForbidden();
    $this->actingAs($this->owner)->delete(route('holidays.destroy', $foreign))->assertNotFound();

    expect(SalonHoliday::withoutGlobalScopes()->count())->toBe(1);
});
