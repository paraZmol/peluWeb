<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function fakeGoogleUser(string $email, bool $verified = true, string $id = 'google-123'): void
{
    $googleUser = (new GoogleUser)
        ->setRaw(['email_verified' => $verified])
        ->map(['id' => $id, 'name' => 'Lucía Pérez', 'email' => $email]);

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);
}

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client',
        'services.google.client_secret' => 'test-secret',
    ]);
});

it('is unavailable until Google credentials are configured', function () {
    config(['services.google.client_id' => null]);

    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();
});

it('creates a verified account for a new Google user and signs them in', function () {
    fakeGoogleUser('lucia@gmail.com');

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $user = User::where('email', 'lucia@gmail.com')->firstOrFail();
    expect($user->google_id)->toBe('google-123')
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('links Google to an existing account with the same verified email', function () {
    $existing = User::factory()->create(['email' => 'lucia@gmail.com']);
    fakeGoogleUser('Lucia@Gmail.com');

    $this->get(route('google.callback'));

    expect($existing->fresh()?->google_id)->toBe('google-123');
    $this->assertAuthenticatedAs($existing);
});

it('refuses a Google email that Google itself did not verify', function () {
    $victim = User::factory()->create(['email' => 'victima@example.com']);
    fakeGoogleUser('victima@example.com', verified: false);

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect($victim->fresh()?->google_id)->toBeNull();
});

it('still asks for the second factor when the account has 2FA', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'lucia@gmail.com']);
    fakeGoogleUser('lucia@gmail.com');

    $this->get(route('google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

it('shows the Google button only when it is configured', function () {
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('features.googleLogin', true));

    config(['services.google.client_id' => null]);
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('features.googleLogin', false));
});
