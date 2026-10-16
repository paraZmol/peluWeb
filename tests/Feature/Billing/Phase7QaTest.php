<?php

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Platform\SupportVisits;
use App\Actions\Salon\CreateSalon;
use App\Data\Billing\NewSubscriptionPayment;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\SalonRole;
use App\Exceptions\BillingException;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Salons\Pages\ViewSalon;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\SubscriptionPayment;
use App\Models\SupportVisit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/*
| QA of phase 7 (7.1 to 7.4): the rules per role, with the attacks the
| step tests do not make. Signed up on 2026-10-07 (Lima): the trial covers
| up to 2026-11-06, grace up to 2026-11-13, read only from 2026-11-14.
*/

const PAUSED_MESSAGE = 'Tu salón está en pausa. Renueva tu plan para seguir haciendo cambios.';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 5, 'billing.founder.ends_on' => null]);

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
    $this->salon->forceFill(['timezone' => 'America/Lima'])->save();

    $this->staffUser = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($this->staffUser)->create(['role' => SalonRole::Staff]);

    $this->otherOwner = User::factory()->create();
    $this->other = app(CreateSalon::class)->handle($this->otherOwner, 'Estudio Luz', BusinessType::BeautySalon, Clientele::Everyone);
    $this->other->forceFill(['timezone' => 'America/Lima'])->save();
});

function p7TravelTo(string $date): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} 10:00", 'America/Lima'));
}

/**
 * Every route behind the `salon` middleware that changes data.
 *
 * @return list<array{string, string}>
 */
function p7WriteRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('salon', $route->gatherMiddleware(), true))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn ($method) => in_array($method, ['GET', 'HEAD', 'OPTIONS'], true))
            ->map(fn ($method) => [$method, $route->getName()]))
        ->values()
        ->all();
}

function p7RouteParams(string $name): array
{
    return collect(Route::getRoutes()->getByName($name)->parameterNames())->mapWithKeys(fn ($p) => [$p => 999999])->all();
}

// ---------------------------------------------------------------- 7.3 read only

it('knows the exact day grace ends and read only begins', function (string $date, bool $writable, string $state) {
    p7TravelTo($date);

    expect($this->salon->refresh()->isWritable())->toBe($writable)
        ->and($this->salon->standing()->state->value)->toBe($state);
})->with([
    'last day of the trial' => ['2026-11-06', true, 'trial'],
    'first day of grace' => ['2026-11-07', true, 'grace'],
    'last day of grace' => ['2026-11-13', true, 'grace'],
    'first day read only' => ['2026-11-14', false, 'read_only'],
]);

it('refuses every write route of the salon, for the owner and for the team, once read only', function () {
    p7TravelTo('2026-11-20');
    $checked = 0;

    foreach ([$this->owner, $this->staffUser] as $who) {
        foreach (p7WriteRoutes() as [$method, $name]) {
            $response = $this->actingAs($who)->call($method, route($name, p7RouteParams($name)));
            // A made-up id may be a 404 before the middleware; anything else must be the pause.
            if ($response->status() === 404) {
                continue;
            }
            $response->assertRedirect()->assertInertiaFlash('toast.message', PAUSED_MESSAGE);
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(20);
});

it('lets everything be seen and the cash CSV exported when read only, and changes nothing', function () {
    p7TravelTo('2026-11-20');

    foreach (['staff.index', 'agenda.index', 'services.index', 'cash.index', 'reports.index', 'expenses.index', 'clients.index', 'team.index', 'subscription.show', 'salon.edit', 'commissions.index'] as $name) {
        $this->actingAs($this->owner)->get(route($name))->assertOk();
    }

    $this->actingAs($this->owner)->get(route('reports.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($this->owner)->post(route('services.store'), ['name' => 'Corte ñandú', 'price' => '25'])
        ->assertInertiaFlash('toast.message', PAUSED_MESSAGE);
    expect(Service::withoutGlobalScopes()->where('salon_id', $this->salon->id)->where('name', 'Corte ñandú')->exists())->toBeFalse();
});

it('shows the public page of a read only salon without a way to book, and refuses the booking', function () {
    p7TravelTo('2026-11-20');
    $beto = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create();
    $cut = Service::factory()->for($this->salon)->create(['active_minutes' => 30]);
    $beto->services()->attach($cut->id);

    $this->get(route('salons.show', $this->salon))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('salon.bookingUrl', null)->where('salon.name', 'Barbería Central'));

    $client = User::factory()->create();
    $before = DB::table('bookings')->count();
    $this->actingAs($client)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $cut->id]],
        'staff' => $beto->id,
        'start' => CarbonImmutable::parse('2026-11-21 10:00', 'America/Lima')->getTimestamp(),
    ]);
    expect(DB::table('bookings')->count())->toBe($before);

    // Waiting list is also closed.
    $this->actingAs($client)->post(route('salons.waitlist.store', $this->salon), ['services' => [['id' => $cut->id]], 'staff' => $beto->id, 'date' => '2026-11-21'])->assertNotFound();
});

it('reactivates at once when the platform records a payment, and the other salon never noticed', function () {
    p7TravelTo('2026-11-20');
    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'X'])->assertInertiaFlash('toast.message', PAUSED_MESSAGE);
    // The other salon (own trial through 2026-11-06 too) is also read only: give it a payment.
    $plan = Plan::query()->where('code', 'equipo')->firstOrFail();
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->other->refresh(), $plan, 1, 6900, PaymentMethod::Yape, User::factory()->create(),
    ));

    expect($this->other->refresh()->isWritable())->toBeTrue()
        ->and($this->salon->refresh()->isWritable())->toBeFalse();

    $this->actingAs($this->otherOwner)->post(route('staff.store'), ['name' => 'Ana'])->assertSessionHasNoErrors();
    expect(Staff::withoutGlobalScopes()->where('name', 'Ana')->exists())->toBeTrue();

    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->salon->refresh(), $plan, 1, 6900, PaymentMethod::Yape, User::factory()->create(),
    ));
    $this->actingAs($this->owner->fresh())->post(route('staff.store'), ['name' => 'Beto'])->assertSessionHasNoErrors();
    expect(Staff::withoutGlobalScopes()->where('name', 'Beto')->exists())->toBeTrue();
});

it('treats a suspended salon as read only even with the trial running', function () {
    $this->salon->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'X'])
        ->assertInertiaFlash('toast.message', PAUSED_MESSAGE);
    $this->actingAs($this->owner)->get(route('staff.index'))->assertOk();
});

it('lets the owner of a read only salon cancel a booking already made (PLAN 7.3 says they are cancelled)', function () {
    $beto = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create();
    $cut = Service::factory()->for($this->salon)->create(['active_minutes' => 30]);
    $beto->services()->attach($cut->id);
    $client = User::factory()->create();
    $this->salon->forceFill(['min_lead_minutes' => 0, 'deposit_percent' => 0])->save();
    $this->actingAs($client)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $cut->id]], 'staff' => $beto->id,
        'start' => CarbonImmutable::parse('2026-11-25 10:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();
    $booking = DB::table('bookings')->first();

    p7TravelTo('2026-11-20');

    // The client can cancel their own.
    $this->actingAs($client)->post(route('bookings.cancel', $booking->code))->assertSessionHasNoErrors();
    expect(DB::table('bookings')->where('id', $booking->id)->value('status'))->toBe('cancelled');
});

it('lets the owner cancel from the agenda while read only', function () {
    $beto = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create();
    $cut = Service::factory()->for($this->salon)->create(['active_minutes' => 30]);
    $beto->services()->attach($cut->id);
    $this->salon->forceFill(['min_lead_minutes' => 0, 'deposit_percent' => 0])->save();
    $this->actingAs(User::factory()->create())->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $cut->id]], 'staff' => $beto->id,
        'start' => CarbonImmutable::parse('2026-11-25 10:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();
    $booking = Booking::withoutGlobalScopes()->firstOrFail();

    p7TravelTo('2026-11-20');
    $this->actingAs($this->owner)->post(route('agenda.bookings.cancel', $booking))->assertSessionHasNoErrors();

    expect($booking->refresh()->status->value)->toBe('cancelled');
});

it('lets a read only salon confirm a deposit the client already paid', function () {
    $beto = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create();
    $cut = Service::factory()->for($this->salon)->create(['active_minutes' => 30, 'price_cents' => 5000]);
    $beto->services()->attach($cut->id);
    $this->salon->forceFill(['min_lead_minutes' => 0, 'deposit_percent' => 20])->save();
    // Booked on the last day of grace, just before midnight.
    $this->travelTo(CarbonImmutable::parse('2026-11-13 23:50', 'America/Lima'));
    $this->actingAs(User::factory()->create())->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $cut->id]], 'staff' => $beto->id,
        'start' => CarbonImmutable::parse('2026-11-25 10:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();
    $booking = Booking::withoutGlobalScopes()->firstOrFail();
    expect($booking->status->value)->toBe('pending');

    // Read only from the 14th; the client paid by Yape a few minutes later.
    $this->travelTo(CarbonImmutable::parse('2026-11-14 00:05', 'America/Lima'));
    expect($this->salon->refresh()->isWritable())->toBeFalse();
    $this->actingAs($this->owner)->post(route('agenda.bookings.confirm', $booking))->assertSessionHasNoErrors();

    expect($booking->refresh()->status->value)->toBe('confirmed');
});

// ---------------------------------------------------------------- notice per role

it('shares the standing with every member and gives nothing to a visitor or a client', function () {
    p7TravelTo('2026-11-20');

    foreach ([$this->owner, $this->staffUser] as $who) {
        $this->actingAs($who)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('currentSalon.subscription.state', 'read_only'));
    }

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('currentSalon', null));

    auth()->logout();
    $this->get(route('home'))->assertOk();
});

// ---------------------------------------------------------------- Mi plan per role

it('refuses Mi plan to the team, clients and visitors, and an owner never sees another salon\'s payments', function () {
    $plan = Plan::query()->where('code', 'equipo')->firstOrFail();
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->other->refresh(), $plan, 1, 6900, PaymentMethod::Yape, User::factory()->create(), 'secreto de Luz',
    ));

    $this->actingAs($this->staffUser)->get(route('subscription.show'))->assertForbidden();
    app()->forgetScopedInstances();
    $this->actingAs(User::factory()->create())->get(route('subscription.show'))->assertRedirect(route('salons.create'));

    $this->actingAs($this->owner)->get(route('subscription.show'))
        ->assertInertia(fn (Assert $page) => $page->where('subscription.payments', [])->where('subscription.offersYear', false));

    expect($this->actingAs($this->owner)->get(route('subscription.show'))->getContent())->not->toContain('secreto de Luz');

    auth()->logout();
    $this->get(route('subscription.show'))->assertRedirect();
});

it('keeps the founder price shown to a salon that has already paid at the founder price', function () {
    $plan = Plan::query()->where('code', 'solo')->firstOrFail();
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->salon->refresh(), $plan->fresh(), 1, 2900, PaymentMethod::Yape, User::factory()->create(),
    ));
    // The offer is now closed to newcomers.
    config(['billing.founder.slots' => 1]);

    $this->actingAs($this->owner)->get(route('subscription.show'))->assertInertia(fn (Assert $page) => $page
        ->where('subscription.isFounder', true)
        ->where('subscription.plans.0.monthlyCents', 2900)
        ->where('subscription.plans.1.monthlyCents', 6900)
        ->where('subscription.plans.1.yearCents', 69000));
});

// ---------------------------------------------------------------- 7.1 limit

it('refuses the 7th professional with agenda in Equipo with a clear message, via the real route', function () {
    foreach (range(2, 6) as $n) {
        $this->actingAs($this->owner)->post(route('staff.store'), ['name' => "Pro {$n}"])->assertSessionHasNoErrors();
    }
    expect(Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->where('is_active', true)->count())->toBe(6);

    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Pro 7'])
        ->assertInertiaFlash('toast.message', 'Tu plan Equipo incluye 6 profesionales con agenda. Quita la agenda a alguien o cambia de plan para sumar a otra persona.');
    expect(Staff::withoutGlobalScopes()->where('name', 'Pro 7')->exists())->toBeFalse();
});

it('does not let a staff member or a client change the agenda of anyone, nor reach another salon\'s professionals', function () {
    $own = Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->firstOrFail();
    $theirs = Staff::withoutGlobalScopes()->where('salon_id', $this->other->id)->firstOrFail();

    $this->actingAs($this->staffUser)->put(route('staff.update', $own), ['name' => 'Hack', 'is_active' => true])->assertForbidden();
    // The owner of Central attacks the id of Luz.
    $this->actingAs($this->owner)->put(route('staff.update', $theirs), ['name' => 'Hack', 'is_active' => false])->assertNotFound();
    expect($theirs->refresh()->is_active)->toBeTrue();
});

it('does not get round the limit by editing an inactive professional of a full plan with is_active as text', function (mixed $value) {
    $solo = Plan::query()->where('code', 'solo')->firstOrFail();
    $this->salon->plan()->associate($solo)->save();
    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Recepción', 'is_active' => false])->assertSessionHasNoErrors();
    $paused = Staff::withoutGlobalScopes()->where('name', 'Recepción')->firstOrFail();

    $this->actingAs($this->owner)->put(route('staff.update', $paused), ['name' => 'Recepción', 'is_active' => $value]);

    expect($paused->refresh()->is_active)->toBeFalse();
})->with(['true string' => ['true'], 'one' => [1], 'on' => ['on'], 'string 1' => ['1']]);

it('does not let the downgrade be forced by paying a smaller plan while over the limit, and keeps the salon intact', function () {
    Staff::factory()->for($this->salon)->create();
    $solo = Plan::query()->where('code', 'solo')->firstOrFail();

    expect(fn () => app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->salon->refresh(), $solo, 1, 2900, PaymentMethod::Yape, User::factory()->create(),
    )))->toThrow(BillingException::class);

    expect($this->salon->refresh()->plan->code)->toBe('equipo')
        ->and($this->salon->paid_until)->toBeNull()
        ->and(SubscriptionPayment::withoutGlobalScopes()->exists())->toBeFalse();
});

it('does not count professionals of other salons or removed ones toward the limit', function () {
    $solo = Plan::query()->where('code', 'solo')->firstOrFail();
    $this->salon->plan()->associate($solo)->save();
    $own = Staff::withoutGlobalScopes()->where('salon_id', $this->salon->id)->sole();

    // Removing the owner's profile frees the only place (nothing is booked).
    $this->actingAs($this->owner)->delete(route('staff.destroy', $own))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Nuevo'])->assertSessionHasNoErrors();

    expect(Staff::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->where('is_active', true)->count())->toBe(1);
});

// ---------------------------------------------------------------- landing

it('hides the founder line with 0 slots, negative slots, or a last day already gone', function () {
    p7TravelTo('2026-10-15');

    config(['billing.founder.slots' => 0]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.founder', null)->has('pricing.plans', 2));

    config(['billing.founder.slots' => -3]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.founder', null));

    config(['billing.founder.slots' => 5]);
    config(['billing.founder.ends_on' => '2026-10-14']);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.founder', null));
});

it('survives a badly written founder date in the environment', function () {
    config(['billing.founder.ends_on' => '31/12/2026']);

    $this->get(route('home'))->assertOk();
});

it('does not publish a plan the platform marked as not public', function () {
    Plan::query()->where('code', 'solo')->update(['is_public' => false]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('pricing.plans', 1)->where('pricing.plans.0.code', 'equipo'));
});

it('does not leak the number of founders beyond the places left', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('pricing.founder', fn ($founder) => array_keys($founder->toArray()) === ['slotsLeft', 'endsOn']));
});

// ---------------------------------------------------------------- platform panel

it('keeps the billing panel to the platform admin, and the actions off other roles', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($this->owner)->get('/plataforma')->assertForbidden();
    $this->actingAs($this->owner)->get('/plataforma/salons/'.$this->salon->getRouteKey())->assertForbidden();
    $this->actingAs($this->staffUser)->get('/plataforma/plans')->assertForbidden();
    auth()->logout();
    $this->get('/plataforma/plans')->assertRedirect();
    // An admin without two-factor is held at the door (PLAN 1.x).
    $this->actingAs(User::factory()->platformAdmin()->create())->get('/plataforma/plans')->assertRedirect();
    $this->actingAs($admin)->get('/plataforma/plans')->assertOk();
});

it('refuses a zero or negative or badly formatted amount when recording a payment', function (string $amount) {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');
    $team = Plan::query()->where('code', 'equipo')->firstOrFail();

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('recordPayment', ['plan_id' => $team->id, 'months' => 1, 'amount' => $amount, 'method' => 'yape']);

    expect(SubscriptionPayment::withoutGlobalScopes()->exists())->toBeFalse();
})->with(['zero' => ['0'], 'zero cents' => ['0.00'], 'negative' => ['-5'], 'comma' => ['29,50'], 'letters' => ['abc'], 'huge' => ['9999999']]);

it('refuses a month count other than 1 or 12 and an unknown method', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');
    $team = Plan::query()->where('code', 'equipo')->firstOrFail();

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('recordPayment', ['plan_id' => $team->id, 'months' => 6, 'amount' => '69.00', 'method' => 'yape'])
        ->assertHasActionErrors();
    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('recordPayment', ['plan_id' => $team->id, 'months' => 1, 'amount' => '69.00', 'method' => 'bitcoin'])
        ->assertHasActionErrors();

    expect(SubscriptionPayment::withoutGlobalScopes()->exists())->toBeFalse();
});

it('does not extend a trial twice, nor after paying, and counts from today once ended', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');
    p7TravelTo('2026-11-20');

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])->callAction('extendTrial');
    $salon = $this->salon->refresh();
    expect($salon->trial_extended_at)->not->toBeNull()
        ->and($salon->standing()->state->value)->toBe('trial')
        ->and($salon->standing()->coveredUntil->toDateString())->toBe('2026-12-05');

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])->assertActionHidden('extendTrial');
    expect($salon->refresh()->trial_ends_at->toDateString())->toBe($salon->trial_ends_at->toDateString());
});

it('does not let a crafted plan edit hurt a salon already on it', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');
    $team = Plan::query()->where('code', 'equipo')->firstOrFail();

    foreach ([['price' => '-1'], ['price' => 'abc'], ['max_staff' => 0], ['max_staff' => 99999]] as $bad) {
        Livewire::test(EditPlan::class, ['record' => $team->getRouteKey()])
            ->fillForm($bad)
            ->call('save');
        expect($team->refresh()->price_cents)->toBe(8900)->and($team->max_staff)->toBe(6);
    }
});

// ---------------------------------------------------------------- support mode

function p7StartSupport(User $admin, Salon $salon, string $reason = 'Revisar'): SupportVisit
{
    $visit = new SupportVisit;
    $visit->admin_id = $admin->id;
    $visit->salon_id = $salon->id;
    $visit->reason = $reason;
    $visit->started_at = now();
    $visit->save();

    return $visit;
}

it('refuses every write route of the salon in support mode, even when the salon is paid and writable', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $visit = p7StartSupport($admin, $this->salon);
    $checked = 0;

    foreach (p7WriteRoutes() as [$method, $name]) {
        $response = $this->actingAs($admin)->withSession([SupportVisits::SESSION_KEY => $visit->id])
            ->call($method, route($name, p7RouteParams($name)));
        if ($response->status() === 404) {
            continue;
        }
        $response->assertRedirect()->assertInertiaFlash('toast.message', 'Estás en modo soporte: puedes ver todo, pero no hacer cambios.');
        $checked++;
    }

    expect($checked)->toBeGreaterThan(20);
});

it('lets the admin in support see Mi plan, but not download the cash CSV nor read the other salon', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $visit = p7StartSupport($admin, $this->salon);
    $session = [SupportVisits::SESSION_KEY => $visit->id];

    $this->actingAs($admin)->withSession($session)->get(route('subscription.show'))->assertOk();
    $this->actingAs($admin)->withSession($session)->get(route('reports.export'))->assertForbidden();

    $theirs = Staff::withoutGlobalScopes()->where('salon_id', $this->other->id)->firstOrFail();
    $this->actingAs($admin)->withSession($session)->get(route('staff.schedule.show', $theirs))->assertNotFound();
});

it('does not leave support open when the salon is deleted, nor accept a visit for a stranger\'s id in the session', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $visit = p7StartSupport($admin, $this->salon);
    $this->salon->delete();

    $this->actingAs($admin)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->get(route('staff.index'))->assertRedirect(route('salons.create'));
    expect($visit->refresh()->ended_at)->not->toBeNull();

    // The id of a visit that never existed.
    app()->forgetScopedInstances();
    $this->actingAs($admin->fresh())->withSession([SupportVisits::SESSION_KEY => 987654])
        ->get(route('staff.index'))->assertRedirect(route('salons.create'));
});

it('requires a reason to start a support visit and trims it to the limit', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('supportVisit', ['reason' => ''])->assertHasActionErrors(['reason' => 'required']);
    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('supportVisit', ['reason' => str_repeat('ñ', SupportVisit::REASON_MAX_LENGTH + 1)])->assertHasActionErrors();
    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('supportVisit', ['reason' => '   '])->assertHasActionErrors();

    expect(SupportVisit::query()->count())->toBe(0);

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('supportVisit', ['reason' => '¿Por qué no salen mis citas? 💈 ñandú'])->assertHasNoActionErrors();
    expect(SupportVisit::query()->sole()->reason)->toBe('¿Por qué no salen mis citas? 💈 ñandú');
});

it('ends the earlier visit when a new one starts, so only one is ever open', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $first = p7StartSupport($admin, $this->salon);
    $session = app('session.store');
    $session->put(SupportVisits::SESSION_KEY, $first->id);

    $second = app(SupportVisits::class)->start($admin, $this->other, 'Otro', '127.0.0.1', $session);

    expect($first->refresh()->ended_at)->not->toBeNull()
        ->and($second->refresh()->ended_at)->toBeNull();
});

it('does not give a salon owner a way into support and shows nothing about it to them', function () {
    $visit = p7StartSupport(User::factory()->platformAdmin()->withTwoFactor()->create(), $this->salon);

    // Owner forging the session key with the admin's visit id.
    $this->actingAs($this->owner)->withSession([SupportVisits::SESSION_KEY => $visit->id])
        ->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('support', null)->where('currentSalon.id', $this->salon->id));
    // And the owner can still write: the visit did not freeze them.
    $this->actingAs($this->owner)->post(route('staff.store'), ['name' => 'Libre'])->assertSessionHasNoErrors();
});

it('still offers the founder price on the very last day of the offer, on the landing and when recording a payment', function () {
    p7TravelTo('2026-10-15');
    config(['billing.founder.slots' => 5, 'billing.founder.ends_on' => '2026-10-15']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricing.founder.endsOn', '2026-10-15'));

    $plan = Plan::query()->where('code', 'solo')->firstOrFail();
    expect(RecordSubscriptionPayment::expectedCents($this->salon->refresh(), $plan, 1))->toBe(2900);
});

it('applies a smaller plan the day it is recorded, since the admin records it when its period starts (PLAN 7.1)', function () {
    $team = Plan::query()->where('code', 'equipo')->firstOrFail();
    $solo = Plan::query()->where('code', 'solo')->firstOrFail();
    // Only the owner has an agenda, so the downgrade is accepted.
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->salon->refresh(), $team, 1, 6900, PaymentMethod::Yape, User::factory()->create(),
    ));
    // Paid Equipo up to 2026-12-06; the next month, in advance, is Solo.
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $this->salon->refresh(), $solo, 1, 2900, PaymentMethod::Yape, User::factory()->create(),
    ));

    // PLAN 7.1: the admin records it when its period starts; recorded early, it applies at once.
    expect($this->salon->refresh()->plan->code)->toBe('solo');
});

it('keeps the founder offer closed to a second salon taking the last place at the same time', function () {
    config(['billing.founder.slots' => 1]);
    $plan = Plan::query()->where('code', 'solo')->firstOrFail();
    foreach ([$this->salon, $this->other] as $salon) {
        app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
            $salon->refresh(), $plan, 1, 2900, PaymentMethod::Yape, User::factory()->create(),
        ));
    }

    expect(Salon::query()->whereNotNull('founder_since')->count())->toBe(1)
        ->and(SubscriptionPayment::withoutGlobalScopes()->where('is_founder', true)->count())->toBe(1);
});
