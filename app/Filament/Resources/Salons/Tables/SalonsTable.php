<?php

namespace App\Filament\Resources\Salons\Tables;

use App\Enums\BusinessType;
use App\Enums\SalonStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SalonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                TextColumn::make('trial_ends_at')
                    ->label('Fin de la prueba')
                    ->date('d/m/Y')
                    ->sortable(),
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
