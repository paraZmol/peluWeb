<?php

namespace App\Actions\Catalog;

use App\Models\Salon;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class SaveService
{
    /**
     * Create (when $service is null) or update a salon service and sync its
     * variants: listed ids are updated, new ones created, missing ones removed.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{id: int|null, label: string, price_cents: int, extra_minutes: int}>  $variants
     */
    public function handle(Salon $salon, ?Service $service, array $attributes, array $variants): Service
    {
        return DB::transaction(function () use ($salon, $service, $attributes, $variants): Service {
            if ($service === null) {
                $service = new Service;
                $service->salon_id = $salon->id;
                $service->position = (int) $salon->services()->max('position') + 1;
            }

            $service->fill($attributes)->save();
            $this->syncVariants($service, $variants);

            return $service->load('variants');
        });
    }

    /**
     * @param  list<array{id: int|null, label: string, price_cents: int, extra_minutes: int}>  $variants
     */
    private function syncVariants(Service $service, array $variants): void
    {
        $existing = $service->variants()->get()->keyBy('id');
        $keptIds = [];

        foreach ($variants as $position => $data) {
            // An id of another service is treated as a new variant.
            $variant = $existing->get($data['id'] ?? 0) ?? $service->variants()->make();
            $variant->fill([
                'label' => $data['label'],
                'price_cents' => $data['price_cents'],
                'extra_minutes' => $data['extra_minutes'],
                'position' => $position,
            ])->save();

            $keptIds[] = $variant->id;
        }

        $service->variants()->whereNotIn('id', $keptIds)->delete();
    }
}
