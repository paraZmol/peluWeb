<?php

namespace App\Console\Commands;

use App\Actions\Catalog\SyncServiceTemplates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:sync')]
#[Description('Actualiza el catálogo de servicios de la plataforma desde database/data/service_templates.php')]
class SyncCatalog extends Command
{
    public function handle(SyncServiceTemplates $sync): int
    {
        $this->info($sync->handle().' servicios sincronizados en el catálogo.');

        return self::SUCCESS;
    }
}
