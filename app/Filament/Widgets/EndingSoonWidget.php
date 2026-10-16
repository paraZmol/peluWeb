<?php

namespace App\Filament\Widgets;

use App\Actions\Platform\PlatformMetrics;
use App\Filament\Resources\Salons\SalonResource;
use App\Filament\Resources\Salons\Tables\SalonsTable;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Who to call this week: trials and paid times ending in the next days.
 */
class EndingSoonWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Vencen en los próximos '.PlatformMetrics::ENDING_SOON_DAYS.' días')
            ->query(fn () => PlatformMetrics::endingSoon(CarbonImmutable::now())->with('plan'))
            ->columns([
                TextColumn::make('name')->label('Salón'),
                TextColumn::make('plan.name')->label('Plan')->placeholder('-'),
                TextColumn::make('subscription')
                    ->label('Suscripción')
                    ->badge()
                    ->state(fn (Salon $record): string => $record->standing()->state->label())
                    ->color(fn (Salon $record): string => SalonsTable::standingColor($record->standing()->state)),
                TextColumn::make('covered_until')
                    ->label('Cubierto hasta')
                    ->state(fn (Salon $record): ?string => $record->standing()->coveredUntil?->format('d/m/Y')),
                TextColumn::make('phone')->label('WhatsApp')->placeholder('-'),
            ])
            ->recordUrl(fn (Salon $record): string => SalonResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nadie vence esta semana');
    }
}
