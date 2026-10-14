<?php

namespace App\Concerns;

use App\Models\Promotion;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Validator;

/**
 * What a combo must be (docs/promociones.md, "Combo a precio cerrado"):
 * two or three services with a price that can be frozen, saving between
 * 5 % and 50 % of their base prices.
 */
trait ComboPromotionRules
{
    public const int COMBO_MIN_SERVICES = 2;

    public const int COMBO_MAX_SERVICES = 3;

    public const int COMBO_MIN_PERCENT_OFF = 5;

    public const int COMBO_MAX_PERCENT_OFF = 50;

    /**
     * @param  EloquentCollection<int, Service>  $services
     */
    protected function checkCombo(Validator $validator, EloquentCollection $services, int $priceCents): void
    {
        if ($services->contains('requires_evaluation', true) || $services->every('is_addon', true)) {
            $validator->errors()->add('services', 'Un combo no puede llevar servicios con evaluación previa ni ser solo de adicionales.');

            return;
        }

        $combo = Promotion::priceCombo($services, 0);

        if (! $combo->isBookable) {
            $validator->errors()->add('services', 'Nadie del equipo hace todos estos servicios: el combo no se podría reservar.');

            return;
        }

        $normal = $combo->normalCents;
        $lowest = intdiv($normal * (100 - self::COMBO_MAX_PERCENT_OFF), 100);
        $highest = intdiv($normal * (100 - self::COMBO_MIN_PERCENT_OFF), 100);

        if ($priceCents < $lowest || $priceCents > $highest) {
            $validator->errors()->add('combo_price', 'El precio del combo debe estar entre '.Money::toAmount($lowest).' y '.Money::toAmount($highest)
                .' soles: por separado cuestan S/ '.Money::toAmount($normal).'.');
        }
    }

    /**
     * The services together at the lowest price someone can book them for.
     *
     * @param  EloquentCollection<int, Service>  $services
     */
    protected function comboNormalCents(EloquentCollection $services): int
    {
        return Promotion::priceCombo($services, 0)->normalCents;
    }
}
