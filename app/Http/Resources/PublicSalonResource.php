<?php

namespace App\Http\Resources;

use App\Enums\ServiceCategory;
use App\Models\Salon;
use App\Models\SalonPhoto;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Support\Images\ImageStore;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * What a visitor sees on the salon's public page. Only public data: no
 * internal ids of members, no status, no trial dates.
 *
 * @mixin Salon
 */
class PublicSalonResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  Collection<int, Service>  $services  active services of this salon
     * @param  Collection<int, SalonPhoto>  $photos
     * @param  array<string, mixed>|null  $openingHours  null while nobody has working hours
     */
    public function __construct(
        Salon $salon,
        private readonly Collection $services,
        private readonly Collection $photos,
        private readonly ?array $openingHours = null,
    ) {
        parent::__construct($salon);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $images = app(ImageStore::class);

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'businessType' => $this->business_type->label(),
            'clientele' => $this->clientele->label(),
            'description' => $this->description,
            'logoUrl' => $images->url($this->logo_path),
            'coverUrl' => $images->url($this->cover_path),
            'brand' => $this->brandTokens(),
            'whatsappUrl' => WhatsApp::link($this->phone, "Hola, vi {$this->name} en peluWeb y quisiera reservar."),
            // Online booking needs someone with working hours and a salon that takes bookings.
            'bookingUrl' => $this->openingHours !== null && $this->isWritable() && $this->accepts_online_bookings
                ? route('salons.booking.create', $this->slug)
                : null,
            'instagramUrl' => $this->instagram === null ? null : "https://instagram.com/{$this->instagram}",
            'address' => collect([$this->address, $this->district, $this->city])->filter()->join(', ') ?: null,
            'location' => $this->hasLocation() ? [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'directionsUrl' => 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude,
            ] : null,
            'photos' => $this->photos->map(fn (SalonPhoto $photo) => ['url' => $photo->url, 'caption' => $photo->caption])->values(),
            'openingHours' => $this->openingHours,
            'serviceGroups' => $this->serviceGroups(),
        ];
    }

    /**
     * @return array<int, array{label: string, services: array<int, array<string, mixed>>}>
     */
    private function serviceGroups(): array
    {
        return $this->services
            ->groupBy(fn (Service $service) => $service->category->value)
            ->sortBy(fn (Collection $group, string $category) => array_search(ServiceCategory::from($category), ServiceCategory::cases(), true))
            ->map(fn (Collection $group, string $category) => [
                'label' => ServiceCategory::from($category)->label(),
                'services' => $group->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'minutes' => $service->totalMinutes(),
                    'priceFrom' => Money::toAmount($service->variants->min('price_cents') ?? $service->price_cents),
                    'hasVariants' => $service->variants->isNotEmpty(),
                    'requiresEvaluation' => $service->requires_evaluation,
                    'whatsappUrl' => WhatsApp::link($this->phone, "Hola, quisiera reservar \"{$service->name}\" en {$this->name}."),
                    'variants' => $service->variants->map(fn (ServiceVariant $variant) => [
                        'label' => $variant->label,
                        'price' => Money::toAmount($variant->price_cents),
                    ])->values(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
