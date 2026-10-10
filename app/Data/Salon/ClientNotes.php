<?php

namespace App\Data\Salon;

/**
 * What the team writes on a client's file.
 */
final readonly class ClientNotes
{
    /**
     * @param  string|null  $notes  how the person likes to be attended
     * @param  string|null  $formulaNotes  products, tones and times of their usual work
     * @param  string|null  $careNotes  allergies and reactions: health data
     * @param  bool  $hasCareConsent  the salon keeps the person's signed authorization for the care notes
     */
    public function __construct(
        public ?string $notes,
        public ?string $formulaNotes,
        public ?string $careNotes,
        public bool $hasCareConsent,
    ) {}
}
