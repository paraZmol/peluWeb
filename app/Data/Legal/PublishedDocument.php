<?php

namespace App\Data\Legal;

use App\Enums\LegalDocumentType;
use Carbon\CarbonImmutable;

/**
 * One version of a legal text exactly as the public reads it: its gaps
 * already filled and the notes for whoever reviews it left out.
 */
final readonly class PublishedDocument
{
    public function __construct(
        public LegalDocumentType $type,
        public string $title,
        public string $version,
        public CarbonImmutable $effectiveOn,
        public string $changes,
        public string $markdown,
    ) {}

    /**
     * SHA-256 of the published words: what an acceptance proves was shown.
     */
    public function hash(): string
    {
        return hash('sha256', $this->markdown);
    }
}
