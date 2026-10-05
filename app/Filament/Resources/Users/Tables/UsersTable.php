<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
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
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->placeholder('-'),
                IconColumn::make('email_verified_at')
                    ->label('Verificado')
                    ->boolean()
                    ->state(fn ($record) => $record->email_verified_at !== null),
                IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->state(fn ($record) => $record->two_factor_confirmed_at !== null),
                IconColumn::make('is_platform_admin')
                    ->label('Super admin')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')
                    ->label('Correo verificado')
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
