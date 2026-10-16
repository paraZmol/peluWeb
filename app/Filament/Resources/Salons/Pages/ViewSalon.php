<?php

namespace App\Filament\Resources\Salons\Pages;

use App\Filament\Resources\Salons\Actions\ExtendTrialAction;
use App\Filament\Resources\Salons\Actions\RecordPaymentAction;
use App\Filament\Resources\Salons\Actions\SupportVisitAction;
use App\Filament\Resources\Salons\SalonResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalon extends ViewRecord
{
    protected static string $resource = SalonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),
            ExtendTrialAction::make(),
            SupportVisitAction::make(),
            EditAction::make(),
        ];
    }
}
