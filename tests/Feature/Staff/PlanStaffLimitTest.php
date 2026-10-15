<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonRole;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\Staff;
use App\Models\User;

/*
| Step 7.1: the plan only limits how many professionals have an agenda
| (active). Reception or cash desk members without one never count.
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
});

function planNamed(string $code): Plan
{
    return Plan::query()->where('code', $code)->firstOrFail();
}

function activeStaffOf(Salon $salon): int
{
    return Staff::withoutGlobalScopes()->where('salon_id', $salon->id)->where('is_active', true)->count();
}

it('starts a new salon on the trial with the Equipo plan', function () {
    expect($this->salon->plan->code)->toBe('equipo')
        ->and($this->salon->staffLimit())->toBe(6);
});

it('stops at the plan limit of professionals with an agenda, and counts only those', function () {
    $this->salon->plan()->associate(planNamed('solo'))->save();
    // The owner's own profile, made with the salon, already fills Solo.
    expect(activeStaffOf($this->salon))->toBe(1);

    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Beto'])
        ->assertInertiaFlash('toast.type', 'error');
    expect(activeStaffOf($this->salon))->toBe(1);

    // Without an agenda there is no limit to hit.
    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Recepción', 'is_active' => false])
        ->assertSessionHasNoErrors();
    $paused = Staff::withoutGlobalScopes()->where('name', 'Recepción')->firstOrFail();

    // Giving them an agenda is what counts.
    $this->actingAs($this->owner)->put(route('staff.update', $paused), ['name' => 'Recepción', 'is_active' => true])
        ->assertInertiaFlash('toast.type', 'error');
    expect($paused->refresh()->is_active)->toBeFalse();

    // Room again once someone else stops having one.
    $own = Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->where('is_active', true)->sole();
    $this->actingAs($this->owner)->put(route('staff.update', $own), ['name' => $own->name, 'is_active' => false]);
    $this->actingAs($this->owner)->put(route('staff.update', $paused), ['name' => 'Recepción', 'is_active' => true])
        ->assertSessionHasNoErrors();
    expect($paused->refresh()->is_active)->toBeTrue();
});

it('lets a renamed professional keep their agenda with the plan full', function () {
    $this->salon->plan()->associate(planNamed('solo'))->save();
    $own = Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->sole();

    $this->actingAs($this->owner)->put(route('staff.update', $own), ['name' => 'Nuevo nombre', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($own->refresh()->name)->toBe('Nuevo nombre');
});

it('lets an invited professional join a full plan, without an agenda', function () {
    $this->salon->plan()->associate(planNamed('solo'))->save();
    $invitee = User::factory()->create(['email' => 'barbero@example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create(['email' => 'barbero@example.com', 'role' => SalonRole::Staff]);

    $this->actingAs($invitee)->post(route('invitations.accept', 'secret-token'))->assertRedirect(route('dashboard'));

    $profile = Staff::withoutGlobalScopes()->where('user_id', $invitee->id)->sole();
    expect($profile->is_active)->toBeFalse()
        ->and(activeStaffOf($this->salon))->toBe(1);
});

it('treats a salon without a plan as the trial', function () {
    $salon = Salon::factory()->create(['plan_id' => null]);

    expect($salon->staffLimit())->toBe(planNamed('equipo')->max_staff);
});
