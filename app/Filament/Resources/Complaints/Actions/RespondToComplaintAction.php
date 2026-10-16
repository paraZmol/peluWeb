<?php

namespace App\Filament\Resources\Complaints\Actions;

use App\Actions\Complaints\RespondToComplaint;
use App\Exceptions\ComplaintException;
use App\Models\Complaint;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Writes the platform's answer, once, and emails it to the person.
 */
class RespondToComplaintAction
{
    private const int RESPONSE_MAX_LENGTH = 5000;

    public static function make(): Action
    {
        return Action::make('respond')
            ->label('Responder')
            ->icon('heroicon-o-paper-airplane')
            ->modalHeading(fn (Complaint $record): string => "Responder la hoja N.º {$record->code()}")
            ->modalDescription(fn (Complaint $record): string => "La respuesta se envía a {$record->email} con la fecha de hoy y queda guardada en la hoja. No se puede cambiar después.")
            ->modalSubmitActionLabel('Enviar respuesta')
            ->visible(fn (Complaint $record): bool => ! $record->isAnswered())
            ->schema([
                Textarea::make('response')
                    ->label('Respuesta')
                    ->rows(8)
                    ->required()
                    ->minLength(10)
                    ->maxLength(self::RESPONSE_MAX_LENGTH),
            ])
            ->action(function (array $data, Complaint $record): void {
                /** @var User $admin */
                $admin = auth()->user();

                try {
                    app(RespondToComplaint::class)->handle($record, $admin, trim((string) $data['response']));
                } catch (ComplaintException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title("Respuesta enviada a {$record->email}.")->send();
            });
    }
}
