<?php

use App\Enums\AcceptanceChannel;
use App\Models\LegalAcceptance;
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

it('shows a new Google user the terms before creating their account', function () {
    fakeGoogleUser('lucia@gmail.com');

    $this->get(route('google.callback'))->assertRedirect(route('google.terms'));

    $this->assertGuest();
    expect(User::where('email', 'lucia@gmail.com')->exists())->toBeFalse();
    $this->get(route('google.terms'))->assertInertia(fn ($page) => $page
        ->component('auth/accept-terms')
        ->where('mode', 'google')
        ->where('account.email', 'lucia@gmail.com'));
});

it('creates the verified account once the new Google user accepts the terms', function () {
    fakeGoogleUser('lucia@gmail.com');
    $this->get(route('google.callback'));

    $this->post(route('google.terms.store'))->assertSessionHasErrors('terms');
    expect(User::count())->toBe(0);

    $this->post(route('google.terms.store'), ['terms' => 'on'])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'lucia@gmail.com')->firstOrFail();
    expect($user->google_id)->toBe('google-123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(LegalAcceptance::where('user_id', $user->id)->pluck('channel')->unique()->all())->toBe([AcceptanceChannel::Google]);
    $this->assertAuthenticatedAs($user);
});

it('sends the Google terms screen back to sign in when nobody is waiting on it', function () {
    $this->get(route('google.terms'))->assertRedirect(route('login'));
    $this->post(route('google.terms.store'), ['terms' => 'on'])->assertRedirect(route('login'));
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
