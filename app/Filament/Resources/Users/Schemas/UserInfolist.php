<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\SalonMember;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nombre'),
                TextEntry::make('email')->label('Correo'),
                TextEntry::make('phone')->label('WhatsApp')->placeholder('-'),
                TextEntry::make('email_verified_at')
                    ->label('Correo verificado el')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin verificar'),
                IconEntry::make('two_factor_confirmed_at')
                    ->label('Verificación en dos pasos')
                    ->boolean()
                    ->state(fn ($record) => $record->two_factor_confirmed_at !== null),
                IconEntry::make('is_platform_admin')
                    ->label('Super admin')
                    ->boolean(),
                TextEntry::make('salons')
                    ->label('Salones')
                    ->state(fn ($record) => $record->memberships()
                        ->with(['salon' => fn ($query) => $query->withTrashed()])
                        ->get()
                        ->map(fn (SalonMember $member) => sprintf(
                            '%s (%s)%s',
                            $member->salon->name,
                            $member->role->label(),
                            $member->salon->trashed() ? ' · eliminado' : '',
                        ))
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('Ninguno'),
                TextEntry::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
