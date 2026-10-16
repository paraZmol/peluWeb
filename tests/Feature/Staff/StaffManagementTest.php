<?php

use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
});

function staffOf(Salon $salon): Builder
{
    return Staff::withoutGlobalScopes()->where('salon_id', $salon->id);
}

it('adds a professional without an account who performs every service', function () {
    Service::factory()->for($this->salon)->count(3)->create();

    $this->actingAs($this->owner)
        ->post(route('staff.store'), ['name' => '  Martín Soto ', 'title' => 'Barbero'])
        ->assertRedirect();

    $staff = staffOf($this->salon)->firstOrFail();
    expect($staff->name)->toBe('Martín Soto')
        ->and($staff->user_id)->toBeNull()
        ->and($staff->is_active)->toBeTrue()
        ->and(DB::table('service_staff')->where('staff_id', $staff->id)->count())->toBe(3);
});

it('lists professionals with their work days and the accounts that can be linked', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    Staff::factory()->for($this->salon)->working([1, 3])->create(['name' => 'Ana']);
    Staff::factory()->for(Salon::factory()->create())->create(['name' => 'De otro salón']);

    $this->actingAs($this->owner)
        ->get(route('staff.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('owner/staff/index')
            ->has('staff', 1)
            ->where('staff.0.name', 'Ana')
            ->where('staff.0.workDays', [1, 3])
            ->has('linkableMembers', 2)
            ->where('linkableMembers.1.userId', $member->user_id));
});

it('links a professional to a team account only once', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $first = Staff::factory()->for($this->salon)->create();
    $second = Staff::factory()->for($this->salon)->create();

    $this->actingAs($this->owner)
        ->put(route('staff.update', $first), ['name' => $first->name, 'user_id' => $member->user_id])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->owner)
        ->put(route('staff.update', $second), ['name' => $second->name, 'user_id' => $member->user_id]);

    expect($first->refresh()->user_id)->toBe($member->user_id)
        ->and($second->refresh()->user_id)->toBeNull();
});

it('refuses to link an account that is not in the team', function () {
    $outsider = User::factory()->create();
    $staff = Staff::factory()->for($this->salon)->create();

    $this->actingAs($this->owner)
        ->put(route('staff.update', $staff), ['name' => $staff->name, 'user_id' => $outsider->id]);

    expect($staff->refresh()->user_id)->toBeNull();
});

it('pauses and renames a professional', function () {
    $staff = Staff::factory()->for($this->salon)->create();

    $this->actingAs($this->owner)
        ->put(route('staff.update', $staff), ['name' => 'Nuevo Nombre', 'is_active' => false])
        ->assertRedirect(route('staff.index'));

    expect($staff->refresh()->name)->toBe('Nuevo Nombre')->and($staff->is_active)->toBeFalse();
});

it('removes a professional, their hours and the link to the account', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $staff = Staff::factory()->for($this->salon)->linkedTo($member->user)->working([1, 2])->create();

    $this->actingAs($this->owner)->delete(route('staff.destroy', $staff))->assertRedirect(route('staff.index'));

    $removed = Staff::withoutGlobalScopes()->withTrashed()->findOrFail($staff->id);
    expect($removed->trashed())->toBeTrue()
        ->and($removed->user_id)->toBeNull()
        ->and(StaffSchedule::withoutGlobalScopes()->where('staff_id', $staff->id)->count())->toBe(0);
});

it('validates the name', function (array $payload) {
    $this->actingAs($this->owner)->post(route('staff.store'), $payload)->assertSessionHasErrors('name');
})->with([
    'empty' => [['name' => '']],
    'markup' => [['name' => '<b>Ana</b>']],
    'too long' => [['name' => str_repeat('a', 81)]],
]);

it('stops at the maximum number of professionals', function () {
    // Without an agenda, so the plan's limit is not what stops it.
    Staff::factory()->for($this->salon)->count(Staff::MAX_PER_SALON)->create(['is_active' => false]);

    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Uno más']);

    expect(staffOf($this->salon)->count())->toBe(Staff::MAX_PER_SALON);
});

it('keeps the list and its changes for owners only', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $staff = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();

    $this->actingAs($member->user)->get(route('staff.index'))->assertForbidden();
    $this->actingAs($member->user)->post(route('staff.store'), ['name' => 'Colado'])->assertForbidden();
    $this->actingAs($member->user)->put(route('staff.update', $staff), ['name' => 'Otro'])->assertForbidden();
    $this->actingAs($member->user)->delete(route('staff.destroy', $staff))->assertForbidden();
});

it('hides professionals of another salon from every action', function () {
    $foreign = Staff::factory()->for(Salon::factory()->ownedBy()->create())->create(['name' => 'Ajeno']);

    $this->actingAs($this->owner)->put(route('staff.update', $foreign), ['name' => 'Robado'])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('staff.destroy', $foreign))->assertNotFound();
    $this->actingAs($this->owner)->get(route('staff.schedule.show', $foreign))->assertNotFound();
    $this->actingAs($this->owner)->put(route('staff.schedule.update', $foreign), ['ranges' => []])->assertNotFound();
    $this->actingAs($this->owner)->put(route('staff.services.update', $foreign), ['services' => []])->assertNotFound();
    $this->actingAs($this->owner)->post(route('staff.photo.store', $foreign))->assertNotFound();

    expect($foreign->refresh()->name)->toBe('Ajeno')->and($foreign->trashed())->toBeFalse();
});

it('stores the photo as a square WebP and lets the professional change their own', function () {
    Storage::fake('public');
    $member = SalonMember::factory()->for($this->salon)->create();
    $staff = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();
    $other = Staff::factory()->for($this->salon)->create();

    $this->actingAs($member->user)
        ->post(route('staff.photo.store', $staff), ['image' => UploadedFile::fake()->image('yo.jpg', 900, 600)])
        ->assertSessionHasNoErrors();
    $this->actingAs($member->user)
        ->post(route('staff.photo.store', $other), ['image' => UploadedFile::fake()->image('yo.jpg')])
        ->assertForbidden();

    $path = $staff->refresh()->photo_path;
    expect($path)->toEndWith('.webp')->and($other->refresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->owner)->delete(route('staff.photo.destroy', $staff));
    Storage::disk('public')->assertMissing($path);
});

it('cannot change anything while the salon is suspended', function () {
    $this->salon->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Nadie']);

    expect(staffOf($this->salon)->count())->toBe(0);
});
