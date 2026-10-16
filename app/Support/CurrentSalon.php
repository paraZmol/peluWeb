<?php

namespace App\Support;

use App\Models\Salon;
use App\Models\SupportVisit;

/**
 * The salon the current request is acting on. Registered as a scoped
 * singleton, so it is reset between requests and queued jobs.
 *
 * Fail closed: with no salon set, salon-scoped models return nothing unless
 * the caller explicitly opted out with bypass() (platform panel, console
 * maintenance). Every bypass is a deliberate, greppable decision.
 */
class CurrentSalon
{
    private ?Salon $salon = null;

    private bool $bypassed = false;

    private ?SupportVisit $supportVisit = null;

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

    /**
     * See every salon's data. Only for the platform admin and console tasks.
     */
    public function bypass(): void
    {
        $this->bypassed = true;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * The platform admin is looking at this salon for support: read only.
     */
    public function setSupportVisit(SupportVisit $visit): void
    {
        $this->supportVisit = $visit;
    }

    public function supportVisit(): ?SupportVisit
    {
        return $this->supportVisit;
    }
}
