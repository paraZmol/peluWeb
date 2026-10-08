<?php

namespace App\Http\Resources;

use App\Enums\ServiceCategory;
use App\Models\Salon;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\Staff;
use App\Support\Images\ImageStore;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * What the public booking wizard needs to let someone choose services and
 * a professional. Only public data; availability comes from its own
 * endpoint, filtered by who is asking.
 *
 * @mixin Salon
 */
class BookableSalonResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  Collection<int, Service>  $services  active services with variants loaded
     * @param  Collection<int, Staff>  $staff  active professionals
     * @param  Collection<int, Collection<int, int>>  $performers  service id => staff ids
     */
    public function __construct(
        Salon $salon,
        private readonly Collection $services,
        private readonly Collection $staff,
        private readonly Collection $performers,
    ) {
        parent::__construct($salon);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'logoUrl' => app(ImageStore::class)->url($this->logo_path),
            'brand' => $this->brandTokens(),
            'theme' => $this->theme->value,
            'staff' => $this->staff->map(fn (Staff $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'title' => $person->title,
                'photoUrl' => $person->photoUrl(),
            ])->values(),
            'serviceGroups' => $this->serviceGroups(),
        ];
    }

    /**
     * Services someone on the team can actually perform, by category.
     *
     * @return array<int, array{label: string, services: array<int, array<string, mixed>>}>
     */
    private function serviceGroups(): array
    {
        return $this->services
            ->filter(fn (Service $service) => $this->performers->has($service->id))
            ->groupBy(fn (Service $service) => $service->category->value)
            ->sortBy(fn (Collection $group, string $category) => array_search(ServiceCategory::from($category), ServiceCategory::cases(), true))
            ->map(fn (Collection $group, string $category) => [
                'label' => ServiceCategory::from($category)->label(),
                'services' => $group->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'minutes' => $service->totalMinutes(),
                    'price' => Money::toAmount($service->price_cents),
                    'isAddon' => $service->is_addon,
                    'requiresEvaluation' => $service->requires_evaluation,
                    'staffIds' => $this->performers->get($service->id, collect())->values(),
                    'variants' => $service->variants->map(fn (ServiceVariant $variant) => [
                        'id' => $variant->id,
                        'label' => $variant->label,
                        'price' => Money::toAmount($variant->price_cents),
                        'extraMinutes' => $variant->extra_minutes,
                    ])->values(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
