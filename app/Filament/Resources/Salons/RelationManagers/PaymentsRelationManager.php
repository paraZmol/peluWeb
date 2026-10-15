<?php

namespace App\Filament\Resources\Salons\RelationManagers;

use App\Enums\PaymentMethod;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The salon's subscription payments, newest first. Read only: a payment
 * recorded by mistake is corrected with a note on the next one, never
 * edited, so the history stays what was received.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionPayments';

    protected static ?string $title = 'Pagos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('covers_from', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Registrado')->date('d/m/Y'),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('months')->label('Meses'),
                TextColumn::make('amount_cents')
                    ->label('Monto')
                    ->formatStateUsing(fn (int $state): string => 'S/ '.Money::toAmount($state)),
                TextColumn::make('method')
                    ->label('Cómo pagó')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('covers_from')->label('Desde')->date('d/m/Y'),
                TextColumn::make('covers_until')->label('Hasta')->date('d/m/Y'),
                IconColumn::make('is_founder')->label('Fundador')->boolean(),
                TextColumn::make('recorder.name')->label('Registró')->placeholder('-'),
                TextColumn::make('note')->label('Nota')->placeholder('-')->wrap(),
            ]);
    }
}
