<?php

namespace App\Actions\Booking;

use App\Data\Booking\BookingQuote;
use App\Data\Booking\QuoteItem;
use App\Exceptions\BookingException;
use App\Models\Salon;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Prices and times a selection of services for each professional who can
 * do all of them. Names the salon explicitly: visitors booking on the
 * public page have no current salon.
 */
class QuoteBooking
{
    public const int MAX_SERVICES = 5;

    private const int MIN_ACTIVE_MINUTES = 5;

    /**
     * @param  list<array{service_id: int, variant_id: int|null}>  $selections  in the order they will be done
     * @param  Collection<int, Staff>  $staff  candidates, all of this salon
     * @return array<int, BookingQuote> keyed by staff id; who does not perform every service is left out
     */
    public function handle(Salon $salon, array $selections, Collection $staff): array
    {
        $services = $this->services($salon, $selections);

        $pivots = DB::table('service_staff')
            ->whereIn('staff_id', $staff->pluck('id'))
            ->whereIn('service_id', $services->keys())
            ->get()
            ->groupBy('staff_id');

        $quotes = [];

        foreach ($staff as $person) {
            $own = $pivots->get($person->id, collect())->keyBy('service_id');

            if ($own->count() === $services->count()) {
                $quotes[$person->id] = new BookingQuote(array_map(
                    fn (array $selection): QuoteItem => $this->item($services[$selection['service_id']], $selection['variant_id'], $own[$selection['service_id']]),
                    $selections,
                ));
            }
        }

        return $quotes;
    }

    /**
     * The selected services, checked: active, of this salon, not repeated
     * and not only add-ons.
     *
     * @param  list<array{service_id: int, variant_id: int|null}>  $selections
     * @return Collection<int, Service> keyed by id
     */
    private function services(Salon $salon, array $selections): Collection
    {
        $ids = array_column($selections, 'service_id');

        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            throw BookingException::serviceUnavailable();
        }

        if (count($ids) > self::MAX_SERVICES) {
            throw BookingException::tooManyServices(self::MAX_SERVICES);
        }

        $services = Service::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->with('variants')
            ->get()
            ->keyBy('id');

        if ($services->count() !== count($ids)) {
            throw BookingException::serviceUnavailable();
        }

        if ($services->every(fn (Service $service) => $service->is_addon)) {
            throw BookingException::onlyAddons();
        }

        return $services;
    }

    /**
     * A chosen option (e.g. hair length) sets the price and adds its extra
     * time; otherwise the professional's own price wins over the salon's.
     * The professional's own working time always applies.
     */
    private function item(Service $service, ?int $variantId, stdClass $pivot): QuoteItem
    {
        $variant = $this->variant($service, $variantId);
        $active = (int) ($pivot->active_minutes ?? $service->active_minutes) + ($variant->extra_minutes ?? 0);

        return new QuoteItem(
            serviceId: $service->id,
            name: $variant === null ? $service->name : "{$service->name} · {$variant->label}",
            priceCents: $variant->price_cents ?? (int) ($pivot->price_cents ?? $service->price_cents),
            activeMinutes: max(self::MIN_ACTIVE_MINUTES, $active),
            idleMinutes: $service->idle_minutes,
            finishMinutes: $service->finish_minutes,
            variantId: $variant?->id,
        );
    }

    /**
     * A service with options needs one of them chosen; one without options
     * accepts none.
     */
    private function variant(Service $service, ?int $variantId): ?ServiceVariant
    {
        $variant = $variantId === null ? null : $service->variants->firstWhere('id', $variantId);

        if (($variant === null) !== $service->variants->isEmpty()) {
            throw BookingException::serviceUnavailable();
        }

        return $variant;
    }
}
