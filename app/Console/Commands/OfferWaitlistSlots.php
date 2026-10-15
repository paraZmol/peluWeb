<?php

namespace App\Console\Commands;

use App\Actions\Waitlist\OfferFreedSlots;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('waitlist:offer')]
#[Description('Tells the next person on each waiting list when a time opened up for them')]
class OfferWaitlistSlots extends Command
{
    public function handle(OfferFreedSlots $offer): int
    {
        $this->info('Avisos de lista de espera: '.$offer->handle(CarbonImmutable::now()));

        return self::SUCCESS;
    }
}
