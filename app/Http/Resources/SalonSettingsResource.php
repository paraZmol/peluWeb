<?php

namespace App\Http\Resources;

use App\Models\Salon;
use App\Models\SalonPhoto;
use App\Support\Images\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the owner's "Mi salón" screen edits.
 *
 * @mixin Salon
 */
class SalonSettingsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $images = app(ImageStore::class);

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'publicUrl' => route('salons.show', $this->slug),
            'businessType' => $this->business_type->value,
            'clientele' => $this->clientele->value,
            'description' => $this->description,
            'phone' => $this->phone,
            'instagram' => $this->instagram,
            'logoUrl' => $images->url($this->logo_path),
            'coverUrl' => $images->url($this->cover_path),
            'brandColor' => $this->brand_color,
            'brand' => $this->brandTokens(),
            'address' => $this->address,
            'district' => $this->district,
            'city' => $this->city,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'booking' => [
                'acceptsOnlineBookings' => $this->accepts_online_bookings,
                'minLeadMinutes' => $this->min_lead_minutes,
                'maxDaysAhead' => $this->max_days_ahead,
                'slotStepMinutes' => $this->slot_step_minutes,
                'cancelWindowHours' => $this->cancel_window_hours,
                'depositPercent' => $this->deposit_percent,
                'vipDepositPercent' => $this->vip_deposit_percent,
                'holdMinutes' => $this->hold_minutes,
                'paymentInstructions' => $this->payment_instructions,
            ],
            'photos' => $this->photos->map(fn (SalonPhoto $photo) => [
                'id' => $photo->id,
                'url' => $photo->url,
                'caption' => $photo->caption,
            ])->values(),
        ];
    }
}
