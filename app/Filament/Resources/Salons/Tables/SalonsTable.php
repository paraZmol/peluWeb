<?php

namespace App\Filament\Resources\Salons\Tables;

use App\Enums\BusinessType;
use App\Enums\SalonStatus;
use App\Enums\SubscriptionState;
use App\Models\Salon;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalonsTable
{
    public static function standingColor(SubscriptionState $state): string
    {
        return match ($state) {
            SubscriptionState::Active => 'success',
            SubscriptionState::Trial => 'info',
            SubscriptionState::Grace => 'warning',
            SubscriptionState::ReadOnly, SubscriptionState::Suspended => 'danger',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('plan'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (BusinessType $state) => $state->label()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (SalonStatus $state) => match ($state) {
                        SalonStatus::Active => 'success',
                        SalonStatus::Trial => 'info',
                        SalonStatus::PastDue => 'warning',
                        SalonStatus::Suspended => 'danger',
                    })
                    ->formatStateUsing(fn (SalonStatus $state) => $state->label()),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('subscription')
                    ->label('Suscripción')
                    ->badge()
                    ->state(fn (Salon $record): string => $record->standing()->state->label())
                    ->color(fn (Salon $record): string => self::standingColor($record->standing()->state)),
                TextColumn::make('covered_until')
                    ->label('Cubierto hasta')
                    ->state(fn (Salon $record): ?string => $record->standing()->coveredUntil?->format('d/m/Y'))
                    ->placeholder('-'),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('plan')->label('Plan')->relationship('plan', 'name'),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(SalonStatus::cases())
                        ->mapWithKeys(fn (SalonStatus $status) => [$status->value => $status->label()])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
