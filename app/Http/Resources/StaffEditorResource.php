<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Everything the "hours, time off and services" screen of one professional
 * edits. Expects `user`, `schedules`, `timeOff` and `services` loaded.
 *
 * @mixin Staff
 */
class StaffEditorResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  Collection<int, Service>  $salonServices  every service of the salon
     * @param  string  $timezone  the salon's, to show time off on its clock
     */
    public function __construct(Staff $staff, private readonly Collection $salonServices, private readonly string $timezone)
    {
        parent::__construct($staff);
    }

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
            'ranges' => $this->schedules->map(fn (StaffSchedule $schedule) => [
                'weekday' => $schedule->weekday,
                'start' => $schedule->start(),
                'end' => $schedule->end(),
                'accessLevel' => $schedule->access_level->value,
            ])->values(),
            'timeOff' => $this->timeOff->map($this->timeOffItem(...))->values(),
            'services' => $this->salonServices->map($this->serviceItem(...))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timeOffItem(StaffTimeOff $timeOff): array
    {
        $start = $timeOff->starts_at->setTimezone($this->timezone);
        $end = $timeOff->ends_at->setTimezone($this->timezone);
        $isAllDay = $start->format('H:i') === '00:00' && $end->format('H:i') === '00:00';

        return [
            'id' => $timeOff->id,
            'startsOn' => $start->format('Y-m-d'),
            // A whole-day absence ends at midnight after its last day.
            'endsOn' => ($isAllDay ? $end->subDay() : $end)->format('Y-m-d'),
            'allDay' => $isAllDay,
            'startTime' => $start->format('H:i'),
            'endTime' => $end->format('H:i'),
            'reason' => $timeOff->reason->label(),
            'note' => $timeOff->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceItem(Service $service): array
    {
        $pivot = $this->services->firstWhere('id', $service->id)?->getRelation('pivot');
        $ownPriceCents = $pivot?->getAttribute('price_cents');

        return [
            'id' => $service->id,
            'name' => $service->name,
            'isActive' => $service->is_active,
            'baseMinutes' => $service->active_minutes,
            'waitMinutes' => $service->idle_minutes + $service->finish_minutes,
            'basePrice' => Money::toAmount($service->price_cents),
            'assigned' => $pivot !== null,
            'activeMinutes' => $pivot?->getAttribute('active_minutes'),
            'price' => $ownPriceCents === null ? null : Money::toAmount((int) $ownPriceCents),
        ];
    }
}
