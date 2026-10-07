<?php

use App\Enums\ScheduleAccess;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
    $this->staff = Staff::factory()->for($this->salon)->create();
});

function scheduleRange(int $weekday, string $start, string $end, string $access = 'public'): array
{
    return ['weekday' => $weekday, 'start' => $start, 'end' => $end, 'access_level' => $access];
}

/**
 * @return Collection<int, StaffSchedule>
 */
function schedulesOf(Staff $staff): Collection
{
    return StaffSchedule::withoutGlobalScopes()->where('staff_id', $staff->id)->orderBy('weekday')->orderBy('start_time')->get();
}

it('saves a week with a lunch break and a VIP range', function () {
    $this->actingAs($this->owner)
        ->put(route('staff.schedule.update', $this->staff), ['ranges' => [
            scheduleRange(1, '09:00', '13:00'),
            scheduleRange(1, '15:00', '20:00'),
            scheduleRange(6, '19:00', '21:00', 'vip'),
        ]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('staff.schedule.show', $this->staff));

    $saved = schedulesOf($this->staff);
    expect($saved)->toHaveCount(3)
        ->and($saved[0]->start())->toBe('09:00')
        ->and($saved[0]->salon_id)->toBe($this->salon->id)
        ->and($saved[2]->access_level)->toBe(ScheduleAccess::Vip);
});

it('replaces the previous week instead of adding to it', function () {
    Staff::factory()->for($this->salon)->working([1, 2, 3])->create();
    $staff = Staff::factory()->for($this->salon)->working([1, 2, 3])->create();

    $this->actingAs($this->owner)
        ->put(route('staff.schedule.update', $staff), ['ranges' => [scheduleRange(5, '10:00', '14:00')]]);

    expect(schedulesOf($staff)->pluck('weekday')->all())->toBe([5])
        ->and(StaffSchedule::withoutGlobalScopes()->count())->toBe(4);
});

it('accepts an empty week (the professional stops attending)', function () {
    $staff = Staff::factory()->for($this->salon)->working([1])->create();

    $this->actingAs($this->owner)->put(route('staff.schedule.update', $staff), ['ranges' => []])->assertSessionHasNoErrors();

    expect(schedulesOf($staff))->toHaveCount(0);
});

it('accepts ranges that touch but refuses ranges that overlap', function () {
    $this->actingAs($this->owner)->put(route('staff.schedule.update', $this->staff), ['ranges' => [
        scheduleRange(1, '09:00', '13:00'),
        scheduleRange(1, '13:00', '18:00'),
    ]]);
    expect(schedulesOf($this->staff))->toHaveCount(2);

    $this->actingAs($this->owner)->put(route('staff.schedule.update', $this->staff), ['ranges' => [
        scheduleRange(2, '09:00', '13:00'),
        scheduleRange(2, '12:55', '18:00'),
    ]]);

    expect(schedulesOf($this->staff)->pluck('weekday')->unique()->all())->toBe([1]);
});

it('validates each range', function (array $ranges, string $field) {
    $this->actingAs($this->owner)
        ->put(route('staff.schedule.update', $this->staff), ['ranges' => $ranges])
        ->assertSessionHasErrors($field);

    expect(schedulesOf($this->staff))->toHaveCount(0);
})->with([
    'ends before it starts' => [[scheduleRange(1, '18:00', '09:00')], 'ranges.0.end'],
    'shorter than 15 minutes' => [[scheduleRange(1, '09:00', '09:10')], 'ranges.0.end'],
    'not on the 5-minute grid' => [[scheduleRange(1, '09:03', '12:00')], 'ranges.0.start'],
    'weekday out of range' => [[scheduleRange(8, '09:00', '12:00')], 'ranges.0.weekday'],
    'not a time' => [[scheduleRange(1, '9am', '12:00')], 'ranges.0.start'],
    'unknown access level' => [[scheduleRange(1, '09:00', '12:00', 'friends')], 'ranges.0.access_level'],
    'five ranges in a day' => [[
        scheduleRange(1, '08:00', '09:00'), scheduleRange(1, '10:00', '11:00'), scheduleRange(1, '12:00', '13:00'),
        scheduleRange(1, '14:00', '15:00'), scheduleRange(1, '16:00', '17:00'),
    ], 'ranges'],
    'keyed instead of a list' => [[7 => scheduleRange(1, '09:00', '12:00')], 'ranges'],
]);

it('lets professionals edit their own hours but not a colleague\'s', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $own = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();
    $payload = ['ranges' => [scheduleRange(2, '10:00', '19:00')]];

    $this->actingAs($member->user)->get(route('staff.schedule.show', $own))->assertOk();
    $this->actingAs($member->user)->put(route('staff.schedule.update', $own), $payload)->assertSessionHasNoErrors();
    $this->actingAs($member->user)->get(route('staff.schedule.show', $this->staff))->assertForbidden();
    $this->actingAs($member->user)->put(route('staff.schedule.update', $this->staff), $payload)->assertForbidden();

    expect(schedulesOf($own))->toHaveCount(1)->and(schedulesOf($this->staff))->toHaveCount(0);
});

it('takes each person to their own schedule from "Mi horario"', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $own = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();

    $this->actingAs($member->user)->get(route('staff.schedule.mine'))->assertRedirect(route('staff.schedule.show', $own));
    $this->actingAs($this->owner)->get(route('staff.schedule.mine'))->assertRedirect(route('dashboard'));
});

it('shows the editor with ranges, access levels and what the viewer may do', function () {
    $staff = Staff::factory()->for($this->salon)->working([1], '09:00', '13:00', ScheduleAccess::Vip)->create();

    $this->actingAs($this->owner)
        ->get(route('staff.schedule.show', $staff))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/schedule')
            ->where('staff.ranges.0', ['weekday' => 1, 'start' => '09:00', 'end' => '13:00', 'accessLevel' => 'vip'])
            ->has('accessLevels', 3)
            ->where('canManageTeam', true)
            ->where('canSetPrices', true));
});

it('does not save hours for a suspended salon', function () {
    $this->salon->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($this->owner)
        ->put(route('staff.schedule.update', $this->staff), ['ranges' => [scheduleRange(1, '09:00', '13:00')]]);

    expect(schedulesOf($this->staff))->toHaveCount(0);
});
