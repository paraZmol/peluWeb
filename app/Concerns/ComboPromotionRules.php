<?php

namespace App\Concerns;

use App\Actions\Promotions\SavePromotion;
use App\Models\Promotion;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * What a combo must be (docs/promociones.md, "Combo a precio cerrado"):
 * two or three services with a price that can be frozen, saving between
 * 5 % and 50 % of their base prices. And "Ven con alguien", the other
 * promotion of several services: only services with one fixed price.
 *
 * @mixin FormRequest
 */
trait ComboPromotionRules
{
    /** "Ven con alguien": the second one at half price, or free. */
    public const int PAIR_HALF = 50;

    public const int PAIR_FREE = 100;

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

        $combo = Promotion::priceCombo($services, 0, $this->comboStaffIds());

        if (! $combo->isBookable) {
            $validator->errors()->add('services', 'Nadie del equipo elegido hace todos estos servicios: el combo no se podría reservar.');

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
     * @param  EloquentCollection<int, Service>  $services
     */
    protected function checkPair(Validator $validator, EloquentCollection $services): void
    {
        if ($services->contains('requires_evaluation', true) || $services->contains('is_addon', true)) {
            $validator->errors()->add('services', '«Ven con alguien» va con servicios de precio fijo: sin evaluación previa ni adicionales.');
        }
    }

    /**
     * A combo with bookings keeps the saving they were promised: prices
     * that moved since must not stop the owner from giving it a later end.
     */
    protected function keepsBookedSaving(?int $savingCents): bool
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion
            && $promotion->amount_cents === $savingCents
            && app(SavePromotion::class)->hasBookings($promotion);
    }

    /**
     * The services together at the lowest price someone can book them for.
     *
     * @param  EloquentCollection<int, Service>  $services
     */
    protected function comboNormalCents(EloquentCollection $services): int
    {
        return Promotion::priceCombo($services, 0, $this->comboStaffIds())->normalCents;
    }

    /**
     * "Solo con…": the combo is priced as those professionals charge it.
     *
     * @return list<int>|null null = all of the team
     */
    private function comboStaffIds(): ?array
    {
        $ids = array_values(array_map(intval(...), (array) $this->input('staff', [])));

        return $ids === [] ? null : $ids;
    }
}
