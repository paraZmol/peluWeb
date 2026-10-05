<?php

use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->salon = Salon::factory()->ownedBy()->create([
        'slug' => 'barberia-lucho',
        'name' => 'Barbería Lucho',
        'phone' => '999888777',
        'description' => 'Cortes clásicos desde 1998.',
        'latitude' => -12.1211,
        'longitude' => -77.0297,
    ]);
});

it('is visible to visitors without an account', function () {
    $this->get('/s/barberia-lucho')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/salon')
            ->where('salon.name', 'Barbería Lucho')
            ->where('salon.whatsappUrl', fn (string $url) => str_starts_with($url, 'https://wa.me/51999888777'))
            ->where('salon.location.directionsUrl', fn (string $url) => str_contains($url, '-12.1211')));
});

it('shows only active bookable services of this salon', function () {
    Service::factory()->for($this->salon)->create(['name' => 'Degradado medio']);
    Service::factory()->for($this->salon)->create(['name' => 'Servicio pausado', 'is_active' => false]);
    Service::factory()->for($this->salon)->create(['name' => 'Lavado extra', 'is_addon' => true]);
    Service::factory()->for(Salon::factory()->create())->create(['name' => 'De otro salón']);

    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->has('salon.serviceGroups', 1)
        ->has('salon.serviceGroups.0.services', 1)
        ->where('salon.serviceGroups.0.services.0.name', 'Degradado medio'));
});

it('works the same for a signed-in owner of another salon', function () {
    $otherOwner = User::factory()->create();
    Salon::factory()->ownedBy($otherOwner)->create();
    Service::factory()->for($this->salon)->create(['name' => 'Degradado medio']);

    $this->actingAs($otherOwner)->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->where('salon.serviceGroups.0.services.0.name', 'Degradado medio'));
});

it('does not expose internal salon data', function () {
    $this->get('/s/barberia-lucho')->assertInertia(fn (Assert $page) => $page
        ->missing('salon.status')
        ->missing('salon.trial_ends_at')
        ->missing('salon.id')
        ->missing('salon.phone'));
});

it('renders link preview tags on the server', function () {
    $this->get('/s/barberia-lucho')
        ->assertSee('<meta property="og:title" content="Barbería Lucho', false)
        ->assertSee('Cortes clásicos desde 1998.', false);
});

it('hides suspended and deleted salons', function () {
    $this->salon->forceFill(['status' => 'suspended'])->save();
    $this->get('/s/barberia-lucho')->assertNotFound();

    $other = Salon::factory()->create(['slug' => 'cerrado']);
    $other->delete();
    $this->get('/s/cerrado')->assertNotFound();
});
