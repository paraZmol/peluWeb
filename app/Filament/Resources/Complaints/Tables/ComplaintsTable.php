<?php

namespace App\Filament\Resources\Complaints\Tables;

use App\Enums\ComplaintKind;
use App\Models\Complaint;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ComplaintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('N.º')
                    ->state(fn (Complaint $record): string => $record->code())
                    ->searchable(['number']),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ComplaintKind $state): string => $state->label()),
                TextColumn::make('consumer_name')->label('Nombre')->searchable(),
                TextColumn::make('item_description')->label('Sobre')->limit(40),
                TextColumn::make('created_at')
                    ->label('Presentada')
                    ->dateTime('d/m/Y H:i')
                    ->timezone((string) config('legal.timezone'))
                    ->sortable(),
                TextColumn::make('due_on')->label('Vence')->date('d/m/Y')->sortable(),
                TextColumn::make('deadline')
                    ->label('Plazo')
                    ->badge()
                    ->state(fn (Complaint $record): string => self::deadlineLabel($record))
                    ->color(fn (Complaint $record): string => self::deadlineColor($record)),
            ])
            ->filters([
                TernaryFilter::make('responded_at')->label('Respondida')->nullable(),
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options(collect(ComplaintKind::cases())->mapWithKeys(fn (ComplaintKind $kind) => [$kind->value => $kind->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
            ]);
    }

    public static function deadlineLabel(Complaint $complaint): string
    {
        if ($complaint->isAnswered()) {
            return 'Respondida';
        }

        $left = $complaint->businessDaysLeft();

        return match (true) {
            $left < 0 => 'Vencida',
            $left === 0 => 'Vence hoy',
            $left === 1 => 'Falta 1 día hábil',
            default => "Faltan {$left} días hábiles",
        };
    }

    /**
     * Gray once answered; amber with 5 business days left, red with 2.
     */
    public static function deadlineColor(Complaint $complaint): string
    {
        if ($complaint->isAnswered()) {
            return 'gray';
        }

        $left = $complaint->businessDaysLeft();

        return match (true) {
            $left <= (int) config('legal.complaints.urgent_days') => 'danger',
            $left <= (int) config('legal.complaints.warning_days') => 'warning',
            default => 'success',
        };
    }
}
