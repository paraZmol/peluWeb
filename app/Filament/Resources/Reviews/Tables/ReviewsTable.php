<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Actions\Reviews\ModerateReview;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('reported_at', 'desc')
            ->columns([
                TextColumn::make('salon.name')->label('Salón')->searchable(),
                TextColumn::make('rating')->label('Estrellas'),
                TextColumn::make('comment')->label('Reseña')->wrap()->placeholder('(sin comentario)'),
                TextColumn::make('report_reason')->label('Motivo del reporte')->wrap(),
                TextColumn::make('reported_at')->label('Reportada')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Review $record): string => match (true) {
                        $record->report_resolved_at === null => 'Pendiente',
                        $record->hidden_at !== null => 'Oculta',
                        default => 'Se mantiene',
                    }),
            ])
            ->filters([
                TernaryFilter::make('report_resolved_at')->label('Resuelta')->nullable(),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Ocultar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('La reseña dejará de mostrarse en la página del salón y de contar en su promedio.')
                    ->visible(fn (Review $record): bool => $record->hidden_at === null)
                    ->action(fn (Review $record) => app(ModerateReview::class)->resolve($record, hide: true)),
                Action::make('keep')
                    ->label('Mantener')
                    ->requiresConfirmation()
                    ->modalDescription('El reporte se cierra y la reseña sigue visible.')
                    ->visible(fn (Review $record): bool => $record->report_resolved_at === null || $record->hidden_at !== null)
                    ->action(fn (Review $record) => app(ModerateReview::class)->resolve($record, hide: false)),
            ]);
    }
}
