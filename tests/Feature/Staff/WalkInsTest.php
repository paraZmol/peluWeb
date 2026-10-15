<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\WalkInStatus;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalkIn;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.6: the day's line of people without a booking. Barbería Central
| keeps one (a barbershop does by default); Estudio Luz does not.
| "Now" is Wednesday 2026-10-07 10:00 in Lima.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->barber = app(CreateSalon::class)->handle($this->owner, 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
    $this->barber->forceFill(['timezone' => 'America/Lima'])->save();
    $this->cut = Service::factory()->for($this->barber)->create(['name' => 'Corte']);
    $this->beto = Staff::factory()->for($this->barber)->create(['name' => 'Beto']);

    $this->member = User::factory()->create();
    SalonMember::factory()->for($this->barber)->for($this->member)->create();
});

it('keeps a line by default only where walking in is the usual way', function () {
    $studioOwner = User::factory()->create();
    $studio = app(CreateSalon::class)->handle($studioOwner, 'Estudio Luz', BusinessType::BeautySalon, Clientele::Everyone);

    expect($this->barber->refresh()->walk_ins_enabled)->toBeTrue()
        ->and($studio->refresh()->walk_ins_enabled)->toBeFalse();

    $this->actingAs($studioOwner)->get(route('walk-ins.index'))->assertNotFound();
    $this->actingAs($studioOwner)->post(route('walk-ins.store'), ['name' => 'Luis'])->assertNotFound();
    $this->actingAs($studioOwner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentSalon.walkInsEnabled', false));

    // The owner switches it on with the booking rules.
    $this->actingAs($studioOwner)->put(route('salon.booking.update'), [
        'accepts_online_bookings' => true, 'walk_ins_enabled' => true, 'min_lead_minutes' => 60, 'max_days_ahead' => 60,
        'slot_step_minutes' => 15, 'cancel_window_hours' => 24, 'deposit_percent' => 0, 'hold_minutes' => 30,
    ])->assertSessionHasNoErrors();
    $this->actingAs($studioOwner)->get(route('walk-ins.index'))->assertOk();
});

it('lets anyone of the team add who walks in and move them along the line', function () {
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => '  Luis   Paredes ', 'service_id' => $this->cut->id, 'staff_id' => $this->beto->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => 'Tomás'])->assertSessionHasNoErrors();

    $this->actingAs($this->member)->get(route('walk-ins.index'))->assertInertia(fn (Assert $page) => $page
        ->component('staff/walk-ins')
        ->has('line', 2)
        ->where('line.0.name', 'Luis Paredes')
        ->where('line.0.serviceName', 'Corte')
        ->where('line.0.staffName', 'Beto')
        ->where('line.0.status', 'waiting')
        ->where('line.0.arrivedAt', '10:00')
        ->where('line.1.name', 'Tomás')
        ->where('line.1.serviceName', null));

    $luis = WalkIn::withoutGlobalScope('salon')->where('name', 'Luis Paredes')->firstOrFail();
    $move = fn (string $status) => $this->actingAs($this->member)->patch(route('walk-ins.update', $luis), ['status' => $status]);

    $move('called')->assertSessionHasNoErrors();
    expect($luis->refresh()->status)->toBe(WalkInStatus::Called)->and($luis->called_at)->not->toBeNull();
    $move('attended')->assertSessionHasNoErrors();
    expect($luis->refresh()->status)->toBe(WalkInStatus::Attended)->and($luis->closed_at)->not->toBeNull();

    // A closed place only goes back to waiting, and a made-up status is refused.
    $move('called')->assertInertiaFlash('toast.type', 'error');
    $move('vip')->assertSessionHasErrors('status');
    expect($luis->refresh()->status)->toBe(WalkInStatus::Attended);
});

it('undoes a close by mistake the same day, while the line has room', function () {
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => 'Luis']);
    $luis = WalkIn::withoutGlobalScope('salon')->sole();
    $move = fn (string $status) => $this->actingAs($this->member)->patch(route('walk-ins.update', $luis), ['status' => $status]);

    $move('left')->assertSessionHasNoErrors();
    $move('waiting')->assertSessionHasNoErrors();
    expect($luis->refresh())->status->toBe(WalkInStatus::Waiting)->closed_at->toBeNull();

    // A full line has no room to take them back.
    $move('left');
    WalkIn::query()->insert(array_fill(0, WalkIn::MAX_OPEN, [
        'salon_id' => $this->barber->id, 'day' => '2026-10-07', 'name' => 'Otra persona', 'status' => WalkInStatus::Waiting->value,
        'created_at' => now(), 'updated_at' => now(),
    ]));
    $move('waiting')->assertInertiaFlash('toast.type', 'error');
    WalkIn::withoutGlobalScope('salon')->whereKeyNot($luis->id)->delete();

    // Yesterday's line is over.
    $this->travelTo(CarbonImmutable::parse('2026-10-08 09:00', 'America/Lima'));
    $move('waiting')->assertInertiaFlash('toast.type', 'error');
    expect($luis->refresh()->status)->toBe(WalkInStatus::Left);
});

it('starts the line over each day', function () {
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => 'Luis']);

    $this->travelTo(CarbonImmutable::parse('2026-10-08 09:00', 'America/Lima'));

    $this->actingAs($this->member)->get(route('walk-ins.index'))->assertInertia(fn (Assert $page) => $page->has('line', 0));
});

it('keeps another salon out of the line, its people and its services', function () {
    $otherOwner = User::factory()->create();
    $other = app(CreateSalon::class)->handle($otherOwner, 'Barbería Norte', BusinessType::Barbershop, Clientele::Men);
    $theirService = Service::factory()->for($other)->create();
    $theirBarber = Staff::factory()->for($other)->create();
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => 'Luis']);
    $luis = WalkIn::withoutGlobalScope('salon')->firstOrFail();

    // The other salon attacks the URL with this salon's id.
    $this->actingAs($otherOwner)->patch(route('walk-ins.update', $luis->id), ['status' => 'left'])->assertNotFound();
    $this->actingAs($otherOwner)->get(route('walk-ins.index'))->assertInertia(fn (Assert $page) => $page->has('line', 0));

    // And this salon cannot point at their service or their barber.
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => 'Ana', 'service_id' => $theirService->id])->assertSessionHasErrors('service_id');
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => 'Ana', 'staff_id' => $theirBarber->id])->assertSessionHasErrors('staff_id');

    expect($luis->refresh()->status)->toBe(WalkInStatus::Waiting)
        ->and(WalkIn::withoutGlobalScope('salon')->count())->toBe(1);

    // Nobody without a session gets in.
    auth()->logout();
    $this->get(route('walk-ins.index'))->assertRedirect(route('login'));
});

it('asks for a name and refuses markup in it', function () {
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => '<b>Luis</b>'])->assertSessionHasErrors('name');
    $this->actingAs($this->member)->post(route('walk-ins.store'), ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');

    expect(WalkIn::withoutGlobalScope('salon')->count())->toBe(0);
});
