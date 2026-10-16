<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use App\Support\Money;
use Filament\Resources\Pages\EditRecord;

/**
 * Prices are stored in céntimos and typed in soles.
 */
class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $founder = $data['founder_price_cents'] ?? null;

        return [
            ...$data,
            'price' => Money::toAmount((int) $data['price_cents']),
            'founder_price' => is_int($founder) ? Money::toAmount($founder) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $founder = $data['founder_price'] ?? null;

        return [
            'name' => $data['name'],
            'price_cents' => Money::toCents((string) $data['price']),
            'founder_price_cents' => filled($founder) ? Money::toCents((string) $founder) : null,
            'max_staff' => (int) $data['max_staff'],
            'is_public' => (bool) $data['is_public'],
            'position' => (int) $data['position'],
        ];
    }
}
