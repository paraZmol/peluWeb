<?php

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Salon\CreateSalon;
use App\Data\Billing\NewSubscriptionPayment;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\User;
use App\Notifications\SubscriptionEndingNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Steps 7.2 and 7.3 from the salon's side: "Mi plan" for the owner, the
| notice everyone sees, and the reminders 7, 3 and 1 days before the end.
| Barbería Central signs up on 2026-10-07 in Lima: its trial covers up to
| 2026-11-06.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 10, 'billing.founder.ends_on' => null, 'billing.pay_to.yape' => '999888777', 'billing.pay_to.name' => 'Plataforma']);

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
    $this->salon->forceFill(['timezone' => 'America/Lima'])->save();
});

function payFor(Salon $salon, string $code = 'equipo'): void
{
    $plan = Plan::query()->where('code', $code)->firstOrFail();
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
        $salon->refresh(), $plan, 1, RecordSubscriptionPayment::expectedCents($salon, $plan, 1), PaymentMethod::Yape, User::factory()->create(),
    ));
}

it('shows the owner the trial, the plans at their founder price and how to pay', function () {
    $this->actingAs($this->owner)->get(route('subscription.show'))->assertInertia(fn (Assert $page) => $page
        ->component('owner/subscription')
        ->where('subscription.state', 'trial')
        ->where('subscription.coveredUntil', '2026-11-06')
        ->where('subscription.daysLeft', 30)
        ->where('subscription.graceEndsOn', '2026-11-13')
        ->where('subscription.plan.name', 'Equipo')
        ->where('subscription.withAgenda', 1)
        ->where('subscription.offersYear', false)
        ->where('subscription.payTo.yape', '999888777')
        ->has('subscription.plans', 2)
        ->where('subscription.plans.0.name', 'Solo')
        ->where('subscription.plans.0.monthlyCents', 2900)
        ->where('subscription.plans.0.normalCents', 3900)
        ->where('subscription.plans.1.isCurrent', true)
        ->where('subscription.payments', []));
});

it('offers the year once the salon paid, and lists only its own payments', function () {
    payFor($this->salon);
    $other = app(CreateSalon::class)->handle(User::factory()->create(), 'Estudio Luz', BusinessType::BeautySalon, Clientele::Everyone);
    payFor($other);

    $this->actingAs($this->owner)->get(route('subscription.show'))->assertInertia(fn (Assert $page) => $page
        ->where('subscription.state', 'active')
        ->where('subscription.offersYear', true)
        ->where('subscription.isFounder', true)
        ->has('subscription.payments', 1)
        ->where('subscription.payments.0.amountCents', 6900)
        ->where('subscription.payments.0.coversFrom', '2026-11-07'));
});

it('keeps "Mi plan" to owners', function () {
    $member = SalonMember::factory()->for($this->salon)->create();

    $this->actingAs($member->user)->get(route('subscription.show'))->assertForbidden();
});

it('tells everyone in the salon where it stands', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'America/Lima'));

    $this->actingAs($this->owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('currentSalon.subscription.state', 'read_only')
        ->where('currentSalon.subscription.daysLeft', -14));
});

it('reminds the owners 7, 3 and 1 days before the end, once each day', function () {
    Notification::fake();
    $command = fn () => $this->artisan('subscriptions:send-reminders')->assertSuccessful();

    $this->travelTo(CarbonImmutable::parse('2026-10-29 09:00', 'America/Lima'));
    $command();
    Notification::assertNothingSent();

    $this->travelTo(CarbonImmutable::parse('2026-10-30 09:00', 'America/Lima'));
    $command();
    $command();
    Notification::assertSentToTimes($this->owner, SubscriptionEndingNotification::class, 1);
    Notification::assertSentTo($this->owner, SubscriptionEndingNotification::class, fn ($notice) => $notice->daysLeft === 7
        && str_contains($notice->toMail($this->owner)->subject, 'La prueba gratis de Barbería Central termina en 7 días'));

    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00', 'America/Lima'));
    $command();
    Notification::assertSentTo($this->owner, SubscriptionEndingNotification::class, fn ($notice) => $notice->daysLeft === 1
        && str_contains($notice->toMail($this->owner)->subject, 'termina mañana'));
    Notification::assertSentToTimes($this->owner, SubscriptionEndingNotification::class, 2);
});

it('does not remind a suspended salon', function () {
    Notification::fake();
    $this->salon->forceFill(['status' => 'suspended'])->save();

    $this->travelTo(CarbonImmutable::parse('2026-10-30 09:00', 'America/Lima'));
    $this->artisan('subscriptions:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});
