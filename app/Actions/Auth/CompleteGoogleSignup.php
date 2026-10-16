<?php

namespace App\Actions\Auth;

use App\Actions\Legal\RecordLegalAcceptance;
use App\Data\Auth\GoogleProfile;
use App\Data\Legal\AcceptanceProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A new person signing in with Google read and accepted the terms
 * (casilla 2): only now their account exists, with the proof next to it.
 */
class CompleteGoogleSignup
{
    public function __construct(
        private readonly ResolveGoogleUser $resolveGoogleUser,
        private readonly RecordLegalAcceptance $recordAcceptance,
    ) {}

    public function handle(GoogleProfile $profile, AcceptanceProof $proof): User
    {
        return DB::transaction(function () use ($profile, $proof): User {
            $user = $this->resolveGoogleUser->handle($profile, createMissing: true);
            assert($user !== null);

            $this->recordAcceptance->forAccount($user, $proof);

            return $user;
        });
    }
}
