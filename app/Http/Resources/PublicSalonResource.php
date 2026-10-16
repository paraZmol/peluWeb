<?php

namespace App\Http\Resources;

use App\Data\Salon\PublicSalonContent;
use App\Data\Salon\SalonPreview;
use App\Enums\ServiceCategory;
use App\Models\Salon;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\Staff;
use App\Support\Images\ImageStore;
use App\Support\Money;
use App\Support\SamplePhotos;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * What a visitor sees on the salon's public page. Only public data: no
 * internal ids of members or staff, no status, no trial dates. With a
 * SalonPreview (owners only) it shows another template and sample photos.
 *
 * @mixin Salon
 */
class PublicSalonResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        Salon $salon,
        private readonly PublicSalonContent $content,
        private readonly ?SalonPreview $preview = null,
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
            'coverUrl' => $images->url($this->cover_path) ?? ($this->withSamples() ? SamplePhotos::cover($this->business_type) : null),
            'brand' => $this->brandTokens(),
            'theme' => ($this->preview->theme ?? $this->theme)->value,
            // Only in a preview: tells the page which photos are samples, not the salon's.
            'isPreview' => $this->preview !== null,
            'usesSamplePhotos' => $this->withSamples(),
            'whatsappUrl' => WhatsApp::link($this->phone, "Hola, vi {$this->name} en peluWeb y quisiera reservar."),
            // Online booking needs someone with working hours and a salon that takes bookings.
            'bookingUrl' => $this->content->openingHours !== null && $this->isWritable() && $this->accepts_online_bookings
                ? route('salons.booking.create', $this->slug)
                : null,
            // Paused for its plan (PLAN 7.3): the page stays up and says so.
            'bookingsPaused' => ! $this->isWritable(),
            'instagramUrl' => $this->instagram === null ? null : "https://instagram.com/{$this->instagram}",
            'address' => collect([$this->address, $this->district, $this->city])->filter()->join(', ') ?: null,
            'location' => $this->hasLocation() ? [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'directionsUrl' => 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude,
            ] : null,
            'photos' => $this->photos(),
            'team' => $this->team(),
            'openingHours' => $this->content->openingHours,
            'serviceGroups' => $this->serviceGroups(),
        ];
    }

    /**
     * @return array<int, array{label: string, services: array<int, array<string, mixed>>}>
     */
    private function serviceGroups(): array
    {
        return $this->content->services
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

    /**
     * @return list<array{url: string|null, caption: string|null}>
     */
    private function photos(): array
    {
        if ($this->content->photos->isEmpty() && $this->withSamples()) {
            return SamplePhotos::gallery($this->business_type);
        }

        $photos = [];

        foreach ($this->content->photos as $photo) {
            $photos[] = ['url' => $photo->url, 'caption' => $photo->caption];
        }

        return $photos;
    }

    /**
     * Who attends, as a client may see them: name, title and photo. In a
     * preview with samples, professionals without a photo get one.
     *
     * @return list<array{name: string, title: string|null, photoUrl: string|null}>
     */
    private function team(): array
    {
        $team = [];

        foreach ($this->content->team->values() as $index => $person) {
            $team[] = [
                'name' => $person->name,
                'title' => $person->title,
                'photoUrl' => $person->photoUrl() ?? ($this->withSamples() ? SamplePhotos::portrait($this->business_type, $index) : null),
            ];
        }

        return $team;
    }

    private function withSamples(): bool
    {
        return $this->preview !== null && $this->preview->withSamples;
    }
}
