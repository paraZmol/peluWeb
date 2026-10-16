<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Concerns\AuthorizesPlatformAdmin;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use App\Models\Staff;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The plans salons pay for. Edited, never created or deleted here: the
 * plans themselves are a decision of the plan (docs/PLAN.md §11), and a
 * salon always points at one. Prices are typed in soles.
 */
class PlanResource extends Resource
{
    use AuthorizesPlatformAdmin;

    private const string PRICE_RULE = '/^\d{1,5}(\.\d{1,2})?$/';

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'planes';

    protected static function allowedActions(): array
    {
        return ['viewAny', 'update'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(40),
            TextInput::make('price')->label('Precio al mes (S/, IGV incluido)')->required()->regex(self::PRICE_RULE),
            TextInput::make('founder_price')->label('Precio fundador (S/, vacío = sin precio fundador)')->regex(self::PRICE_RULE),
            TextInput::make('max_staff')
                ->label('Profesionales con agenda')
                ->required()
                ->integer()
                ->minValue(1)
                ->maxValue(Staff::MAX_PER_SALON),
            Toggle::make('is_public')->label('Se muestra en la portada'),
            TextInput::make('position')->label('Orden')->required()->integer()->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('price_cents')
                    ->label('Precio al mes')
                    ->formatStateUsing(fn (int $state): string => 'S/ '.Money::toAmount($state)),
                TextColumn::make('founder_price_cents')
                    ->label('Precio fundador')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : 'S/ '.Money::toAmount($state))
                    ->placeholder('-'),
                TextColumn::make('max_staff')->label('Profesionales con agenda'),
                IconColumn::make('is_public')->label('En la portada')->boolean(),
                TextColumn::make('salons_count')->label('Salones')->counts('salons'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
