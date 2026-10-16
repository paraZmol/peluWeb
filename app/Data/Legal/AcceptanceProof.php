<?php

namespace App\Data\Legal;

use App\Enums\AcceptanceChannel;
use Illuminate\Http\Request;

/**
 * Where and from what device a legal text was accepted.
 */
final readonly class AcceptanceProof
{
    private function __construct(
        public AcceptanceChannel $channel,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}

    public static function fromRequest(Request $request, AcceptanceChannel $channel): self
    {
        return new self($channel, $request->ip(), mb_substr((string) $request->userAgent(), 0, 512));
    }

    /**
     * Demo and test accounts: nobody accepted anything in person.
     */
    public static function seeded(): self
    {
        return new self(AcceptanceChannel::Seeded);
    }
}
