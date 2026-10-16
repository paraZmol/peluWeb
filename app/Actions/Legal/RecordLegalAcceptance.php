<?php

namespace App\Actions\Legal;

use App\Data\Legal\AcceptanceProof;
use App\Enums\LegalDocumentType;
use App\Models\LegalAcceptance;
use App\Models\Salon;
use App\Models\User;
use App\Support\Legal\LegalLibrary;
use Illuminate\Support\Facades\DB;

/**
 * Keep the proof that an account accepted the current version of some
 * legal texts: one row per text, with its version and the hash of the
 * exact words published.
 */
class RecordLegalAcceptance
{
    public function __construct(private readonly LegalLibrary $library) {}

    /**
     * @param  list<LegalDocumentType>  $documents
     */
    public function handle(User $user, array $documents, AcceptanceProof $proof, ?Salon $salon = null): void
    {
        DB::transaction(function () use ($user, $documents, $proof, $salon): void {
            foreach ($documents as $document) {
                LegalAcceptance::record($user, $this->library->current($document), $proof, $salon);
            }
        });
    }

    /**
     * What every account accepts (casilla 1).
     */
    public function forAccount(User $user, AcceptanceProof $proof): void
    {
        $this->handle($user, LegalDocumentType::forAccounts(), $proof);
    }
}
