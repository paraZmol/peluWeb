<?php

namespace App\Filament\Resources\Salons\Actions;

use App\Actions\Platform\SupportVisits;
use App\Models\Salon;
use App\Models\SupportVisit;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Livewire\Component;

/**
 * "Entrar como soporte": opens the salon's screens as its owner sees them,
 * read only, and records who, why and when (PLAN 7.4).
 */
class SupportVisitAction
{
    public static function make(): Action
    {
        return Action::make('supportVisit')
            ->label('Entrar como soporte')
            ->icon('heroicon-o-lifebuoy')
            ->color('gray')
            ->modalHeading('Entrar al salón como soporte')
            ->modalDescription('Verás sus pantallas como las ve quien es titular, sin poder cambiar nada. Queda registrado quién entró, por qué y cuándo. La visita se cierra sola a las '.intdiv(SupportVisit::MAX_MINUTES, 60).' horas.')
            ->visible(fn (Salon $record): bool => ! $record->trashed())
            ->schema([
                TextInput::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ej. No le aparecen las reservas del sábado')
                    ->required()
                    ->maxLength(SupportVisit::REASON_MAX_LENGTH),
            ])
            ->action(function (array $data, Salon $record, Component $livewire): void {
                /** @var User $admin */
                $admin = auth()->user();
                $request = request();

                app(SupportVisits::class)->start($admin, $record, trim((string) $data['reason']), $request->ip(), session()->driver());

                $livewire->redirect(route('dashboard'));
            });
    }
}
