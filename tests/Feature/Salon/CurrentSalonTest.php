<?php

use App\Http\Middleware\ResolveCurrentSalon;
use App\Models\Salon;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses the first salon of the user when none is selected', function () {
    $owner = User::factory()->create();
    $salon = Salon::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentSalon.id', $salon->id)
            ->where('currentSalon.role', 'owner')
            ->has('salons', 1));
});

it('switches to another salon of the user', function () {
    $owner = User::factory()->create();
    Salon::factory()->ownedBy($owner)->create();
    $second = Salon::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)
        ->put(route('current-salon.update', $second))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(ResolveCurrentSalon::SESSION_KEY, $second->id);
});

it('refuses to switch to a salon the user does not belong to', function () {
    $owner = User::factory()->create();
    Salon::factory()->ownedBy($owner)->create();
    $foreign = Salon::factory()->ownedBy()->create();

    $this->actingAs($owner)
        ->put(route('current-salon.update', $foreign))
        ->assertForbidden();
});

it('ignores a tampered session pointing to a foreign salon', function () {
    $owner = User::factory()->create();
    $own = Salon::factory()->ownedBy($owner)->create();
    $foreign = Salon::factory()->ownedBy()->create();

    $this->actingAs($owner)
        ->withSession([ResolveCurrentSalon::SESSION_KEY => $foreign->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentSalon.id', $own->id));
});

it('never keeps a deleted salon as the current one', function () {
    $owner = User::factory()->create();
    $deleted = Salon::factory()->ownedBy($owner)->create();
    $deleted->delete();

    $this->actingAs($owner)
        ->withSession([ResolveCurrentSalon::SESSION_KEY => $deleted->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentSalon', null)->has('salons', 0));
});

it('sends users without a salon to create one when they open a salon page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('team.index'))
        ->assertRedirect(route('salons.create'));
});
