<?php

use App\Enums\SalonPermission;
use App\Enums\SalonStatus;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Support\CurrentSalon;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| QA of phase 3 (staff, weekly hours, time off, holidays, per-professional
| services, public opening hours). Black-box: roles, tenants, clocks, limits.
| Helper names carry the `p3` prefix because Pest helpers are global.
*/

beforeEach(function () {
    // Wednesday 10:00 in Lima (UTC-5) = 15:00 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'qa-tres']);
});

/** A team member of the salon, optionally with a professional profile. */
function p3Member(Salon $salon, bool $withProfile = true, SalonPermission ...$permissions): array
{
    $user = User::factory()->create();
    SalonMember::factory()->for($salon)->for($user)->withPermissions(...$permissions)->create();
    $staff = $withProfile ? Staff::factory()->for($salon)->linkedTo($user)->create() : null;

    return [$user, $staff];
}

function p3Range(int $weekday, string $start, string $end, string $access = 'public'): array
{
    return ['weekday' => $weekday, 'start' => $start, 'end' => $end, 'access_level' => $access];
}

function p3TimeOff(array $overrides = []): array
{
    return ['starts_on' => '2026-10-12', 'ends_on' => '2026-10-14', 'all_day' => true, 'reason' => 'vacation', ...$overrides];
}

function p3Pivots(Staff $staff): Collection
{
    return DB::table('service_staff')->where('staff_id', $staff->id)->get()->keyBy('service_id');
}

// ---------------------------------------------------------------- roles

describe('roles', function () {
    it('sends visitors to log in on every phase 3 route', function () {
        $staff = Staff::factory()->for($this->salon)->create();

        $this->get('/profesionales')->assertRedirect('/login');
        $this->get('/mi-horario')->assertRedirect('/login');
        $this->get(route('staff.schedule.show', $staff))->assertRedirect('/login');
        $this->put(route('staff.schedule.update', $staff), ['ranges' => []])->assertRedirect('/login');
        $this->post(route('staff.timeoff.store', $staff), p3TimeOff())->assertRedirect('/login');
        $this->put(route('staff.services.update', $staff), ['services' => []])->assertRedirect('/login');
        $this->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad'])->assertRedirect('/login');
        $this->post(route('staff.store'), ['name' => 'Colado'])->assertRedirect('/login');
    });

    it('lets a super admin or a client without salon nowhere near a salon\'s professionals', function (string $kind) {
        $staff = Staff::factory()->for($this->salon)->working([1])->create();
        $user = User::factory()->create($kind === 'admin' ? ['is_platform_admin' => true] : []);

        $this->actingAs($user)->get('/profesionales')->assertRedirect(route('salons.create'));
        $this->actingAs($user)->get('/mi-horario')->assertRedirect(route('salons.create'));
        $this->actingAs($user)->post(route('staff.store'), ['name' => 'Intruso'])->assertForbidden();
        // Without a current salon the professional does not exist for them (fail closed).
        $this->actingAs($user)->put(route('staff.schedule.update', $staff), ['ranges' => []])->assertNotFound();
        $this->actingAs($user)->delete(route('staff.destroy', $staff))->assertNotFound();
        $this->actingAs($user)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad'])->assertForbidden();

        expect(StaffSchedule::withoutGlobalScopes()->where('staff_id', $staff->id)->count())->toBe(1)
            ->and($staff->fresh())->not->toBeNull();
    })->with(['super admin' => ['admin'], 'client' => ['client']]);

    it('answers "Mi horario" of a member without profile with a message, never a 500 or a stranger\'s hours', function () {
        Staff::factory()->for($this->salon)->working([1])->create();
        [$user] = p3Member($this->salon, withProfile: false);

        $this->actingAs($user)->get('/mi-horario')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    });

    it('refuses every owner-only phase 3 action to staff even with every permission', function () {
        [$user, $own] = p3Member($this->salon, true, ...SalonPermission::cases());
        $other = Staff::factory()->for($this->salon)->working([2])->create();
        $holiday = new SalonHoliday;
        $holiday->forceFill(['salon_id' => $this->salon->id, 'date' => '2026-12-25', 'name' => 'Navidad'])->save();

        $this->actingAs($user)->get('/profesionales')->assertForbidden();
        $this->actingAs($user)->post(route('staff.store'), ['name' => 'Xavier'])->assertForbidden();
        $this->actingAs($user)->put(route('staff.update', $own), ['name' => 'Jefe'])->assertForbidden();
        $this->actingAs($user)->delete(route('staff.destroy', $other))->assertForbidden();
        $this->actingAs($user)->post(route('holidays.store'), ['date' => '2026-12-26', 'name' => 'Cierre'])->assertForbidden();
        $this->actingAs($user)->delete(route('holidays.destroy', $holiday))->assertForbidden();
        $this->actingAs($user)->put(route('staff.schedule.update', $other), ['ranges' => []])->assertForbidden();
        $this->actingAs($user)->post(route('staff.photo.store', $other), ['image' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertForbidden();
        $this->actingAs($user)->delete(route('staff.photo.destroy', $other))->assertForbidden();
        $this->actingAs($user)->put(route('staff.services.update', $other), ['services' => []])->assertForbidden();

        expect($holiday->fresh())->not->toBeNull()
            ->and(StaffSchedule::withoutGlobalScopes()->where('staff_id', $other->id)->count())->toBe(1)
            ->and($own->fresh()->name)->not->toBe('Jefe');
    });

    it('lets a co-owner edit anyone in the salon', function () {
        $coOwner = User::factory()->create();
        SalonMember::factory()->for($this->salon)->for($coOwner)->owner()->create();
        $staff = Staff::factory()->for($this->salon)->create();

        $this->actingAs($coOwner)->put(route('staff.schedule.update', $staff), ['ranges' => [p3Range(1, '09:00', '12:00')]])->assertRedirect();
        $this->actingAs($coOwner)->post(route('staff.store'), ['name' => 'Nuevo'])->assertRedirect();

        expect(StaffSchedule::withoutGlobalScopes()->where('staff_id', $staff->id)->count())->toBe(1);
    });

    it('keeps a paused professional able to open and edit their own hours', function () {
        [$user, $own] = p3Member($this->salon);
        $own->update(['is_active' => false]);

        $this->actingAs($user)->get('/mi-horario')->assertRedirect(route('staff.schedule.show', $own));
        $this->actingAs($user)->put(route('staff.schedule.update', $own), ['ranges' => [p3Range(1, '09:00', '12:00')]])->assertRedirect();
    });

    it('does not let a professional re-activate themselves, relink or rename through the owner endpoints', function () {
        [$user, $own] = p3Member($this->salon);
        $own->update(['is_active' => false]);

        $this->actingAs($user)->put(route('staff.update', $own), ['name' => 'Yo', 'is_active' => true])->assertForbidden();

        expect($own->fresh()->is_active)->toBeFalse();
    });
});

// ---------------------------------------------------------- multi tenant

describe('tenants', function () {
    beforeEach(function () {
        $this->other = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'segundo-qa']);
        $this->staffA = Staff::factory()->for($this->salon)->working([1], '09:00', '12:00')->create(['name' => 'De A']);
        $this->staffB = Staff::factory()->for($this->other)->working([1], '09:00', '12:00')->create(['name' => 'De B']);
    });

    it('works on one salon at a time: the other one\'s professionals look like they do not exist', function () {
        $this->actingAs($this->owner)->put(route('current-salon.update', $this->salon));

        $this->get(route('staff.schedule.show', $this->staffB))->assertNotFound();
        $this->put(route('staff.schedule.update', $this->staffB), ['ranges' => []])->assertNotFound();
        $this->post(route('staff.timeoff.store', $this->staffB), p3TimeOff())->assertNotFound();
        $this->put(route('staff.services.update', $this->staffB), ['services' => []])->assertNotFound();
        $this->put(route('staff.update', $this->staffB), ['name' => 'Robado'])->assertNotFound();
        $this->delete(route('staff.destroy', $this->staffB))->assertNotFound();

        expect($this->staffB->fresh()->name)->toBe('De B')
            ->and(StaffSchedule::withoutGlobalScopes()->where('staff_id', $this->staffB->id)->count())->toBe(1);
    });

    it('follows the owner when they switch salon', function () {
        $this->actingAs($this->owner)->put(route('current-salon.update', $this->other));

        $this->get(route('staff.schedule.show', $this->staffA))->assertNotFound();
        $this->get(route('staff.schedule.show', $this->staffB))->assertOk();
        $this->get('/profesionales')->assertInertia(fn (Assert $page) => $page
            ->has('staff', 1)
            ->where('staff.0.name', 'De B'));
    });

    it('does not mix the holidays of two salons or let one delete the other\'s', function () {
        $this->actingAs($this->owner)->put(route('current-salon.update', $this->salon));
        $this->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad A']);
        $this->put(route('current-salon.update', $this->other));
        $this->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad B'])->assertSessionHasNoErrors();

        $holidayA = SalonHoliday::withoutGlobalScopes()->where('salon_id', $this->salon->id)->firstOrFail();
        $this->delete(route('holidays.destroy', $holidayA))->assertNotFound();

        expect(SalonHoliday::withoutGlobalScopes()->count())->toBe(2)
            ->and($holidayA->fresh()->name)->toBe('Navidad A');
    });

    it('gives "Mi horario" per salon', function () {
        $linkedA = Staff::factory()->for($this->salon)->linkedTo($this->owner)->create();
        $linkedB = Staff::factory()->for($this->other)->linkedTo($this->owner)->create();

        $this->actingAs($this->owner)->put(route('current-salon.update', $this->salon));
        $this->get('/mi-horario')->assertRedirect(route('staff.schedule.show', $linkedA));
        $this->put(route('current-salon.update', $this->other));
        $this->get('/mi-horario')->assertRedirect(route('staff.schedule.show', $linkedB));
    });

    it('never links a team account of another salon', function () {
        $stranger = User::factory()->create();
        SalonMember::factory()->for($this->other)->for($stranger)->create();

        $this->actingAs($this->owner)->put(route('current-salon.update', $this->salon));
        $this->put(route('staff.update', $this->staffA), ['name' => 'De A', 'user_id' => $stranger->id]);

        expect($this->staffA->fresh()->user_id)->toBeNull();
    });

    it('ignores a foreign salon_id or position sent when creating a professional', function () {
        $this->actingAs($this->owner)->put(route('current-salon.update', $this->salon));
        $this->post(route('staff.store'), ['name' => 'Nuevo', 'salon_id' => $this->other->id, 'position' => 99, 'photo_path' => 'x.webp']);

        $created = Staff::withoutGlobalScopes()->where('name', 'Nuevo')->firstOrFail();
        expect($created->salon_id)->toBe($this->salon->id)->and($created->photo_path)->toBeNull();
    });

    it('does not apply a service from another salon to a professional', function () {
        $foreignService = Service::factory()->for($this->other)->create();
        $this->actingAs($this->owner)->put(route('current-salon.update', $this->salon));

        $this->put(route('staff.services.update', $this->staffA), ['services' => [['service_id' => $foreignService->id]]]);

        expect(p3Pivots($this->staffA))->toHaveCount(0);
    });
});

// -------------------------------------------------------- weekly schedule

describe('weekly schedule', function () {
    beforeEach(function () {
        $this->staff = Staff::factory()->for($this->salon)->create();
    });

    it('accepts a range that ends at 23:55 and the public page treats it correctly across midnight', function () {
        $this->actingAs($this->owner)
            ->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(3, '18:00', '23:55')]])
            ->assertSessionHasNoErrors();

        // Wednesday 23:54 in Lima = Thursday 04:54 UTC.
        $this->travelTo(CarbonImmutable::parse('2026-10-07 23:54', 'America/Lima'));
        $this->get('/s/qa-tres')->assertInertia(fn (Assert $page) => $page
            ->where('salon.openingHours.status.isOpen', true)
            ->where('salon.openingHours.status.time', '23:55')
            ->where('salon.openingHours.today', 3));

        // One hour later it is already Thursday in Lima and the salon is closed until next Wednesday.
        $this->travelTo(CarbonImmutable::parse('2026-10-08 00:30', 'America/Lima'));
        $this->get('/s/qa-tres')->assertInertia(fn (Assert $page) => $page
            ->where('salon.openingHours.status.isOpen', false)
            ->where('salon.openingHours.today', 4)
            ->where('salon.openingHours.status.weekday', 3)
            ->where('salon.openingHours.status.daysAhead', 6));
    });

    it('cannot express "until midnight": 00:00 and 24:00 are refused with a message, not a 500', function (string $end) {
        $this->actingAs($this->owner)
            ->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(3, '18:00', $end)]])
            ->assertSessionHasErrors();
    })->with(['00:00', '24:00']);

    it('survives odd payloads with validation errors', function (mixed $ranges) {
        $this->actingAs($this->owner)
            ->put(route('staff.schedule.update', $this->staff), ['ranges' => $ranges])
            ->assertSessionHasErrors();

        expect(StaffSchedule::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'a string' => ['lunes'],
        'an object keyed by name' => [['a' => p3Range(1, '09:00', '12:00')]],
        'start as array' => [[['weekday' => 1, 'start' => ['09:00'], 'end' => '12:00', 'access_level' => 'public']]],
        'weekday 0' => [[p3Range(0, '09:00', '12:00')]],
        'weekday 8' => [[p3Range(8, '09:00', '12:00')]],
        'weekday text' => [[['weekday' => '3abc', 'start' => '09:00', 'end' => '12:00', 'access_level' => 'public']]],
        'single digit hour' => [[p3Range(1, '9:00', '12:00')]],
        'with seconds' => [[p3Range(1, '09:00:00', '12:00:00')]],
        'end before start' => [[p3Range(1, '12:00', '09:00')]],
        'unknown access level' => [[p3Range(1, '09:00', '12:00', 'admin')]],
        'five on a day' => [array_map(fn (int $i) => p3Range(1, sprintf('%02d:00', 8 + $i * 2), sprintf('%02d:30', 8 + $i * 2)), range(0, 4))],
        'twenty nine total' => [array_map(fn (int $i) => p3Range(($i % 7) + 1, sprintf('%02d:00', 6 + intdiv($i, 7) * 2), sprintf('%02d:30', 6 + intdiv($i, 7) * 2)), range(0, 28))],
    ]);

    it('refuses identical or overlapping ranges with a toast and saves nothing', function () {
        $this->actingAs($this->owner)
            ->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(1, '09:00', '12:00'), p3Range(1, '09:00', '12:00')]])
            ->assertRedirect();

        expect(StaffSchedule::withoutGlobalScopes()->count())->toBe(0);
    });

    it('accepts exactly four ranges per day on all seven days', function () {
        $ranges = [];
        foreach (range(1, 7) as $day) {
            foreach ([[6, 8], [9, 11], [12, 14], [15, 17]] as [$from, $to]) {
                $ranges[] = p3Range($day, sprintf('%02d:00', $from), sprintf('%02d:00', $to));
            }
        }

        $this->actingAs($this->owner)->put(route('staff.schedule.update', $this->staff), ['ranges' => $ranges])->assertSessionHasNoErrors();

        expect(StaffSchedule::withoutGlobalScopes()->count())->toBe(28);
    });

    it('keeps the previous week when the new one is refused', function () {
        $this->actingAs($this->owner)->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(1, '09:00', '12:00')]]);
        $this->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(2, '09:00', '12:00'), p3Range(2, '11:00', '13:00')]]);

        expect(StaffSchedule::withoutGlobalScopes()->pluck('weekday')->all())->toBe([1]);
    });

    it('empties the public hours and the checklist when the week is saved empty', function () {
        $this->actingAs($this->owner)->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(1, '09:00', '12:00')]]);
        $step = fn () => collect($this->get(route('dashboard'))->inertiaProps('setup'))->firstWhere('key', 'schedule')['done'];

        expect($step())->toBeTrue();

        $this->put(route('staff.schedule.update', $this->staff), ['ranges' => []]);

        expect($step())->toBeFalse();
        $this->get('/s/qa-tres')->assertInertia(fn (Assert $page) => $page->where('salon.openingHours', null));
    });

    it('stores times as typed whatever the salon timezone', function () {
        $this->salon->forceFill(['timezone' => 'Asia/Tokyo'])->save();

        $this->actingAs($this->owner)->put(route('staff.schedule.update', $this->staff), ['ranges' => [p3Range(1, '09:00', '12:00')]]);

        expect(StaffSchedule::withoutGlobalScopes()->firstOrFail()->start())->toBe('09:00');
    });

    it('opens by the clock of a salon far from UTC', function () {
        $this->salon->forceFill(['timezone' => 'Asia/Tokyo'])->save();
        Staff::factory()->for($this->salon)->working([4], '04:00', '12:00')->create();

        // Wednesday 20:00 UTC is already Thursday 05:00 in Tokyo.
        $this->travelTo(CarbonImmutable::parse('2026-10-07 20:00', 'UTC'));

        $this->get('/s/qa-tres')->assertInertia(fn (Assert $page) => $page
            ->where('salon.openingHours.today', 4)
            ->where('salon.openingHours.status.isOpen', true)
            ->where('salon.openingHours.status.time', '12:00'));
    });

    // Since step 4.12 the public page shows the active team (name, title,
    // photo) as "quién te atiende"; the rest stays private.
    it('shows the active team by name and title only, never emails, ids or hours access', function () {
        Staff::factory()->for($this->salon)->working([1])->create(['name' => 'Visible Ñandú 💈', 'title' => 'Colorista']);
        Staff::factory()->for($this->salon)->create(['name' => 'Pausada Secreta', 'is_active' => false]);

        $props = $this->get('/s/qa-tres')->inertiaProps();
        $json = json_encode($props, JSON_UNESCAPED_UNICODE);

        expect($props['salon']['team'])->toBe([['name' => 'Visible Ñandú 💈', 'title' => 'Colorista', 'photoUrl' => null]])
            ->and($json)->not->toContain('Pausada Secreta')->not->toContain($this->owner->email)->not->toContain('access');
    });
});

// ------------------------------------------------------ absences / holidays

describe('time off and holidays', function () {
    beforeEach(function () {
        $this->staff = Staff::factory()->for($this->salon)->create();
    });

    it('round-trips a whole-day absence for a salon on the other side of the date line', function () {
        $this->salon->forceFill(['timezone' => 'Pacific/Kiritimati'])->save(); // UTC+14

        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff(['starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']));

        $row = StaffTimeOff::withoutGlobalScopes()->firstOrFail();
        expect($row->starts_at->toDateTimeString())->toBe('2026-10-11 10:00:00')
            ->and($row->ends_at->toDateTimeString())->toBe('2026-10-12 10:00:00');

        $this->get(route('staff.schedule.show', $this->staff))->assertInertia(fn (Assert $page) => $page
            ->where('staff.timeOff.0.startsOn', '2026-10-12')
            ->where('staff.timeOff.0.endsOn', '2026-10-12')
            ->where('staff.timeOff.0.allDay', true));
    });

    it('shows an all-day absence as all-day on a day whose midnight does not exist (DST)', function () {
        $this->salon->forceFill(['timezone' => 'America/Santiago'])->save();

        // Chile springs forward at 24:00 on Saturday 2027-09-04: Sunday starts at 01:00.
        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff(['starts_on' => '2027-09-05', 'ends_on' => '2027-09-05']))
            ->assertSessionHasNoErrors();

        $this->get(route('staff.schedule.show', $this->staff))->assertInertia(fn (Assert $page) => $page
            ->where('staff.timeOff.0.startsOn', '2027-09-05')
            ->where('staff.timeOff.0.endsOn', '2027-09-05')
            ->where('staff.timeOff.0.allDay', true));
    });

    it('round-trips an absence by hours that crosses midnight and several days', function () {
        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff([
            'starts_on' => '2026-10-12', 'ends_on' => '2026-10-14', 'all_day' => false, 'start_time' => '20:00', 'end_time' => '08:30',
        ]))->assertSessionHasNoErrors();

        $row = StaffTimeOff::withoutGlobalScopes()->firstOrFail();
        expect($row->starts_at->toDateTimeString())->toBe('2026-10-13 01:00:00')
            ->and($row->ends_at->toDateTimeString())->toBe('2026-10-14 13:30:00');

        $this->get(route('staff.schedule.show', $this->staff))->assertInertia(fn (Assert $page) => $page
            ->where('staff.timeOff.0.allDay', false)
            ->where('staff.timeOff.0.startTime', '20:00')
            ->where('staff.timeOff.0.endTime', '08:30')
            ->where('staff.timeOff.0.endsOn', '2026-10-14'));
    });

    it('does not claim to save an all-day absence that already ended', function () {
        // Yesterday all-day ended at midnight today: already over. It must not be accepted silently.
        $response = $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff([
            'starts_on' => '2026-10-06', 'ends_on' => '2026-10-06',
        ]));

        $response->assertSessionHasErrors();
    });

    it('refuses far-future or endless absences', function (array $override, string $field) {
        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff($override))->assertSessionHasErrors($field);
        expect(StaffTimeOff::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'starts in three years' => [['starts_on' => '2029-10-12', 'ends_on' => '2029-10-13'], 'starts_on'],
        'ends in 2099' => [['ends_on' => '2099-01-01'], 'ends_on'],
        'impossible date' => [['starts_on' => '2026-02-30', 'ends_on' => '2026-03-02'], 'starts_on'],
        'all_day as text' => [['all_day' => 'quizás'], 'all_day'],
        'note too long' => [['note' => str_repeat('ñ', 121)], 'note'],
    ]);

    it('accepts accents, ñ and emoji in notes and holiday names', function () {
        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff(['note' => 'Cumpleaños de mamá 🎂 – Ñuñoa']))->assertSessionHasNoErrors();
        $this->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad 🎄 Ñ'])->assertSessionHasNoErrors();

        expect(SalonHoliday::withoutGlobalScopes()->value('name'))->toBe('Navidad 🎄 Ñ');
    });

    it('stops at 60 upcoming absences without a server error', function () {
        StaffTimeOff::factory()->for($this->staff)->count(60)->create(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(6)]);

        $this->actingAs($this->owner)->post(route('staff.timeoff.store', $this->staff), p3TimeOff())->assertRedirect();

        expect(StaffTimeOff::withoutGlobalScopes()->count())->toBe(60);
    });

    it('stops at 60 holidays but still lets one be renamed', function () {
        foreach (range(0, 59) as $i) {
            (new SalonHoliday)->forceFill(['salon_id' => $this->salon->id, 'date' => CarbonImmutable::parse('2026-11-01')->addDays($i)->toDateString(), 'name' => "D{$i}"])->save();
        }

        $this->actingAs($this->owner)->post(route('holidays.store'), ['date' => '2026-11-01', 'name' => 'Renombrado'])->assertSessionHasNoErrors();
        $this->post(route('holidays.store'), ['date' => '2027-06-01', 'name' => 'Uno más']);

        expect(SalonHoliday::withoutGlobalScopes()->count())->toBe(60)
            ->and(SalonHoliday::withoutGlobalScopes()->whereDate('date', '2026-11-01')->value('name'))->toBe('Renombrado');
    });

    it('closes the public page today when today is a holiday, even for a salon whose day differs from UTC', function () {
        $this->salon->forceFill(['timezone' => 'Asia/Tokyo'])->save();
        Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create();
        // 15:00 UTC Wednesday is already Thursday 00:00 in Tokyo.
        $this->post(route('holidays.store'), ['date' => '2026-10-08', 'name' => 'Feriado Tokio']);
        $this->actingAs($this->owner)->post(route('holidays.store'), ['date' => '2026-10-08', 'name' => 'Feriado Tokio']);

        $this->get('/s/qa-tres')->assertInertia(fn (Assert $page) => $page
            ->where('salon.openingHours.closedToday', 'Feriado Tokio')
            ->where('salon.openingHours.status.isOpen', false));
    });

    it('lists a holiday of the salon\'s today in the owner screen, and hides yesterday\'s', function () {
        foreach (['2026-10-06' => 'Ayer', '2026-10-07' => 'Hoy', '2026-10-08' => 'Mañana'] as $date => $name) {
            (new SalonHoliday)->forceFill(['salon_id' => $this->salon->id, 'date' => $date, 'name' => $name])->save();
        }

        $this->actingAs($this->owner)->get('/profesionales')->assertInertia(fn (Assert $page) => $page
            ->where('holidays.0.name', 'Hoy')
            ->has('holidays', 2));
    });

    it('says "no next opening" instead of failing when every day in the lookahead is a holiday', function () {
        Staff::factory()->for($this->salon)->working([3], '09:00', '18:00')->create();
        foreach (['2026-10-07', '2026-10-14', '2026-10-21'] as $date) {
            (new SalonHoliday)->forceFill(['salon_id' => $this->salon->id, 'date' => $date, 'name' => 'Cierre'])->save();
        }

        $this->get('/s/qa-tres')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('salon.openingHours.status.isOpen', false));
    });
});

// ------------------------------------------------------ lifecycle / linking

describe('professional lifecycle', function () {
    it('re-creates a professional for the same account after removing the previous one', function () {
        [$user, $first] = p3Member($this->salon);

        $this->actingAs($this->owner)->delete(route('staff.destroy', $first))->assertRedirect();
        $this->post(route('staff.store'), ['name' => 'Otra vez', 'user_id' => $user->id])->assertSessionHasNoErrors();

        $linked = Staff::withoutGlobalScopes()->where('user_id', $user->id)->get();
        expect($linked)->toHaveCount(1)->and($linked->first()->name)->toBe('Otra vez');
    });

    it('removes several unlinked professionals without tripping the unique (salon, account) index', function () {
        $a = Staff::factory()->for($this->salon)->create();
        $b = Staff::factory()->for($this->salon)->create();

        $this->actingAs($this->owner)->delete(route('staff.destroy', $a))->assertRedirect();
        $this->delete(route('staff.destroy', $b))->assertRedirect();

        expect(Staff::withoutGlobalScopes()->whereNull('deleted_at')->where('salon_id', $this->salon->id)->count())->toBe(0);
    });

    it('answers 404 for a removed professional on every url', function () {
        $staff = Staff::factory()->for($this->salon)->working([1])->create();
        $this->actingAs($this->owner)->delete(route('staff.destroy', $staff));

        $this->get(route('staff.schedule.show', $staff))->assertNotFound();
        $this->put(route('staff.update', $staff), ['name' => 'Zombi'])->assertNotFound();
        $this->post(route('staff.timeoff.store', $staff), p3TimeOff())->assertNotFound();
        $this->get('/s/qa-tres')->assertInertia(fn (Assert $page) => $page->where('salon.openingHours', null));
    });

    it('lets the owner remove their own profile and then explains "Mi horario"', function () {
        $own = Staff::factory()->for($this->salon)->linkedTo($this->owner)->working([1])->create();

        $this->actingAs($this->owner)->delete(route('staff.destroy', $own))->assertRedirect();

        $this->get('/mi-horario')->assertRedirect(route('dashboard'));
        $this->get('/profesionales')->assertInertia(fn (Assert $page) => $page
            ->has('linkableMembers', 1));
    });

    it('does not unlink the account when the owner edits only the name of a linked professional', function () {
        [$user, $staff] = p3Member($this->salon);

        $this->actingAs($this->owner)->put(route('staff.update', $staff), ['name' => 'Renombrado', 'title' => 'Barbero']);

        expect($staff->fresh()->user_id)->toBe($user->id);
    });

    it('takes a brand-new profile when the invitation\'s professional was removed meanwhile', function () {
        Notification::fake();
        $ghost = Staff::factory()->for($this->salon)->working([1])->create(['name' => 'Fantasma']);
        $this->actingAs($this->owner)->post(route('team.invitations.store'), [
            'email' => 'nuevo@example.com', 'role' => 'staff', 'permissions' => [], 'staff_id' => $ghost->id,
        ]);
        $invitation = SalonInvitation::withoutGlobalScopes()->firstOrFail();
        $invitation->forceFill(['token_hash' => SalonInvitation::hashToken('tok-ghost')])->save();
        $this->delete(route('staff.destroy', $ghost));

        $invitee = User::factory()->create(['name' => 'Nuevo Ñ', 'email' => 'nuevo@example.com']);
        $this->actingAs($invitee)->post(route('invitations.accept', 'tok-ghost'))->assertRedirect();

        $profile = Staff::withoutGlobalScopes()->where('user_id', $invitee->id)->firstOrFail();
        expect($profile->id)->not->toBe($ghost->id)->and($profile->name)->toBe('Nuevo Ñ')
            ->and($this->salon->members()->where('user_id', $invitee->id)->exists())->toBeTrue();
    });

    it('does not steal a profile linked to someone else while the invitation was pending', function () {
        Notification::fake();
        $profile = Staff::factory()->for($this->salon)->create(['name' => 'Disputado']);
        $this->actingAs($this->owner)->post(route('team.invitations.store'), [
            'email' => 'nuevo@example.com', 'role' => 'staff', 'permissions' => [], 'staff_id' => $profile->id,
        ]);
        $invitation = SalonInvitation::withoutGlobalScopes()->firstOrFail();
        $invitation->forceFill(['token_hash' => SalonInvitation::hashToken('tok-race')])->save();

        [$other] = p3Member($this->salon, withProfile: false);
        $this->put(route('staff.update', $profile), ['name' => 'Disputado', 'user_id' => $other->id]);

        $invitee = User::factory()->create(['email' => 'nuevo@example.com']);
        $this->actingAs($invitee)->post(route('invitations.accept', 'tok-race'))->assertRedirect();

        expect($profile->fresh()->user_id)->toBe($other->id)
            ->and(Staff::withoutGlobalScopes()->where('user_id', $invitee->id)->exists())->toBeTrue();
    });

    it('refuses an invitation without crashing when the salon already has 50 professionals', function () {
        Staff::factory()->for($this->salon)->count(Staff::MAX_PER_SALON)->create();
        SalonInvitation::factory()->for($this->salon)->withToken('tok-full')->create(['email' => 'lleno@example.com']);
        $invitee = User::factory()->create(['email' => 'lleno@example.com']);

        $response = $this->actingAs($invitee)->post(route('invitations.accept', 'tok-full'));

        expect($response->status())->toBeLessThan(500);
        // Atomic: either the whole acceptance happened or none of it.
        $isMember = $this->salon->members()->where('user_id', $invitee->id)->exists();
        $hasProfile = Staff::withoutGlobalScopes()->where('user_id', $invitee->id)->exists();
        expect($isMember)->toBe($hasProfile);
    });

    it('lets a removed professional free a slot under the 50 limit', function () {
        $all = Staff::factory()->for($this->salon)->count(Staff::MAX_PER_SALON)->create();
        $this->actingAs($this->owner)->delete(route('staff.destroy', $all->first()));

        $this->post(route('staff.store'), ['name' => 'Cupo liberado'])->assertSessionHasNoErrors();

        expect(Staff::withoutGlobalScopes()->whereNull('deleted_at')->where('salon_id', $this->salon->id)->count())->toBe(Staff::MAX_PER_SALON);
    });

    it('accepts names with accents, ñ and emoji, and rejects markup', function () {
        $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Ñusta Peña 💇', 'title' => 'Colorista · Niños'])->assertSessionHasNoErrors();
        $this->post(route('staff.store'), ['name' => 'Ana', 'title' => '<script>alert(1)</script>'])->assertSessionHasErrors('title');

        expect(Staff::withoutGlobalScopes()->where('name', 'Ñusta Peña 💇')->exists())->toBeTrue();
    });

    it('lets a member removed from the team lose access to the schedule screens at once', function () {
        [$user, $staff] = p3Member($this->salon);
        $member = SalonMember::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($this->owner)->delete(route('team.members.destroy', $member));

        app(CurrentSalon::class)->set(null); // tests share one process; production starts each request clean
        $this->actingAs($user->fresh())->get(route('staff.schedule.show', $staff))->assertNotFound();
        expect($staff->fresh()->user_id)->toBeNull();
    });
});

// -------------------------------------------------------- services

describe('services per professional', function () {
    beforeEach(function () {
        $this->staff = Staff::factory()->for($this->salon)->create();
        $this->fade = Service::factory()->for($this->salon)->create(['name' => 'Fade']);
        $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba']);
        $this->staff->services()->sync([$this->fade->id, $this->beard->id]);
    });

    it('keeps a professional\'s untick when a deleted service is restored from the catalog', function () {
        $template = ServiceTemplate::where('code', 'CH06')->firstOrFail();
        $other = Staff::factory()->for($this->salon)->create();
        $this->actingAs($this->owner)->post(route('services.catalog.store'), ['template_ids' => [$template->id]]);
        $service = Service::query()->where('service_template_id', $template->id)->firstOrFail();
        expect(p3Pivots($other))->toHaveKey($service->id);

        // The professional says they do not do it, then the owner deletes and restores it.
        $this->put(route('staff.services.update', $other), ['services' => []]);
        $this->delete(route('services.destroy', $service));
        $this->post(route('services.catalog.store'), ['template_ids' => [$template->id]]);

        expect(p3Pivots($other))->not->toHaveKey($service->id);
    });

    it('keeps a professional\'s own time for a deleted service if they save their list meanwhile', function () {
        $this->actingAs($this->owner)->put(route('staff.services.update', $this->staff), ['services' => [
            ['service_id' => $this->fade->id, 'active_minutes' => 50],
            ['service_id' => $this->beard->id, 'active_minutes' => 25],
        ]]);
        $this->actingAs($this->owner)->delete(route('services.destroy', $this->beard));

        // The professional saves again (the deleted service is not on their screen).
        $this->put(route('staff.services.update', $this->staff), ['services' => [['service_id' => $this->fade->id, 'active_minutes' => 50]]]);
        $this->beard->refresh();
        Service::withTrashed()->findOrFail($this->beard->id)->restore();

        expect(p3Pivots($this->staff)->get($this->beard->id)?->active_minutes)->toBe(25);
    });

    it('never lets a professional without the permission change a price, not even by omission', function () {
        [$user, $own] = p3Member($this->salon);
        $own->services()->sync([$this->fade->id => ['price_cents' => 5000, 'active_minutes' => 40]]);

        $this->actingAs($user)->put(route('staff.services.update', $own), ['services' => [
            ['service_id' => $this->fade->id, 'active_minutes' => 30, 'price' => '1'],
            ['service_id' => $this->beard->id, 'active_minutes' => 20, 'price' => '1'],
        ]])->assertSessionHasNoErrors();

        $pivots = p3Pivots($own);
        expect($pivots->get($this->fade->id)->price_cents)->toBe(5000)
            ->and($pivots->get($this->fade->id)->active_minutes)->toBe(30)
            // Nor add a service the owner did not assign to them.
            ->and($pivots->has($this->beard->id))->toBeFalse();
    });

    it('validates minutes and price strings', function (array $entry, string $field) {
        $this->actingAs($this->owner)->put(route('staff.services.update', $this->staff), ['services' => [['service_id' => $this->fade->id, ...$entry]]])
            ->assertSessionHasErrors($field);
    })->with([
        'minutes 4' => [['active_minutes' => 4], 'services.0.active_minutes'],
        'minutes 481' => [['active_minutes' => 481], 'services.0.active_minutes'],
        'negative price' => [['price' => '-5'], 'services.0.price'],
        'exponent' => [['price' => '1e5'], 'services.0.price'],
        'three decimals' => [['price' => '10.999'], 'services.0.price'],
        'six digits' => [['price' => '123456'], 'services.0.price'],
        'words' => [['price' => 'gratis'], 'services.0.price'],
    ]);

    it('accepts comma decimals and the limits', function () {
        $this->actingAs($this->owner)->put(route('staff.services.update', $this->staff), ['services' => [
            ['service_id' => $this->fade->id, 'active_minutes' => 480, 'price' => '99999,99'],
            ['service_id' => $this->beard->id, 'active_minutes' => 5, 'price' => '0'],
        ]])->assertSessionHasNoErrors();

        $pivots = p3Pivots($this->staff);
        expect($pivots->get($this->fade->id)->price_cents)->toBe(9_999_999)->and($pivots->get($this->beard->id)->price_cents)->toBe(0);
    });

    it('assigns a new service to a professional created later and to inactive ones', function () {
        $inactive = Staff::factory()->for($this->salon)->inactive()->create();
        $this->actingAs($this->owner)->post(route('services.store'), [
            'name' => 'Cejas', 'category' => 'men_cut', 'target' => 'men', 'price' => '10', 'active_minutes' => 15, 'variants' => [],
        ]);
        $service = Service::query()->where('name', 'Cejas')->firstOrFail();

        expect(p3Pivots($inactive))->toHaveKey($service->id)->and(p3Pivots($this->staff))->toHaveKey($service->id);
    });
});

// ------------------------------------------------------ suspended salon

describe('read-only salon', function () {
    it('freezes every phase 3 write for a suspended salon', function () {
        $staff = Staff::factory()->for($this->salon)->linkedTo($this->owner)->working([1])->create();
        $service = Service::factory()->for($this->salon)->create();
        $this->salon->forceFill(['status' => SalonStatus::Suspended])->save();

        $this->actingAs($this->owner);
        $this->put(route('staff.schedule.update', $staff), ['ranges' => []]);
        $this->post(route('staff.timeoff.store', $staff), p3TimeOff());
        $this->put(route('staff.services.update', $staff), ['services' => [['service_id' => $service->id]]]);
        $this->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Navidad']);
        $this->post(route('staff.store'), ['name' => 'Nuevo']);
        $this->delete(route('staff.destroy', $staff));

        expect(StaffSchedule::withoutGlobalScopes()->count())->toBe(1)
            ->and(StaffTimeOff::withoutGlobalScopes()->count())->toBe(0)
            ->and(SalonHoliday::withoutGlobalScopes()->count())->toBe(0)
            ->and(Staff::withoutGlobalScopes()->count())->toBe(1);
        $this->get(route('staff.schedule.show', $staff))->assertOk();
        $this->get('/s/qa-tres')->assertNotFound();
    });
});

// ------------------------------------------------------------- seeders

describe('seeders', function () {
    it('leaves the demo schedule intact when seeded twice', function () {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $counts = fn () => [Staff::withoutGlobalScopes()->count(), StaffSchedule::withoutGlobalScopes()->count(), StaffTimeOff::withoutGlobalScopes()->count(), SalonHoliday::withoutGlobalScopes()->count()];
        $before = $counts();

        $this->seed(DemoSeeder::class);

        expect($counts())->toBe($before)->and($before[0])->toBeGreaterThanOrEqual(7);
        $this->get('/s/barberia-don-lucho')->assertOk()->assertInertia(fn (Assert $page) => $page->where('salon.openingHours.status.isOpen', true));
    });
});
