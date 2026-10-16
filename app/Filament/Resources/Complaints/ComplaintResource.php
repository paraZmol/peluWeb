<?php

namespace App\Filament\Resources\Complaints;

use App\Filament\Concerns\AuthorizesPlatformAdmin;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Filament\Resources\Complaints\Schemas\ComplaintInfolist;
use App\Filament\Resources\Complaints\Tables\ComplaintsTable;
use App\Models\Complaint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The inbox of the platform's Libro de Reclamaciones (requirement P6).
 * Sheets are read and answered once; never edited nor deleted.
 */
class ComplaintResource extends Resource
{
    use AuthorizesPlatformAdmin;

    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'hoja de reclamación';

    protected static ?string $pluralModelLabel = 'Libro de Reclamaciones';

    protected static ?string $slug = 'libro-de-reclamaciones';

    protected static function allowedActions(): array
    {
        return ['viewAny', 'view'];
    }

    public static function table(Table $table): Table
    {
        return ComplaintsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ComplaintInfolist::configure($schema);
    }

    /**
     * Sheets still waiting for an answer.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = Complaint::query()->unanswered()->count();

        return $waiting === 0 ? null : (string) $waiting;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'view' => ViewComplaint::route('/{record}'),
        ];
    }
}
