<?php

use App\Actions\Billing\ExtendTrial;
use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Salon\CreateSalon;
use App\Data\Billing\NewSubscriptionPayment;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\SalonStatus;
use App\Enums\SubscriptionState;
use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
| Step 7.2: payments recorded by hand by the platform admin. Barbería
| Central signs up on Wednesday 2026-10-07 in Lima, so its trial covers up
| to 2026-11-06.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 10, 'billing.founder.ends_on' => null]);

    $this->admin = User::factory()->create();
    $this->salon = newBillingSalon('Barbería Central');
});

function newBillingSalon(string $name): Salon
{
    $salon = app(CreateSalon::class)->handle(User::factory()->create(), $name, BusinessType::Barbershop, Clientele::Men);
    $salon->forceFill(['timezone' => 'America/Lima'])->save();

    return $salon->refresh();
}

function billingPlan(string $code): Plan
{
    return Plan::query()->where('code', $code)->firstOrFail();
}

function recordPayment(Salon $salon, string $plan = 'solo', int $months = 1, ?int $amountCents = null): SubscriptionPayment
{
    $plan = billingPlan($plan);

    return app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        salon: $salon->refresh(),
        plan: $plan,
        months: $months,
        amountCents: $amountCents ?? RecordSubscriptionPayment::expectedCents($salon->refresh(), $plan, $months),
        method: PaymentMethod::Yape,
        recordedBy: test()->admin,
    ));
}

it('covers the month that follows the trial, with the founder price for the first salons', function () {
    expect(RecordSubscriptionPayment::expectedCents($this->salon, billingPlan('solo'), 1))->toBe(2900);

    $payment = recordPayment($this->salon);
    $salon = $this->salon->refresh();

    expect($payment->covers_from->toDateString())->toBe('2026-11-07')
        ->and($payment->covers_until->toDateString())->toBe('2026-12-06')
        ->and($payment->amount_cents)->toBe(2900)
        ->and($payment->is_founder)->toBeTrue()
        ->and($payment->recorded_by)->toBe($this->admin->id)
        ->and($salon->plan->code)->toBe('solo')
        ->and($salon->paid_until->toDateString())->toBe('2026-12-06')
        ->and($salon->status)->toBe(SalonStatus::Active)
        ->and($salon->standing()->state)->toBe(SubscriptionState::Active);

    // The next one keeps the founder price and follows on.
    expect(RecordSubscriptionPayment::expectedCents($salon, billingPlan('solo'), 1))->toBe(2900);
    expect(recordPayment($salon)->covers_from->toDateString())->toBe('2026-12-07');
});

it('charges a year as ten months', function () {
    expect(RecordSubscriptionPayment::expectedCents($this->salon, billingPlan('equipo'), 12))->toBe(69000);

    $payment = recordPayment($this->salon, 'equipo', 12);

    expect($payment->covers_until->toDateString())->toBe('2027-11-06');
});

it('gives the founder price only while places are left', function () {
    config(['billing.founder.slots' => 1]);
    recordPayment($this->salon);
    $second = newBillingSalon('Estudio Luz');

    expect(RecordSubscriptionPayment::expectedCents($second, billingPlan('solo'), 1))->toBe(3900)
        ->and(recordPayment($second)->is_founder)->toBeFalse();
});

it('closes the founder offer after its last day', function () {
    config(['billing.founder.ends_on' => '2026-10-06']);

    expect(recordPayment($this->salon)->is_founder)->toBeFalse()
        ->and($this->salon->refresh()->founder_since)->toBeNull();
});

it('does not give away the days of grace, and starts today once read only', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'America/Lima'));
    expect($this->salon->refresh()->standing()->state)->toBe(SubscriptionState::Grace);
    expect(recordPayment($this->salon)->covers_from->toDateString())->toBe('2026-11-07');

    $late = newBillingSalon('Estudio Luz');
    $this->travelTo(CarbonImmutable::parse('2026-12-20 10:00', 'America/Lima'));
    expect($late->refresh()->standing()->state)->toBe(SubscriptionState::ReadOnly);
    expect(recordPayment($late)->covers_from->toDateString())->toBe('2026-12-20');
});

it('takes the founder price away from a salon that let it lapse to read only', function () {
    recordPayment($this->salon);
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 1]);

    $payment = recordPayment($this->salon);

    expect($payment->is_founder)->toBeFalse()
        ->and($payment->amount_cents)->toBe(3900)
        ->and($this->salon->refresh()->founder_since)->toBeNull();
});

it('refuses a smaller plan while more people have an agenda than it allows', function () {
    Staff::factory()->for($this->salon)->create();

    expect(fn () => recordPayment($this->salon, 'solo'))
        ->toThrow(BillingException::class, 'Primero debe quitar la agenda a 1 persona.');

    expect($this->salon->refresh()->paid_until)->toBeNull();
});

it('only takes a month or a year', function () {
    expect(fn () => recordPayment($this->salon, 'solo', 3, 1000))->toThrow(BillingException::class);
});

it('extends a trial once, from today if it already ended, and never after paying', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'America/Lima'));
    expect($this->salon->refresh()->standing()->state)->toBe(SubscriptionState::Grace);

    app(ExtendTrial::class)->handle($this->salon);
    $salon = $this->salon->refresh();

    expect($salon->trial_extended_at)->not->toBeNull()
        ->and($salon->standing()->state)->toBe(SubscriptionState::Trial)
        ->and($salon->standing()->coveredUntil->toDateString())->toBe('2026-11-25');

    expect(fn () => app(ExtendTrial::class)->handle($salon))->toThrow(BillingException::class, 'ya se prorrogó');

    $paying = newBillingSalon('Estudio Luz');
    recordPayment($paying);
    expect(fn () => app(ExtendTrial::class)->handle($paying))->toThrow(BillingException::class, 'ya pagó');
});
