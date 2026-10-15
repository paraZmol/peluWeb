<?php

namespace App\Filament\Resources\Salons\Schemas;

use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonStatus;
use App\Filament\Resources\Salons\Tables\SalonsTable;
use App\Models\Salon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SalonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nombre'),
                TextEntry::make('slug')->label('Enlace'),
                TextEntry::make('business_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (BusinessType $state) => $state->label()),
                TextEntry::make('clientele')
                    ->label('Público')
                    ->badge()
                    ->formatStateUsing(fn (Clientele $state) => $state->label()),
                TextEntry::make('phone')->label('WhatsApp')->placeholder('-'),
                TextEntry::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (SalonStatus $state) => $state->label()),
                TextEntry::make('trial_ends_at')
                    ->label('Fin de la prueba')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('plan.name')->label('Plan')->placeholder('-'),
                TextEntry::make('subscription')
                    ->label('Suscripción')
                    ->badge()
                    ->state(fn (Salon $record): string => $record->standing()->state->label())
                    ->color(fn (Salon $record): string => SalonsTable::standingColor($record->standing()->state)),
                TextEntry::make('covered_until')
                    ->label('Cubierto hasta')
                    ->state(fn (Salon $record): ?string => $record->standing()->coveredUntil?->format('d/m/Y'))
                    ->placeholder('-'),
                TextEntry::make('founder_since')
                    ->label('Precio fundador desde')
                    ->date('d/m/Y')
                    ->placeholder('No'),
                TextEntry::make('trial_extended_at')
                    ->label('Prueba prorrogada')
                    ->date('d/m/Y')
                    ->placeholder('No'),
                TextEntry::make('with_agenda')
                    ->label('Profesionales con agenda')
                    ->state(fn (Salon $record): string => $record->staff()->where('is_active', true)->count().' de '.$record->staffLimit()),
                TextEntry::make('members_count')
                    ->label('Personas en el equipo')
                    ->state(fn ($record) => $record->members()->count()),
                TextEntry::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn ($record) => $record->trashed()),
            ]);
    }
}
