<?php

namespace App\Actions\Fortify;

use App\Actions\Legal\RecordLegalAcceptance;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Data\Legal\AcceptanceProof;
use App\Enums\AcceptanceChannel;
use App\Http\Requests\Auth\AcceptLegalTermsRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly RecordLegalAcceptance $recordAcceptance) {}

    /**
     * Validate and create a newly registered user, with the proof that they
     * accepted the terms and the privacy policy (casilla 1).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            AcceptLegalTermsRequest::FIELD => ['accepted'],
        ], [
            AcceptLegalTermsRequest::FIELD.'.accepted' => AcceptLegalTermsRequest::MESSAGE,
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->recordAcceptance->forAccount($user, AcceptanceProof::fromRequest(request(), AcceptanceChannel::Registration));

            return $user;
        });
    }
}
