<?php

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Filament\Resources\Salons\SalonResource;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 1 QA: role matrix and edge cases not covered by the per-feature tests.
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
});

// ---------------------------------------------------------------- visitors

it('sends visitors to login from every salon-area endpoint', function (string $method, string $route, array $params) {
    $this->{$method}(route($route, $params))->assertRedirect(route('login'));
})->with([
    'team' => ['get', 'team.index', []],
    'new salon' => ['get', 'salons.create', []],
    'store invitation' => ['post', 'team.invitations.store', []],
    'accept invitation' => ['post', 'invitations.accept', ['tok']],
    'switch salon' => ['put', 'current-salon.update', [1]],
    'delete member' => ['delete', 'team.members.destroy', [1]],
]);

it('blocks an unverified user from the team page and from creating salons', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('salons.create'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('team.index'))->assertRedirect(route('verification.notice'));
});

// ------------------------------------------------- client without a salon

it('forbids a client without salon from team writes (no current salon)', function () {
    $client = User::factory()->create();

    $this->actingAs($client)->post(route('team.invitations.store'), ['email' => 'a@example.com', 'role' => 'staff'])
        ->assertForbidden();
    $this->actingAs($client)->delete(route('team.invitations.destroy', 1))->assertNotFound();
    $this->actingAs($client)->get(route('dashboard'))->assertOk();
});

it('forbids a client without salon from the platform panel', function () {
    $this->actingAs(User::factory()->create())->get('/plataforma')->assertForbidden();
});

// -------------------------------------------------------------- staff

it('forbids staff without extra permissions from every team endpoint', function () {
    $staffUser = User::factory()->create();
    $staffMembership = SalonMember::factory()->for($this->salon)->for($staffUser)->create();
    $invitation = SalonInvitation::factory()->for($this->salon)->create();
    $ownerMembership = $this->owner->membershipIn($this->salon);

    $this->actingAs($staffUser)->get(route('team.index'))->assertForbidden();
    $this->actingAs($staffUser)->delete(route('team.invitations.destroy', $invitation))->assertForbidden();
    $this->actingAs($staffUser)->delete(route('team.members.destroy', $ownerMembership))->assertForbidden();
    $this->actingAs($staffUser)
        ->put(route('team.members.update', $staffMembership), ['role' => 'owner', 'permissions' => []])
        ->assertForbidden();

    expect($staffMembership->fresh()?->role)->toBe(SalonRole::Staff)
        ->and($ownerMembership?->fresh())->not->toBeNull()
        ->and(SalonInvitation::withoutGlobalScopes()->count())->toBe(1);
});

it('forbids staff from the platform panel', function () {
    $staffUser = User::factory()->withTwoFactor()->create();
    SalonMember::factory()->for($this->salon)->for($staffUser)->create();

    $this->actingAs($staffUser)->get('/plataforma')->assertForbidden();
});

it('exposes the staff role to the front end', function () {
    $staffUser = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($staffUser)
        ->withPermissions(SalonPermission::ManageCash)->create();

    $this->actingAs($staffUser)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentSalon.role', 'staff')
            ->where('currentSalon.id', $this->salon->id));
});

// ------------------------------------------------------- owner of other salon

it('does not let the owner of another salon invite into this salon', function () {
    Notification::fake();
    $otherOwner = User::factory()->create();
    $other = Salon::factory()->ownedBy($otherOwner)->create();

    $this->actingAs($otherOwner)->post(route('team.invitations.store'), [
        'email' => 'nuevo@example.com', 'role' => 'staff',
    ]);

    expect(SalonInvitation::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(0)
        ->and(SalonInvitation::withoutGlobalScopes()->where('salon_id', $other->id)->count())->toBe(1);
});

it('does not let the owner of another salon remove our owner', function () {
    $otherOwner = User::factory()->create();
    Salon::factory()->ownedBy($otherOwner)->create();
    $ownerMembership = $this->owner->membershipIn($this->salon);

    $this->actingAs($otherOwner)->delete(route('team.members.destroy', $ownerMembership))->assertNotFound();
    expect($ownerMembership?->fresh())->not->toBeNull();
});

// ------------------------------------------------------------ salon switching

it('refuses to switch to a nonexistent or soft-deleted salon', function () {
    $this->actingAs($this->owner)->put(route('current-salon.update', 999999))->assertNotFound();

    $gone = Salon::factory()->ownedBy($this->owner)->create();
    $gone->delete();
    $this->actingAs($this->owner)->put(route('current-salon.update', $gone->id))->assertNotFound();
});

it('refuses staff switching to a salon where they do not work', function () {
    $staffUser = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($staffUser)->create();
    $foreign = Salon::factory()->ownedBy()->create();

    $this->actingAs($staffUser)->put(route('current-salon.update', $foreign))->assertForbidden();
});

it('applies the role of the salon selected: owner in A, staff in B', function () {
    $b = Salon::factory()->ownedBy()->create();
    SalonMember::factory()->for($b)->for($this->owner)->create();

    $this->actingAs($this->owner)->put(route('current-salon.update', $b))->assertRedirect(route('dashboard'));
    $this->get(route('team.index'))->assertForbidden();

    $this->put(route('current-salon.update', $this->salon));
    $this->get(route('team.index'))->assertOk();
});

it('lists all salons of a multi-salon user', function () {
    $b = Salon::factory()->ownedBy()->create();
    SalonMember::factory()->for($b)->for($this->owner)->create();

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('salons', 2));
});

// ------------------------------------------------------- salon creation edge

it('builds a readable ASCII slug from accents and enie', function () {
    $this->actingAs($this->owner)->post(route('salons.store'), [
        'name' => 'Peluquería Ñandú Muñoz', 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1',
    ])->assertRedirect(route('dashboard'));

    $salon = Salon::where('name', 'Peluquería Ñandú Muñoz')->firstOrFail();
    expect($salon->slug)->toBe('peluqueria-nandu-munoz');
});

it('falls back to a valid unique slug for names without letters or digits', function () {
    foreach (range(1, 2) as $_) {
        $this->actingAs($this->owner)->post(route('salons.store'), [
            'name' => '&.,', 'business_type' => 'barbershop', 'clientele' => 'men', 'salon_terms' => '1',
        ])->assertRedirect(route('dashboard'));
    }

    $slugs = Salon::where('name', '&.,')->pluck('slug');
    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2)
        ->and($slugs->first())->toMatch('/^[a-z0-9-]+$/');
});

it('rejects names with emojis or html with a validation error, not a 500', function (string $name) {
    $this->actingAs($this->owner)->post(route('salons.store'), [
        'name' => $name, 'business_type' => 'barbershop', 'clientele' => 'men', 'salon_terms' => '1',
    ])->assertSessionHasErrors('name');

    expect(Salon::where('name', $name)->exists())->toBeFalse();
})->with(['💈✂️', 'Corte 💈 Fino', '<script>alert(1)</script> Salon', 'Salon <b>x</b>']);

it('accepts accents, enie, apostrophes and ampersands in the name', function () {
    $this->actingAs($this->owner)->post(route('salons.store'), [
        'name' => "D'Ñandú & Hijos · Peluquería", 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(Salon::where('name', "D'Ñandú & Hijos · Peluquería")->firstOrFail()->slug)->toMatch('/^[a-z0-9-]+$/');
});

it('does not reuse the slug of a soft-deleted salon', function () {
    $old = Salon::factory()->create(['slug' => 'estilo-nuevo']);
    $old->delete();

    $this->actingAs($this->owner)->post(route('salons.store'), [
        'name' => 'Estilo Nuevo', 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1',
    ])->assertRedirect(route('dashboard'));

    expect(Salon::where('name', 'Estilo Nuevo')->firstOrFail()->slug)->not->toBe('estilo-nuevo');
});

it('enforces the name length limits', function (string $name, bool $valid) {
    $response = $this->actingAs($this->owner)->post(route('salons.store'), [
        'name' => $name, 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1',
    ]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('name');
})->with([
    'one char' => ['A', false],
    'blank' => ['     ', false],
    'two chars' => ['Ña', true],
    '120 chars' => [str_repeat('ñ', 120), true],
    '121 chars' => [str_repeat('ñ', 121), false],
]);

it('makes the creator owner of the new salon without losing the previous one', function () {
    $this->actingAs($this->owner)->post(route('salons.store'), [
        'name' => 'Segundo', 'business_type' => 'unisex', 'clientele' => 'everyone', 'salon_terms' => '1',
    ]);

    expect(SalonMember::where('user_id', $this->owner->id)->count())->toBe(2);
});

// ----------------------------------------------------------- invitations

it('accepts an invitation when the account email has capital letters', function () {
    $invitee = User::factory()->create(['email' => 'Barbero.Uno@Example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('tok-1')->create(['email' => 'barbero.uno@example.com']);

    $this->actingAs($invitee)->post(route('invitations.accept', 'tok-1'))->assertRedirect(route('dashboard'));

    expect($invitee->fresh()?->membershipIn($this->salon))->not->toBeNull();
});

it('does not invite a member who is already in the team when the email is uppercase', function () {
    Notification::fake();
    $this->actingAs($this->owner)->post(route('team.invitations.store'), [
        'email' => strtoupper($this->owner->email), 'role' => 'staff',
    ]);

    expect(SalonInvitation::withoutGlobalScopes()->count())->toBe(0);
});

it('does not break when the same invitation is accepted twice', function () {
    $invitee = User::factory()->create(['email' => 'barbero@example.com']);
    SalonInvitation::factory()->for($this->salon)->withToken('tok-2')->create(['email' => 'barbero@example.com']);

    $this->actingAs($invitee)->post(route('invitations.accept', 'tok-2'))->assertRedirect(route('dashboard'));
    $acceptedAt = SalonInvitation::withoutGlobalScopes()->first()?->accepted_at;
    $this->actingAs($invitee)->post(route('invitations.accept', 'tok-2'))->assertRedirect();

    expect(SalonMember::where('user_id', $invitee->id)->count())->toBe(1)
        ->and(SalonInvitation::withoutGlobalScopes()->first()?->accepted_at?->equalTo($acceptedAt))->toBeTrue();
});

it('does not let a second account use an already accepted link', function () {
    $first = User::factory()->create(['email' => 'uno@example.com']);
    $second = User::factory()->create(['email' => 'uno@example.com.pe']);
    SalonInvitation::factory()->for($this->salon)->withToken('tok-3')->create(['email' => 'uno@example.com']);

    $this->actingAs($first)->post(route('invitations.accept', 'tok-3'));
    $this->actingAs($second)->post(route('invitations.accept', 'tok-3'));

    expect($second->fresh()?->membershipIn($this->salon))->toBeNull();
});

it('shows an invalid-invitation page for expired, used and unknown links', function (string $state) {
    $factory = SalonInvitation::factory()->for($this->salon)->withToken('tok-4');
    match ($state) {
        'expired' => $factory->expired()->create(),
        'used' => $factory->accepted()->create(),
        'unknown' => null,
    };

    $this->get(route('invitations.show', 'tok-4'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invitation', null));
})->with(['expired', 'used', 'unknown']);

it('never leaks the token hash in the invitation page', function () {
    SalonInvitation::factory()->for($this->salon)->withToken('tok-5')->create();

    $json = $this->get(route('invitations.show', 'tok-5'))->getContent();

    expect($json)->not->toContain(SalonInvitation::hashToken('tok-5'));
});

it('expires an invitation 7 days after it was sent', function () {
    Notification::fake();
    $this->actingAs($this->owner)->post(route('team.invitations.store'), ['email' => 'x@example.com', 'role' => 'staff']);

    $invitation = SalonInvitation::withoutGlobalScopes()->firstOrFail();
    expect($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();

    $this->travel(8)->days();
    expect($invitation->fresh()?->isPending())->toBeFalse();
});

it('drops permissions when inviting an owner', function () {
    Notification::fake();
    $this->actingAs($this->owner)->post(route('team.invitations.store'), [
        'email' => 'socio@example.com', 'role' => 'owner', 'permissions' => ['manage_cash'],
    ]);

    expect(SalonInvitation::withoutGlobalScopes()->firstOrFail()->permissions)->toBeEmpty();
});

it('validates the invitation payload', function (array $payload, string $field) {
    $this->actingAs($this->owner)->post(route('team.invitations.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'no email' => [['role' => 'staff'], 'email'],
    'bad email' => [['email' => 'no-es-correo', 'role' => 'staff'], 'email'],
    'unknown role' => [['email' => 'a@example.com', 'role' => 'superadmin'], 'role'],
    'repeated permission' => [['email' => 'a@example.com', 'role' => 'staff', 'permissions' => ['manage_cash', 'manage_cash']], 'permissions.0'],
    'email too long' => [['email' => str_repeat('a', 250).'@example.com', 'role' => 'staff'], 'email'],
]);

it('keeps an owner as owner when an old staff invitation for them is accepted', function () {
    $partner = User::factory()->create(['email' => 'socio@example.com']);
    SalonMember::factory()->for($this->salon)->for($partner)->owner()->create();
    SalonInvitation::factory()->for($this->salon)->withToken('tok-6')->create(['email' => 'socio@example.com']);

    $this->actingAs($partner)->post(route('invitations.accept', 'tok-6'));

    expect($partner->fresh()?->membershipIn($this->salon)?->role)->toBe(SalonRole::Owner);
});

it('cannot leave the salon without owner through a stale staff invitation', function () {
    SalonInvitation::factory()->for($this->salon)->withToken('tok-7')->create(['email' => $this->owner->email]);

    $this->actingAs($this->owner)->post(route('invitations.accept', 'tok-7'));

    expect($this->salon->ownersCount())->toBeGreaterThanOrEqual(1);
});

// ------------------------------------------------------------ last owner

it('lets two owners demote each other only once', function () {
    $second = User::factory()->create();
    $secondMembership = SalonMember::factory()->for($this->salon)->for($second)->owner()->create();
    $firstMembership = $this->owner->membershipIn($this->salon);

    $this->actingAs($this->owner)->put(route('team.members.update', $secondMembership), ['role' => 'staff', 'permissions' => []]);
    $this->actingAs($second)->put(route('team.members.update', $firstMembership), ['role' => 'staff', 'permissions' => []])
        ->assertForbidden();

    expect($this->salon->ownersCount())->toBe(1);
});

it('refuses to demote or remove the only owner and answers 422 to JSON clients', function () {
    $membership = $this->owner->membershipIn($this->salon);

    $this->actingAs($this->owner)
        ->putJson(route('team.members.update', $membership), ['role' => 'staff', 'permissions' => []])
        ->assertUnprocessable();
    $this->actingAs($this->owner)->deleteJson(route('team.members.destroy', $membership))->assertUnprocessable();

    expect($this->salon->ownersCount())->toBe(1);
});

it('does not leave a salon without owner when its sole owner deletes their account', function () {
    $this->actingAs($this->owner)->delete(route('profile.destroy'), ['password' => 'password']);

    expect($this->salon->fresh()?->ownersCount())->toBeGreaterThanOrEqual(1);
});

it('requires the current password to delete the account', function () {
    $this->actingAs($this->owner)->delete(route('profile.destroy'), ['password' => 'incorrecta'])
        ->assertSessionHasErrors('password');

    expect($this->owner->fresh())->not->toBeNull();
});

// ------------------------------------------------------------ super admin

it('keeps a platform admin without two-factor out of every panel page', function (string $path) {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)->get($path)->assertRedirect(route('security.edit'));
})->with(['/plataforma', '/plataforma/salons', '/plataforma/users']);

it('forbids an unverified platform admin even with two-factor', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->unverified()->create();

    $this->actingAs($admin)->get('/plataforma')->assertForbidden();
});

it('does not give a salon owner platform rights by owning many salons', function () {
    Salon::factory()->count(3)->ownedBy($this->owner)->create();

    expect($this->owner->fresh()?->is_platform_admin)->toBeFalse();
    $this->actingAs($this->owner)->get('/plataforma/salons')->assertForbidden();
});

it('keeps salon creation as owner self-service, not from the panel (PLAN §11, 2026-10-04)', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);

    expect(SalonResource::can('create'))->toBeFalse()
        ->and(SalonResource::can('viewAny'))->toBeTrue();
});

it('keeps the platform admin flag out of mass assignment on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Intruso', 'email' => 'intruso@example.com', 'password' => 'password-Segura-1',
        'password_confirmation' => 'password-Segura-1', 'is_platform_admin' => true, 'terms' => 'on',
    ]);

    expect(User::where('email', 'intruso@example.com')->first()?->is_platform_admin)->not->toBeTrue();
});
