<?php

namespace App\Actions\Legal;

use App\Data\Legal\PublishedDocument;
use App\Enums\LegalDocumentType;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Support\Legal\LegalLibrary;

/**
 * Which of the texts every account accepts are missing at their current
 * version: a new account from Google, an account from before the texts
 * existed, or one that accepted an older version.
 */
class PendingLegalAcceptances
{
    public function __construct(private readonly LegalLibrary $library) {}

    /**
     * @return list<PublishedDocument>
     */
    public function for(User $user): array
    {
        $current = array_map($this->library->current(...), LegalDocumentType::forAccounts());

        $accepted = LegalAcceptance::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($current): void {
                foreach ($current as $document) {
                    $query->orWhere(fn ($match) => $match
                        ->where('document', $document->type)
                        ->where('version', $document->version));
                }
            })
            ->distinct()
            ->pluck('document')
            ->all();

        return array_values(array_filter(
            $current,
            fn (PublishedDocument $document): bool => ! in_array($document->type, $accepted, true),
        ));
    }

    /**
     * A short tag of the current versions: once an account is known to have
     * accepted them, the session remembers it and skips the query.
     */
    public function currentSignature(User $user): string
    {
        return $user->id.':'.collect(LegalDocumentType::forAccounts())
            ->map(fn (LegalDocumentType $type): string => $type->value.'@'.$this->library->current($type)->version)
            ->implode('|');
    }

    /**
     * Whether the account accepted any earlier version: the screen then
     * says what changed instead of welcoming them.
     */
    public function acceptedBefore(User $user): bool
    {
        return LegalAcceptance::query()->where('user_id', $user->id)
            ->whereIn('document', LegalDocumentType::forAccounts())
            ->exists();
    }
}
