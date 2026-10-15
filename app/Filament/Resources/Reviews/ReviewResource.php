<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Concerns\AuthorizesPlatformAdmin;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reviews a salon asked the platform to look at. The platform is the only
 * one who hides a review; salons never delete them.
 */
class ReviewResource extends Resource
{
    use AuthorizesPlatformAdmin;

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $modelLabel = 'reseña reportada';

    protected static ?string $pluralModelLabel = 'reseñas reportadas';

    protected static function allowedActions(): array
    {
        return ['viewAny', 'view', 'update'];
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    /**
     * Only what was reported, resolved or not.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('withdrawn_at')->whereNotNull('reported_at')->with('salon');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = Review::query()->awaitingModeration()->count();

        return $waiting === 0 ? null : (string) $waiting;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
