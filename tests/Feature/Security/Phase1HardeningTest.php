<?php

use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\User;
use App\Notifications\SalonInvitationNotification;
use App\Support\CurrentSalon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

/*
| Regression tests for the Phase 1 security audit (2026-10-04).
*/

function googleSignsIn(string $email, string $id = 'google-999'): void
{
    $googleUser = (new GoogleUser)
        ->setRaw(['email_verified' => true])
        ->map(['id' => $id, 'name' => 'Víctima', 'email' => $email]);

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
}

it('evicts whoever pre-registered an unverified account before linking Google', function () {
    $squatted = User::factory()->unverified()->create([
        'email' => 'victima@gmail.com',
        'password' => Hash::make('clave-del-atacante'),
    ]);
    DB::table('sessions')->insert([
        'id' => 'attacker-session', 'user_id' => $squatted->id, 'payload' => '', 'last_activity' => time(),
    ]);
    googleSignsIn('victima@gmail.com');

    $this->get(route('google.callback'));

    $fresh = $squatted->fresh();
    expect(Hash::check('clave-del-atacante', (string) $fresh?->password))->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'attacker-session')->exists())->toBeFalse()
        ->and($fresh?->email_verified_at)->not->toBeNull();
});

it('keeps the password of an already verified account when linking Google', function () {
    $user = User::factory()->create(['email' => 'lucia@gmail.com', 'password' => Hash::make('mi-clave')]);
    googleSignsIn('lucia@gmail.com');

    $this->get(route('google.callback'));

    expect(Hash::check('mi-clave', (string) $user->fresh()?->password))->toBeTrue();
});

it('does not silently replace a different linked Google account', function () {
    User::factory()->create(['email' => 'lucia@gmail.com', 'google_id' => 'original-id']);
    googleSignsIn('lucia@gmail.com', id: 'other-id');

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects salon names with link or markup syntax', function (string $name) {
    $this->actingAs(User::factory()->create())
        ->post(route('salons.store'), ['name' => $name, 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1'])
        ->assertSessionHasErrors('name');
})->with([
    'markdown link' => ['Aviso [verifica aquí](https://evil.tld)'],
    'html' => ['<b>Salón</b>'],
]);

it('accepts real salon names with accents, ñ and punctuation', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('salons.store'), ['name' => "Peluquería D'Ángela & Ñusta, S.A.C.", 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1'])
        ->assertSessionHasNoErrors();
});

it('escapes markdown from the salon name inside the invitation email', function () {
    $salon = Salon::factory()->create(['name' => 'Salón [click](https://evil.tld)']);
    $invitation = SalonInvitation::factory()->for($salon)->create();

    $html = (string) (new SalonInvitationNotification($invitation, 'token'))
        ->toMail(new stdClass)
        ->render();

    expect($html)->not->toContain('href="https://evil.tld"');
});

it('sends security headers that forbid framing', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('makes suspended salons and expired trials read only', function (array $state, bool $writable) {
    $owner = User::factory()->create();
    Salon::factory()->ownedBy($owner)->create($state);
    Notification::fake();

    $this->actingAs($owner)
        ->post(route('team.invitations.store'), ['email' => 'nuevo@example.com', 'role' => 'staff']);

    expect(SalonInvitation::withoutGlobalScopes()->exists())->toBe($writable);
})->with([
    'suspended' => [['status' => SalonStatus::Suspended], false],
    'trial expired' => [['status' => SalonStatus::Trial, 'trial_ends_at' => now()->subDays(10)], false], // past its 7 days of grace
    'trial ended, in its grace' => [['status' => SalonStatus::Trial, 'trial_ends_at' => now()->subDays(3)], true],
    'paid until long ago' => [['status' => SalonStatus::Active, 'trial_ends_at' => now()->subDays(60), 'paid_until' => now()->subDays(10)], false],
    'paid until today' => [['status' => SalonStatus::Active, 'trial_ends_at' => now()->subDays(60), 'paid_until' => now()], true],
    'past due (grace period)' => [['status' => SalonStatus::PastDue], true],
    'active' => [['status' => SalonStatus::Active], true],
]);

it('throttles registration attempts per IP', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('register.store'), ['email' => "x{$attempt}@example.com"]);
    }

    $this->post(route('register.store'), ['email' => 'x6@example.com'])->assertStatus(429);
});

it('answers the same for known and unknown emails when resetting a password', function () {
    User::factory()->create(['email' => 'existe@example.com']);

    $known = $this->post(route('password.email'), ['email' => 'existe@example.com']);
    $knownStatus = session('status');
    $unknown = $this->post(route('password.email'), ['email' => 'no-existe@example.com']);

    $known->assertSessionHasNoErrors();
    $unknown->assertSessionHasNoErrors();
    expect(session('status'))->toBe($knownStatus);
});

it('fails closed: without a current salon, salon data is invisible', function () {
    SalonInvitation::factory()->count(2)->create();
    app(CurrentSalon::class)->set(null);

    expect(SalonInvitation::count())->toBe(0)
        ->and(SalonInvitation::withoutGlobalScope('salon')->count())->toBe(2);
});

it('refuses to create salon data without knowing the salon', function () {
    app(CurrentSalon::class)->set(null);

    $invitation = SalonInvitation::factory()->make(['salon_id' => null]);

    expect(fn () => $invitation->save())->toThrow(LogicException::class);
});

it('cancels the pending invitations of someone removed from the team', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $salon = Salon::factory()->ownedBy($owner)->create();
    $coOwner = SalonMember::factory()->for($salon)->owner()->create();
    SalonInvitation::factory()->for($salon)->create(['invited_by' => $coOwner->user_id, 'role' => SalonRole::Owner]);

    $this->actingAs($owner)->delete(route('team.members.destroy', $coOwner));

    expect(SalonInvitation::withoutGlobalScopes()->where('invited_by', $coOwner->user_id)->exists())->toBeFalse();
});

it('never downgrades an existing owner through an invitation', function () {
    $owner = User::factory()->create(['email' => 'duena@example.com']);
    $salon = Salon::factory()->ownedBy($owner)->create();
    SalonInvitation::factory()->for($salon)->withToken('tok')->create(['email' => 'duena@example.com', 'role' => SalonRole::Staff]);

    $this->actingAs($owner)->post(route('invitations.accept', 'tok'));

    expect($owner->fresh()?->membershipIn($salon)?->role)->toBe(SalonRole::Owner);
});

it('shares only an allow-list of user fields with the browser', function () {
    $owner = User::factory()->platformAdmin()->create();
    Salon::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('auth.user.is_platform_admin')
            ->missing('auth.user.memberships')
            ->missing('auth.user.two_factor_secret')
            ->has('auth.user.avatar'));
});
