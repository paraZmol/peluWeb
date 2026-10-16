<?php

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Actions\Platform\PlatformMetrics;
use App\Actions\Salon\CreateSalon;
use App\Data\Billing\NewSubscriptionPayment;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\SalonStatus;
use App\Filament\Widgets\EndingSoonWidget;
use App\Filament\Widgets\PlatformStatsWidget;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Step 7.4: the platform admin's numbers. Salons sign up on 2026-10-07
| in Lima unless a test moves the clock.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    config(['billing.founder.slots' => 0]);
});

function metricsSalon(string $name): Salon
{
    $salon = app(CreateSalon::class)->handle(User::factory()->create(), $name, BusinessType::Barbershop, Clientele::Men);
    $salon->forceFill(['timezone' => 'America/Lima'])->save();

    return $salon->refresh();
}

it('counts salons by where they stand and what was paid by month', function () {
    $paying = metricsSalon('Al día');
    $plan = Plan::query()->where('code', 'solo')->firstOrFail();
    app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment($paying, $plan, 1, 3900, PaymentMethod::Yape, User::factory()->create()));

    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'America/Lima'));
    $graced = metricsSalon('En gracia');
    $graced->forceFill(['trial_ends_at' => now()->subDays(3)])->save();
    metricsSalon('En prueba');
    metricsSalon('Suspendido')->forceFill(['status' => SalonStatus::Suspended])->save();

    $metrics = app(PlatformMetrics::class)->handle(CarbonImmutable::now());

    expect($metrics['byState'])->toMatchArray(['active' => 1, 'trial' => 1, 'grace' => 1, 'read_only' => 0, 'suspended' => 1])
        ->and($metrics['paidThisMonthCents'])->toBe(0)
        ->and($metrics['paidLastMonthCents'])->toBe(3900);
});

it('lists who ends in the next seven days for the platform admin', function () {
    $soon = metricsSalon('Vence pronto');
    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00', 'America/Lima'));
    metricsSalon('Recién llegado');

    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(EndingSoonWidget::class)
        ->assertCanSeeTableRecords([$soon])
        ->assertCountTableRecords(1);

    $this->get('/plataforma')->assertOk();
    Livewire::test(PlatformStatsWidget::class)->assertSee(['La plataforma hoy', 'Salones al día', 'Cobrado este mes']);
});
