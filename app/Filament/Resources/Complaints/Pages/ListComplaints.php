<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\Actions\ExportComplaintsAction;
use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Resources\Pages\ListRecords;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportComplaintsAction::make(),
        ];
    }
}
