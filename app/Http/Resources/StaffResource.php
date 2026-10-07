<?php

namespace App\Http\Resources;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A professional in the owner's list. Expects `user`, `schedules` and
 * `services_count` to be loaded.
 *
 * @mixin Staff
 */
class StaffResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'photoUrl' => $this->photoUrl(),
            'hasOwnPhoto' => $this->photo_path !== null,
            'isActive' => $this->is_active,
            'userId' => $this->user_id,
            'accountEmail' => $this->user?->email,
            'workDays' => $this->schedules->pluck('weekday')->unique()->values(),
            'servicesCount' => (int) $this->getAttribute('services_count'),
        ];
    }
}
