<?php

namespace App\Concerns;

use App\Actions\Booking\QuoteBooking;
use App\Models\Salon;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * The "which services, with whom" part shared by the availability query
 * and the booking itself, so both read the request the same way.
 *
 * @mixin FormRequest
 */
trait ParsesBookingSelection
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function selectionRules(): array
    {
        return [
            'services' => ['required', 'array', 'list', 'max:'.QuoteBooking::MAX_SERVICES],
            'services.*.id' => ['required', 'integer', 'distinct'],
            'services.*.variant' => ['nullable', 'integer'],
            'staff' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<array{service_id: int, variant_id: int|null}>
     */
    public function selections(): array
    {
        /** @var list<array{id: int|string, variant?: int|string|null}> $services */
        $services = $this->validated('services');

        return array_map(fn (array $service): array => [
            'service_id' => (int) $service['id'],
            'variant_id' => isset($service['variant']) ? (int) $service['variant'] : null,
        ], $services);
    }

    /**
     * The chosen professional, or null for "anyone". Looked up inside the
     * given salon only: another salon's professional does not exist here.
     */
    public function staffIn(Salon $salon): ?Staff
    {
        $id = $this->integer('staff');

        if ($id === 0) {
            return null;
        }

        return Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->find($id)
            ?? throw ValidationException::withMessages(['staff' => 'Esa persona ya no está disponible. Elige otra.']);
    }
}
