<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Salons\Pages\ViewSalon;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Step 7.2: the platform admin records payments, extends a trial and edits
| the plans from /plataforma.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 10, 'billing.founder.ends_on' => null]);

    $this->admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
    $this->salon->forceFill(['timezone' => 'America/Lima'])->save();

    $this->actingAs($this->admin);
    Filament::setCurrentPanel('plataforma');
});

it('records a payment with the amount proposed for the plan and the months', function () {
    $solo = Plan::query()->where('code', 'solo')->firstOrFail();

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->mountAction('recordPayment')
        ->assertSchemaStateSet(['amount' => '69.00'])
        ->setActionData(['plan_id' => $solo->id, 'months' => 12])
        ->assertSchemaStateSet(['amount' => '290.00'])
        ->setActionData(['method' => 'yape', 'note' => 'Yape del 7/10'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    $payment = SubscriptionPayment::withoutGlobalScopes()->sole();
    expect($payment->amount_cents)->toBe(29000)
        ->and($payment->months)->toBe(12)
        ->and($payment->recorded_by)->toBe($this->admin->id)
        ->and($payment->note)->toBe('Yape del 7/10')
        ->and($this->salon->refresh()->plan_id)->toBe($solo->id);
});

it('says why a smaller plan is refused and records nothing', function () {
    Staff::factory()->for($this->salon)->create();
    $solo = Plan::query()->where('code', 'solo')->firstOrFail();

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('recordPayment', ['plan_id' => $solo->id, 'months' => 1, 'amount' => '29.00', 'method' => 'yape'])
        ->assertNotified('El salón tiene 2 profesionales con agenda y el plan Solo incluye 1. Primero debe quitar la agenda a 1 persona.');

    expect(SubscriptionPayment::withoutGlobalScopes()->exists())->toBeFalse();
});

it('extends the trial once and then stops offering it', function () {
    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->callAction('extendTrial')
        ->assertNotified();

    expect($this->salon->refresh()->trial_extended_at)->not->toBeNull();

    Livewire::test(ViewSalon::class, ['record' => $this->salon->getRouteKey()])
        ->assertActionHidden('extendTrial');
});

it('edits a plan in soles and keeps it in céntimos', function () {
    $team = Plan::query()->where('code', 'equipo')->firstOrFail();

    Livewire::test(EditPlan::class, ['record' => $team->getRouteKey()])
        ->assertSchemaStateSet(['price' => '89.00', 'founder_price' => '69.00'])
        ->fillForm(['price' => '79.50', 'founder_price' => '', 'max_staff' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($team->refresh())
        ->price_cents->toBe(7950)
        ->founder_price_cents->toBeNull()
        ->max_staff->toBe(5)
        ->code->toBe('equipo');
});

it('keeps the plans to the platform admin', function () {
    $this->get('/plataforma/plans')->assertOk();

    $this->actingAs($this->owner)->get('/plataforma/plans')->assertForbidden();
});
