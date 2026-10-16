<?php

use App\Models\User;

it('cannot be granted through the profile form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'is_platform_admin' => true, 'terms' => 'on',
    ]);

    expect($user->fresh()?->is_platform_admin)->toBeFalse();
});

it('cannot be granted at registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'password-segura-123',
        'password_confirmation' => 'password-segura-123',
        'is_platform_admin' => true, 'terms' => 'on',
    ]);

    expect(User::where('email', 'mallory@example.com')->first()?->is_platform_admin)->toBeFalse();
});

it('saves an optional WhatsApp phone in the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '+51987654321',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()?->phone)->toBe('+51987654321');
});

it('accepts phones typed with spaces and dashes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '+51 987-654 321',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()?->phone)->toBe('+51987654321');
});
