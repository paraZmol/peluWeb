<?php

use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use App\Http\Middleware\ResolveCurrentSalon;
use App\Models\Salon;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get(route('salons.create'))->assertRedirect(route('login'));
});

it('lets a user create a salon and become its owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('salons.store'), [
        'name' => 'Barbería El Turco',
        'business_type' => BusinessType::Barbershop->value,
        'clientele' => Clientele::Men->value,
        'phone' => '+51999888777', 'salon_terms' => '1',
    ]);

    $salon = Salon::firstOrFail();

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas(ResolveCurrentSalon::SESSION_KEY, $salon->id);
    expect($salon->slug)->toBe('barberia-el-turco')
        ->and($salon->status)->toBe(SalonStatus::Trial)
        ->and($salon->trial_ends_at?->isFuture())->toBeTrue()
        ->and($user->fresh()?->membershipIn($salon)?->role)->toBe(SalonRole::Owner);
});

it('gives each salon a unique slug', function () {
    $user = User::factory()->create();
    $payload = ['name' => 'Studio Bella', 'business_type' => 'beauty_salon', 'clientele' => 'everyone', 'salon_terms' => '1'];

    $this->actingAs($user)->post(route('salons.store'), $payload);
    $this->actingAs($user)->post(route('salons.store'), $payload);

    expect(Salon::pluck('slug')->unique())->toHaveCount(2);
});

it('ignores status and slug sent by the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('salons.store'), [
        'name' => 'Salón Pirata',
        'business_type' => 'unisex',
        'clientele' => 'everyone',
        'status' => SalonStatus::Active->value,
        'slug' => 'otro-salon', 'salon_terms' => '1',
    ]);

    $salon = Salon::firstOrFail();
    expect($salon->status)->toBe(SalonStatus::Trial)
        ->and($salon->slug)->toBe('salon-pirata');
});

it('validates the salon data', function (array $payload, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('salons.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['business_type' => 'unisex', 'clientele' => 'everyone'], 'name'],
    'unknown type' => [['name' => 'X Salón', 'business_type' => 'casino', 'clientele' => 'everyone'], 'business_type'],
    'unknown clientele' => [['name' => 'X Salón', 'business_type' => 'unisex', 'clientele' => 'pets'], 'clientele'],
    'bad phone' => [['name' => 'X Salón', 'business_type' => 'unisex', 'clientele' => 'everyone', 'phone' => 'llámame'], 'phone'],
]);
