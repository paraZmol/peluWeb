<?php

use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('gives the owner a professional profile with every starter service when the salon is created', function () {
    $user = User::factory()->create(['name' => 'Luis Quispe']);

    $this->actingAs($user)->post(route('salons.store'), [
        'name' => 'Barbería El Turco', 'business_type' => 'barbershop', 'clientele' => 'men', 'salon_terms' => '1',
    ]);

    $staff = Staff::withoutGlobalScopes()->firstOrFail();
    expect($staff->name)->toBe('Luis Quispe')
        ->and($staff->user_id)->toBe($user->id)
        ->and($staff->salon_id)->toBe(Salon::firstOrFail()->id)
        ->and(DB::table('service_staff')->where('staff_id', $staff->id)->count())
        ->toBe(DB::table('services')->count())
        ->toBeGreaterThan(0);
});

describe('invitations', function () {
    beforeEach(function () {
        $this->owner = User::factory()->create();
        $this->salon = Salon::factory()->ownedBy($this->owner)->create();
        $this->invitee = User::factory()->create(['name' => 'Pedro Barbero', 'email' => 'pedro@example.com']);
    });

    it('creates a professional profile for staff who accept an invitation', function () {
        SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create(['email' => 'pedro@example.com']);

        $this->actingAs($this->invitee)->post(route('invitations.accept', 'secret-token'));

        $staff = Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->firstOrFail();
        expect($staff->user_id)->toBe($this->invitee->id)->and($staff->name)->toBe('Pedro Barbero');
    });

    it('hands over the existing profile the owner chose, keeping its hours', function () {
        Notification::fake();
        $existing = Staff::factory()->for($this->salon)->working([1, 2])->create(['name' => 'Pedrito']);

        $this->actingAs($this->owner)->post(route('team.invitations.store'), [
            'email' => 'pedro@example.com', 'role' => 'staff', 'permissions' => [], 'staff_id' => $existing->id,
        ]);
        $invitation = SalonInvitation::withoutGlobalScopes()->firstOrFail();
        $invitation->forceFill(['token_hash' => SalonInvitation::hashToken('secret-token')])->save();

        $this->actingAs($this->invitee)->post(route('invitations.accept', 'secret-token'));

        expect($invitation->staff_id)->toBe($existing->id)
            ->and($existing->refresh()->user_id)->toBe($this->invitee->id)
            ->and($existing->name)->toBe('Pedrito')
            ->and(Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(1);
    });

    it('never offers a profile of another salon or one already linked', function () {
        Notification::fake();
        $foreign = Staff::factory()->for(Salon::factory()->create())->create();
        $taken = Staff::factory()->for($this->salon)->linkedTo($this->owner)->create();

        foreach ([$foreign, $taken] as $index => $staff) {
            $this->actingAs($this->owner)->post(route('team.invitations.store'), [
                'email' => "persona{$index}@example.com", 'role' => 'staff', 'permissions' => [], 'staff_id' => $staff->id,
            ]);
        }

        expect(SalonInvitation::withoutGlobalScopes()->whereNotNull('staff_id')->count())->toBe(0);
    });

    it('does not create a profile for an invited co-owner', function () {
        SalonInvitation::factory()->for($this->salon)->withToken('secret-token')
            ->create(['email' => 'pedro@example.com', 'role' => SalonRole::Owner]);

        $this->actingAs($this->invitee)->post(route('invitations.accept', 'secret-token'));

        expect(Staff::withoutGlobalScopes()->count())->toBe(0);
    });

    it('offers only professionals without an account in the invite form', function () {
        Staff::factory()->for($this->salon)->create(['name' => 'Sin cuenta']);
        Staff::factory()->for($this->salon)->linkedTo($this->owner)->create();

        $this->actingAs($this->owner)->get(route('team.index'))->assertInertia(fn (Assert $page) => $page
            ->has('unlinkedStaff', 1)
            ->where('unlinkedStaff.0.name', 'Sin cuenta'));
    });
});

it('keeps the profile but detaches the account when someone leaves the team', function () {
    $owner = User::factory()->create();
    $salon = Salon::factory()->ownedBy($owner)->create();
    $member = SalonMember::factory()->for($salon)->create();
    $staff = Staff::factory()->for($salon)->linkedTo($member->user)->working([1])->create();

    $this->actingAs($owner)->delete(route('team.members.destroy', $member));

    expect($staff->refresh()->user_id)->toBeNull()->and($staff->trashed())->toBeFalse();
});

it('tells the interface whether the user has a professional profile here', function () {
    $owner = User::factory()->create();
    $salon = Salon::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentSalon.hasStaffProfile', false));

    Staff::factory()->for($salon)->linkedTo($owner)->create();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentSalon.hasStaffProfile', true));
});
