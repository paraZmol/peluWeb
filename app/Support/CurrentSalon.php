<?php

namespace App\Support;

use App\Models\Salon;

/**
 * The salon the current request is acting on. Registered as a scoped
 * singleton, so it is reset between requests and queued jobs.
 */
class CurrentSalon
{
    private ?Salon $salon = null;

    public function set(?Salon $salon): void
    {
        $this->salon = $salon;
    }

    public function get(): ?Salon
    {
        return $this->salon;
    }

    public function id(): ?int
    {
        return $this->salon?->id;
    }

    public function has(): bool
    {
        return $this->salon !== null;
    }
}
