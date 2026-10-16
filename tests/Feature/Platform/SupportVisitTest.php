<?php

use App\Actions\Platform\SupportVisits;
use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Filament\Resources\Salons\Pages\ViewSalon;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Models\SupportVisit;
use App\Models\User;
use App\Notifications\SupportVisitStartedNotification;
use App\Support\CurrentSalon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/*
| Step 7.4: "Entrar como salón" for support — the platform admin sees the
| salon as its owner would, changes nothing, and every visit is recorded.
*/

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->salon = app(CreateSalon::class)->handle(User::factory()->create(), 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
});

function openVisit(User $admin, Salon $salon): SupportVisit
{
    $visit = new SupportVisit;
    $visit->admin_id = $admin->id;
    $visit->salon_id = $salon->id;
    $visit->reason = 'Revisar reservas';
    $visit->started_at = now();
    $visit->save();

    return $visit;
}

it('records who went in, why and from where, and opens the salon as its owner sees it', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('supportVisit', ['reason' => '  No le aparecen las reservas  '])
        ->assertRedirect(route('dashboard'));

    $visit = SupportVisit::query()->sole();
    expect($visit)
        ->admin_id->toBe($this->admin->id)
        ->salon_id->toBe($this->salon->id)
        ->reason->toBe('No le aparecen las reservas')
        ->ended_at->toBeNull();

    $this->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->get(route('staff.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentSalon.id', $this->salon->id)
            ->where('currentSalon.role', 'owner')
            ->where('support.salonName', 'Barbería Central'));

    // The owner membership only lived in that request.
    expect(SalonMember::query()->where('user_id', $this->admin->id)->exists())->toBeFalse();
});

it('changes nothing while in support', function () {
    $visit = openVisit($this->admin, $this->salon);

    $this->actingAs($this->admin)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->post(route('staff.store'), ['name' => 'Intruso'])
        ->assertInertiaFlash('toast.message', 'Estás en modo soporte: puedes ver todo, pero no hacer cambios.');

    expect(Staff::withoutGlobalScopes()->where('name', 'Intruso')->exists())->toBeFalse();
});

it('ends the visit when left, and the salon is no longer open to the admin', function () {
    $visit = openVisit($this->admin, $this->salon);

    $this->actingAs($this->admin)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->post(route('support.end'))
        ->assertRedirect();

    expect($visit->refresh()->ended_at)->not->toBeNull();
    // A new request starts afresh, as in the app: the admin and the current salon.
    app()->forgetScopedInstances();
    $this->actingAs($this->admin->fresh())->get(route('staff.index'))->assertRedirect(route('salons.create'));
});

it('ends by itself after two hours', function () {
    $visit = openVisit($this->admin, $this->salon);
    $this->travel(3)->days();

    $this->actingAs($this->admin)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->get(route('staff.index'))
        ->assertRedirect(route('salons.create'));

    // The record says when it really ended: at its limit, not when noticed.
    expect($visit->refresh()->ended_at->equalTo($visit->started_at->addMinutes(SupportVisit::MAX_MINUTES)))->toBeTrue();
});

it('keeps health notes and client pictures closed to support', function () {
    $client = SalonClient::factory()->for($this->salon)->create();
    $visit = openVisit($this->admin, $this->salon);
    $owner = User::query()->whereKey($this->salon->members()->value('user_id'))->firstOrFail();

    expect(Gate::forUser($owner)->allows('viewCare', $client))->toBeTrue();

    app(CurrentSalon::class)->setSupportVisit($visit);

    expect(Gate::forUser($this->admin)->allows('viewCare', $client))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('managePhotos', $client))->toBeFalse();
});

it('opens nothing to someone who is not the platform admin with two-factor', function (Closure $who) {
    $visit = openVisit($this->admin, $this->salon);
    $user = $who();
    $visit->forceFill(['admin_id' => $user->id])->save();

    $this->actingAs($user)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->get(route('staff.index'))
        ->assertRedirect(route('salons.create'));
})->with([
    'a regular user' => [fn () => User::factory()->create()],
    'an admin without two-factor' => [fn () => User::factory()->platformAdmin()->create()],
]);

it('does not let one admin ride another admin\'s visit', function () {
    $visit = openVisit($this->admin, $this->salon);
    $other = User::factory()->platformAdmin()->withTwoFactor()->create();

    $this->actingAs($other)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->get(route('staff.index'))
        ->assertRedirect(route('salons.create'));
});

it('tells the owners by email who came in and why, and lists the visit in Mi plan', function () {
    Notification::fake();
    $owner = SalonMember::query()->where('salon_id', $this->salon->id)->sole()->user;
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('supportVisit', ['reason' => 'No le aparecen las reservas']);

    Notification::assertSentTo($owner, SupportVisitStartedNotification::class, fn (SupportVisitStartedNotification $notification) => str_contains(
        implode(' ', $notification->toMail($owner)->introLines),
        'No le aparecen las reservas',
    ));
    Notification::assertNotSentTo($this->admin, SupportVisitStartedNotification::class);

    app()->forgetScopedInstances();
    session()->flush();
    $this->actingAs($owner->fresh())->get(route('subscription.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscription.supportVisits', 1)
            ->where('subscription.supportVisits.0.reason', 'No le aparecen las reservas')
            ->where('subscription.supportVisits.0.minutes', null));
});

it('lists only the salon own visits to its owner', function () {
    $other = app(CreateSalon::class)->handle(User::factory()->create(), 'Estudio Luz', BusinessType::BeautySalon, Clientele::Everyone);
    openVisit($this->admin, $other);
    $owner = SalonMember::query()->where('salon_id', $this->salon->id)->sole()->user;

    $this->actingAs($owner)->get(route('subscription.show'))
        ->assertInertia(fn (Assert $page) => $page->has('subscription.supportVisits', 0));
});

it('forgets visits after 24 months', function () {
    $old = openVisit($this->admin, $this->salon);
    $old->forceFill(['started_at' => now()->subMonths(SupportVisit::RETENTION_MONTHS)->subDay()])->save();
    $recent = openVisit($this->admin, $this->salon);

    $this->artisan('model:prune', ['--model' => [SupportVisit::class]])->assertSuccessful();

    expect(SupportVisit::query()->pluck('id')->all())->toBe([$recent->id]);
});
