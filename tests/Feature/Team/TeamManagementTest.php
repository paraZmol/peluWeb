<?php

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
    $this->staff = SalonMember::factory()->for($this->salon)->create();
});

it('lets the owner open the team page', function () {
    $this->actingAs($this->owner)->get(route('team.index'))->assertOk();
});

it('forbids staff from managing the team, even with every extra permission', function () {
    $staffUser = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($staffUser)
        ->withPermissions(...SalonPermission::cases())->create();

    $this->actingAs($staffUser)->get(route('team.index'))->assertForbidden();
    $this->actingAs($staffUser)
        ->put(route('team.members.update', $this->staff), ['role' => 'owner', 'permissions' => []])
        ->assertForbidden();
    $this->actingAs($staffUser)
        ->post(route('team.invitations.store'), ['email' => 'x@example.com', 'role' => 'owner'])
        ->assertForbidden();
});

it('updates the role and permissions of a member', function () {
    $this->actingAs($this->owner)
        ->put(route('team.members.update', $this->staff), [
            'role' => SalonRole::Staff->value,
            'permissions' => [SalonPermission::ManageCash->value, SalonPermission::ViewReports->value],
        ])
        ->assertRedirect(route('team.index'));

    $staff = $this->staff->fresh();
    expect($staff?->hasPermission(SalonPermission::ManageCash))->toBeTrue()
        ->and($staff?->hasPermission(SalonPermission::ManageServices))->toBeFalse();
});

it('rejects unknown permissions', function () {
    $this->actingAs($this->owner)
        ->put(route('team.members.update', $this->staff), ['role' => 'staff', 'permissions' => ['delete_everything']])
        ->assertSessionHasErrors('permissions.0');
});

it('never leaves a salon without an owner', function () {
    $ownerMembership = $this->owner->membershipIn($this->salon);

    $this->actingAs($this->owner)
        ->from(route('team.index'))
        ->put(route('team.members.update', $ownerMembership), ['role' => 'staff', 'permissions' => []])
        ->assertRedirect(route('team.index'));
    $this->actingAs($this->owner)
        ->from(route('team.index'))
        ->delete(route('team.members.destroy', $ownerMembership));

    expect($ownerMembership?->fresh()?->role)->toBe(SalonRole::Owner);
});

it('lets an owner step down when another owner exists', function () {
    $secondOwner = SalonMember::factory()->for($this->salon)->owner()->create();

    $this->actingAs($this->owner)
        ->delete(route('team.members.destroy', $secondOwner))
        ->assertRedirect(route('team.index'));

    expect($secondOwner->fresh())->toBeNull();
});

it('removes a staff member', function () {
    $this->actingAs($this->owner)
        ->delete(route('team.members.destroy', $this->staff))
        ->assertRedirect(route('team.index'));

    expect($this->staff->fresh())->toBeNull();
});
