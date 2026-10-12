<?php

namespace App\Console\Commands;

use App\Actions\Salon\PurgeExpiredClientData as Purge;
use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use App\Models\WalkIn;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clients:purge-expired-data')]
#[Description('Erases care notes and client pictures that ran out their time, or whose salon closed')]
class PurgeExpiredClientData extends Command
{
    /** Days a closed place on a waiting list, or a name in the walk-in line, is kept. */
    private const int LIST_DAYS = 30;

    public function handle(Purge $purge): int
    {
        $now = CarbonImmutable::now();
        $erased = $purge->handle($now);

        // Lists that only matter on their day: a name at the door, a place in a line.
        $old = $now->subDays(self::LIST_DAYS);
        WalkIn::withoutGlobalScope('salon')->where('created_at', '<', $old)->delete();
        WaitlistEntry::withoutGlobalScope('salon')->whereNotIn('status', [WaitlistStatus::Waiting, WaitlistStatus::Offered])->where('updated_at', '<', $old)->delete();

        $this->info("Fichas sin alergias: {$erased['care']}. Fichas sin fotos: {$erased['photos']}.");

        return self::SUCCESS;
    }
}
