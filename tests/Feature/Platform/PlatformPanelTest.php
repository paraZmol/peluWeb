<?php

use App\Enums\SalonPermission;
use App\Enums\SalonStatus;
use App\Filament\Resources\Salons\Pages\EditSalon;
use App\Filament\Resources\Salons\SalonResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\User;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

it('sends guests to the app login', function () {
    $this->get('/plataforma')->assertRedirect();
    expect(auth()->check())->toBeFalse();
});

it('forbids regular users and salon owners', function () {
    $owner = User::factory()->withTwoFactor()->create();
    Salon::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)->get('/plataforma')->assertForbidden();
});

it('asks the platform admin to enable two-factor authentication first', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get('/plataforma')
        ->assertRedirect(route('security.edit'))
        // Said out loud: a silent bounce to the settings looks like a broken link.
        ->assertInertiaFlash('toast.type', 'error');
});

it('tells the platform admin apart on their own panel, and nobody else', function () {
    $admin = User::factory()->platformAdmin()->create();
    $client = User::factory()->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('platform.hasTwoFactor', false)->missing('auth.user.is_platform_admin'));
    $this->actingAs($client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('platform', null));
});

it('lets the platform admin with two-factor in', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();

    $this->actingAs($admin)->get('/plataforma')->assertOk();
    $this->actingAs($admin)->get('/plataforma/salons')->assertOk();
    $this->actingAs($admin)->get('/plataforma/users')->assertOk();
});

it('shows salons of every owner to the platform admin', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $salons = Salon::factory()->count(3)->ownedBy()->create();

    $this->actingAs($admin)
        ->get('/plataforma/salons')
        ->assertSee($salons->pluck('name')->all());
});

it('lets the platform admin suspend a salon without touching its slug', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $salon = Salon::factory()->ownedBy()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(EditSalon::class, ['record' => $salon->getRouteKey()])
        ->fillForm(['status' => SalonStatus::Suspended->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $salon->fresh();
    expect($fresh?->status)->toBe(SalonStatus::Suspended)
        ->and($fresh?->slug)->toBe($salon->slug);
});

it('grants and revokes the platform admin flag from the console only', function () {
    $user = User::factory()->create(['email' => 'yo@example.com']);

    $this->artisan('platform:grant-admin', ['email' => 'yo@example.com'])->assertSuccessful();
    expect($user->fresh()?->is_platform_admin)->toBeTrue();

    $this->artisan('platform:grant-admin', ['email' => 'yo@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()?->is_platform_admin)->toBeFalse();

    $this->artisan('platform:grant-admin', ['email' => 'nadie@example.com'])->assertFailed();
});

it('keeps users read-only in the panel, even for the platform admin', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);

    expect(UserResource::can('viewAny'))->toBeTrue()
        ->and(UserResource::can('update'))->toBeFalse()
        ->and(UserResource::can('delete'))->toBeFalse()
        ->and(UserResource::can('create'))->toBeFalse()
        ->and(SalonResource::can('forceDelete'))->toBeFalse();
});

it('says why when someone without a salon opens a salon screen', function () {
    $this->actingAs(User::factory()->create())->get(route('agenda.index'))
        ->assertRedirect(route('salons.create'))
        ->assertInertiaFlash('toast.type', 'error');
});

it('answers what is not theirs with a page that offers a way back', function () {
    $staff = User::factory()->create();
    $salon = Salon::factory()->create();
    SalonMember::factory()->for($salon)->for($staff)->create();

    $this->actingAs($staff)->get(route('team.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('public/error')->where('status', 403));
    $this->actingAs($staff)->get('/no-existe')->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('public/error')->where('status', 404));
    // The API keeps answering in JSON.
    $this->actingAs($staff)->getJson(route('team.index'))->assertForbidden()->assertJsonStructure(['message']);
});

it('shows the client list to who sees everyone\'s agenda, without the VIP switch', function () {
    $reception = User::factory()->create();
    $salon = Salon::factory()->create();
    SalonMember::factory()->for($salon)->for($reception)->withPermissions(SalonPermission::ViewAllSchedules)->create();
    $client = SalonClient::factory()->for($salon)->create();

    $this->actingAs($reception)->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('canManageVip', false));
    $this->actingAs($reception)->patch(route('clients.vip.update', $client), ['is_vip' => true])->assertForbidden();

    expect($client->refresh()->is_vip)->toBeFalse();
});
