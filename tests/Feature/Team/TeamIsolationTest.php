<?php

use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The most expensive bug this product can have: one salon seeing or touching
| another salon's data. Every team endpoint is attacked from salon B.
*/

beforeEach(function () {
    $this->ownerA = User::factory()->create();
    $this->salonA = Salon::factory()->ownedBy($this->ownerA)->create();
    $this->staffA = SalonMember::factory()->for($this->salonA)->create();
    $this->invitationA = SalonInvitation::factory()->for($this->salonA)->create();

    $this->ownerB = User::factory()->create();
    $this->salonB = Salon::factory()->ownedBy($this->ownerB)->create();
});

it('shows each owner only the members and invitations of their own salon', function () {
    $this->actingAs($this->ownerB)
        ->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 1)
            ->where('members.0.email', $this->ownerB->email)
            ->has('invitations', 0));
});

it('hides a member of another salon from updates', function () {
    $this->actingAs($this->ownerB)
        ->put(route('team.members.update', $this->staffA), ['role' => SalonRole::Owner->value, 'permissions' => []])
        ->assertNotFound();

    expect($this->staffA->fresh()?->role)->toBe(SalonRole::Staff);
});

it('hides a member of another salon from removal', function () {
    $this->actingAs($this->ownerB)
        ->delete(route('team.members.destroy', $this->staffA))
        ->assertNotFound();

    expect($this->staffA->fresh())->not->toBeNull();
});

it('hides an invitation of another salon from cancellation', function () {
    $this->actingAs($this->ownerB)
        ->delete(route('team.invitations.destroy', $this->invitationA))
        ->assertNotFound();

    expect(SalonInvitation::withoutGlobalScopes()->find($this->invitationA->id))->not->toBeNull();
});

it('does not let an owner of both salons edit salon A while working in salon B', function () {
    SalonMember::factory()->for($this->salonA)->for($this->ownerB)->owner()->create();

    $this->actingAs($this->ownerB)
        ->withSession(['current_salon_id' => $this->salonB->id])
        ->put(route('team.members.update', $this->staffA), ['role' => 'staff', 'permissions' => []])
        ->assertNotFound();
});
