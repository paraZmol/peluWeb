<?php

namespace App\Data\Salon;

use App\Models\SalonPhoto;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Support\Collection;

/**
 * Everything the public page shows besides the salon row itself, loaded
 * by the controller with the salon named explicitly (visitors have no
 * current salon).
 */
final readonly class PublicSalonContent
{
    /**
     * @param  Collection<int, Service>  $services  active, non-addon, with variants
     * @param  Collection<int, SalonPhoto>  $photos  in gallery order
     * @param  Collection<int, Staff>  $team  active professionals, with their account loaded
     * @param  array<string, mixed>|null  $openingHours  null while nobody has working hours
     */
    public function __construct(
        public Collection $services,
        public Collection $photos,
        public Collection $team,
        public ?array $openingHours,
    ) {}
}
