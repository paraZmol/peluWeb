<?php

namespace App\Enums;

/**
 * What kind of working hours someone may book. Decided on the server from
 * who is asking; never taken from the request.
 */
enum ClientLevel: string
{
    case Standard = 'standard';
    case Vip = 'vip';
    /** The salon's own team booking for a client. */
    case Team = 'team';

    /**
     * @return list<ScheduleAccess>
     */
    public function allowedAccess(): array
    {
        return match ($this) {
            self::Standard => [ScheduleAccess::Public],
            self::Vip => [ScheduleAccess::Public, ScheduleAccess::Vip],
            self::Team => [ScheduleAccess::Public, ScheduleAccess::Vip, ScheduleAccess::StaffOnly],
        };
    }
}
