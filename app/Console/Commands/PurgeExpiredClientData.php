<?php

namespace App\Console\Commands;

use App\Actions\Salon\PurgeExpiredClientData as Purge;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clients:purge-expired-data')]
#[Description('Erases care notes and client pictures that ran out their time, or whose salon closed')]
class PurgeExpiredClientData extends Command
{
    public function handle(Purge $purge): int
    {
        $erased = $purge->handle(CarbonImmutable::now());

        $this->info("Fichas sin alergias: {$erased['care']}. Fichas sin fotos: {$erased['photos']}.");

        return self::SUCCESS;
    }
}
