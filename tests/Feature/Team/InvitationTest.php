<?php

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\User;
use App\Notifications\SalonInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
});

it('sends an invitation and stores only the hash of the token', function () {
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('team.invitations.store'), [
            'email' => 'Nueva.Barbera@Example.com',
            'role' => SalonRole::Staff->value,
            'permissions' => [SalonPermission::ViewAllSchedules->value],
        ])
        ->assertRedirect(route('team.index'));

    $invitation = SalonInvitation::firstOrFail();
    expect($invitation->email)->toBe('nueva.barbera@example.com');

    Notification::assertSentOnDemand(
        SalonInvitationNotification::class,
        function (SalonInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
            return $notifiable->routes['mail'] === 'nueva.barbera@example.com'
                && SalonInvitation::hashToken($notification->plainToken) === $invitation->token_hash
                && $notification->plainToken !== $invitation->token_hash;
        },
    );
});

it('replaces a previous pending invitation for the same email', function () {
    Notification::fake();
    $payload = ['email' => 'staff@example.com', 'role' => 'staff'];

    $this->actingAs($this->owner)->post(route('team.invitations.store'), $payload);
    $this->actingAs($this->owner)->post(route('team.invitations.store'), $payload);

    expect(SalonInvitation::count())->toBe(1);
});

it('does not invite someone who is already in the team', function () {
    Notification::fake();

    $this->actingAs($this->owner)
        ->from(route('team.index'))
        ->post(route('team.invitations.store'), ['email' => $this->owner->email, 'role' => 'staff'])
        ->assertRedirect(route('team.index'));

    expect(SalonInvitation::count())->toBe(0);
    Notification::assertNothingSent();
});

it('shows the invitation page to guests and remembers where to come back', function () {
    SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create();

    $this->get(route('invitations.show', 'secret-token'))
        ->assertOk()
        ->assertSessionHas('url.intended')
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/show')
            ->where('invitation.salonName', $this->salon->name));
});

it('lets the invited person join with the given role and permissions', function () {
    $invitee = User::factory()->create(['email' => 'barbero@example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create([
        'email' => 'barbero@example.com',
        'permissions' => [SalonPermission::ManageCash],
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', 'secret-token'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('current_salon_id', $this->salon->id);

    $membership = $invitee->fresh()?->membershipIn($this->salon);
    expect($membership?->role)->toBe(SalonRole::Staff)
        ->and($membership?->hasPermission(SalonPermission::ManageCash))->toBeTrue();
});

it('refuses an invitation meant for another email', function () {
    $intruder = User::factory()->create(['email' => 'intruso@example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create(['email' => 'barbero@example.com']);

    $this->actingAs($intruder)->post(route('invitations.accept', 'secret-token'));

    expect($intruder->fresh()?->membershipIn($this->salon))->toBeNull();
});

it('refuses expired, used or unknown invitations', function (string $state) {
    $invitee = User::factory()->create(['email' => 'barbero@example.com']);
    $factory = SalonInvitation::factory()->for($this->salon)->withToken('secret-token');
    match ($state) {
        'expired' => $factory->expired()->create(['email' => 'barbero@example.com']),
        'accepted' => $factory->accepted()->create(['email' => 'barbero@example.com']),
        'unknown' => null,
    };

    $this->actingAs($invitee)->post(route('invitations.accept', 'secret-token'));

    expect(SalonMember::where('user_id', $invitee->id)->exists())->toBeFalse();
})->with(['expired', 'accepted', 'unknown']);

it('refuses an invitation of a deleted salon', function () {
    $invitee = User::factory()->create(['email' => 'barbero@example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create(['email' => 'barbero@example.com']);
    $this->salon->delete();

    $this->actingAs($invitee)->post(route('invitations.accept', 'secret-token'));

    expect(SalonMember::where('user_id', $invitee->id)->exists())->toBeFalse();
});

it('requires a verified email to accept', function () {
    $invitee = User::factory()->unverified()->create(['email' => 'barbero@example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('secret-token')->create(['email' => 'barbero@example.com']);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', 'secret-token'))
        ->assertRedirect(route('verification.notice'));
});
