<?php

namespace App\Filament\Widgets;

use App\Actions\Platform\PlatformMetrics;
use App\Enums\SubscriptionState;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The platform at a glance (PLAN 7.4): who pays, who is about to, and what
 * came in. Payments are the ones recorded by hand in each salon.
 */
class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return 'La plataforma hoy';
    }

    protected function getStats(): array
    {
        $metrics = app(PlatformMetrics::class)->handle(CarbonImmutable::now());
        $state = $metrics['byState'];

        return [
            Stat::make('Salones al día', (string) $state[SubscriptionState::Active->value])
                ->description($state[SubscriptionState::Trial->value].' en prueba')
                ->color('success'),
            Stat::make('Pago vencido', (string) $state[SubscriptionState::Grace->value])
                ->description($state[SubscriptionState::ReadOnly->value].' en solo lectura · '.$state[SubscriptionState::Suspended->value].' suspendidos')
                ->color('warning'),
            Stat::make('Cobrado este mes', 'S/ '.Money::toAmount($metrics['paidThisMonthCents']))
                ->description('Mes pasado: S/ '.Money::toAmount($metrics['paidLastMonthCents'])),
            Stat::make('Reservas este mes', (string) $metrics['bookingsThisMonth'])
                ->description('En todos los salones, sin canceladas ni vencidas'),
        ];
    }
}
