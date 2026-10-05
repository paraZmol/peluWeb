<?php

namespace App\Filament\Resources\Salons\Pages;

use App\Filament\Resources\Salons\SalonResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditSalon extends EditRecord
{
    /**
     * Fields the platform admin may change. Status and trial are not mass
     * assignable on the model, so they are written explicitly here.
     */
    private const array EDITABLE = ['name', 'phone', 'status', 'trial_ends_at'];

    protected static string $resource = SalonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill(Arr::only($data, self::EDITABLE))->save();

        return $record;
    }
}
