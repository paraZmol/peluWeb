<?php

use App\Enums\SalonStatus;
use App\Filament\Resources\Salons\Pages\EditSalon;
use App\Filament\Resources\Salons\SalonResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Salon;
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
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_platform_admin', true)->where('auth.user.two_factor_enabled', false));
    $this->actingAs($client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_platform_admin', false));
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
