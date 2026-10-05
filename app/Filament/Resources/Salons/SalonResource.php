<?php

namespace App\Filament\Resources\Salons;

use App\Filament\Concerns\AuthorizesPlatformAdmin;
use App\Filament\Resources\Salons\Pages\EditSalon;
use App\Filament\Resources\Salons\Pages\ListSalons;
use App\Filament\Resources\Salons\Pages\ViewSalon;
use App\Filament\Resources\Salons\Schemas\SalonForm;
use App\Filament\Resources\Salons\Schemas\SalonInfolist;
use App\Filament\Resources\Salons\Tables\SalonsTable;
use App\Models\Salon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Salons are created by their owners from the app; the platform admin
 * reviews them, edits the basics and controls status and trial.
 */
class SalonResource extends Resource
{
    use AuthorizesPlatformAdmin;

    protected static ?string $model = Salon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'salón';

    protected static ?string $pluralModelLabel = 'salones';

    protected static function allowedActions(): array
    {
        return ['viewAny', 'view', 'update', 'delete', 'restore'];
    }

    public static function form(Schema $schema): Schema
    {
        return SalonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalonsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalons::route('/'),
            'view' => ViewSalon::route('/{record}'),
            'edit' => EditSalon::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
