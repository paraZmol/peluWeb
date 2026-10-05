<?php

namespace App\Filament\Resources\Salons\Schemas;

use App\Enums\SalonStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalonForm
{
    /**
     * Only the fields listed in EditSalon::EDITABLE are saved.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120),
                TextInput::make('phone')
                    ->label('WhatsApp')
                    ->tel()
                    ->regex('/^\+?[0-9]{7,15}$/'),
                Select::make('status')
                    ->label('Estado')
                    ->options(collect(SalonStatus::cases())
                        ->mapWithKeys(fn (SalonStatus $status) => [$status->value => $status->label()]))
                    ->required(),
                DateTimePicker::make('trial_ends_at')
                    ->label('Fin de la prueba gratis'),
            ]);
    }
}
