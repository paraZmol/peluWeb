<?php

namespace App\Actions\Promotions;

use App\Data\Booking\QuoteItem;
use App\Domain\Promotions\PromotionCopy;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\Service;
use App\Support\Money;

/**
 * The promotions a salon is running today, as a visitor reads them on its
 * page and in the booking wizard. Only what is on offer now: drafts,
 * paused, scheduled and ended ones never leave the server.
 */
class PublicPromotions
{
    public function __construct(private readonly ApplyPromotion $apply) {}

    /**
     * @param  string|null  $sharedCode  the code of a shared link, when the visitor came through one
     * @return array{items: array<int, array<string, mixed>>, shared: array{id: int|null, hasEnded: bool}|null}
     */
    public function handle(Salon $salon, ?string $sharedCode = null): array
    {
        $today = $salon->localNow()->toDateString();
        $running = Promotion::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->runningOn($today)
            ->with(['services.variants', 'giftService'])
            ->orderBy('ends_on')
            ->orderBy('id')
            ->get()
            // One with no places left is not advertised: nobody could get it.
            ->filter(fn (Promotion $promotion): bool => $promotion->isIntact() && $this->apply->hasPlacesLeft($promotion))
            ->values();

        $shared = $sharedCode === null ? null : $running->first(fn (Promotion $promotion): bool => $promotion->share_code === $sharedCode);

        return [
            'items' => $running->map(fn (Promotion $promotion): array => $this->item($promotion, $salon))->all(),
            // A link to one that is over, or that never was, still lands on the
            // page: it says so and offers to book at the normal price.
            'shared' => $sharedCode === null ? null : ['id' => $shared?->id, 'hasEnded' => $shared === null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Promotion $promotion, Salon $salon): array
    {
        $terms = $promotion->terms();
        $services = $promotion->services->sortBy('position')->values();
        $names = array_values($services->map(fn (Service $service): string => $service->name)->all());
        $gift = $promotion->giftService;
        $written = PromotionCopy::title($terms, $names, $gift?->name);

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            // What it does, in the system's words, under a title the owner typed.
            'summary' => $written === $promotion->name ? null : $written,
            'overline' => $promotion->overline,
            'mechanic' => $promotion->mechanic->value,
            'endsOn' => $promotion->ends_on->toDateString(),
            // Read without opening the conditions: how long it runs and how many it takes.
            'period' => PromotionCopy::period($terms->startsOn, $terms->endsOn),
            'quota' => $promotion->quota,
            // "lunes a miércoles, de 10:00 a 16:00"; null when it holds every day and hour.
            'when' => PromotionCopy::when($terms),
            'finePrint' => PromotionCopy::finePrint($terms, $names, $promotion->facts($salon)),
            'serviceIds' => $services->modelKeys(),
            // What each included service costs with it, from its catalogue price:
            // a professional's own price or a chosen option may change it.
            'prices' => $services->map(function (Service $service) use ($terms): array {
                $normal = (int) ($service->variants->min('price_cents') ?? $service->price_cents);
                $priced = $terms->price([new QuoteItem($service->id, $service->name, $normal, 0, 0, 0)])[0];

                return [
                    'serviceId' => $service->id,
                    // Add-ons are not on the salon's public list: the card names them itself.
                    'name' => $service->name,
                    'normal' => Money::toAmount($normal),
                    'promo' => Money::toAmount($priced->priceCents),
                    'isFrom' => $service->variants->isNotEmpty(),
                ];
            })->all(),
            'gift' => $gift === null ? null : [
                'serviceId' => $gift->id,
                'name' => $gift->name,
                'price' => Money::toAmount($gift->price_cents),
                'minutes' => $gift->active_minutes + $gift->idle_minutes + $gift->finish_minutes,
            ],
        ];
    }
}
