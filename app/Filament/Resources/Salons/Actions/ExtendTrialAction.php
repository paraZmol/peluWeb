<?php

namespace App\Filament\Resources\Salons\Actions;

use App\Actions\Billing\ExtendTrial;
use App\Exceptions\BillingException;
use App\Models\Salon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * The one extension of a trial, offered by hand to a salon still deciding.
 * Not advertised anywhere (PLAN §11, 2026-10-15).
 */
class ExtendTrialAction
{
    public static function make(): Action
    {
        $days = (int) config('billing.trial_extension_days');

        return Action::make('extendTrial')
            ->label("Prorrogar prueba {$days} días")
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription("Solo se puede una vez por salón y solo si todavía no pagó. Si la prueba ya terminó, los {$days} días cuentan desde hoy.")
            ->visible(fn (Salon $record): bool => ! $record->trashed() && $record->trial_extended_at === null && $record->paid_until === null)
            ->action(function (Salon $record): void {
                try {
                    $salon = app(ExtendTrial::class)->handle($record);
                } catch (BillingException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()
                    ->title('Prueba prorrogada hasta el '.$salon->trial_ends_at?->setTimezone($salon->timezone)->format('d/m/Y').'.')
                    ->send();
            });
    }
}
