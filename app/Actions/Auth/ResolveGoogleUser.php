<?php

namespace App\Actions\Auth;

use App\Data\Auth\GoogleProfile;
use App\Exceptions\SocialLoginException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResolveGoogleUser
{
    /**
     * Find or link the account behind a Google sign-in. A new person gets
     * an account only once they accepted the terms ($createMissing), so a
     * plain sign-in returns null for them.
     *
     * An existing account is only linked when Google vouches for the email
     * (GoogleProfile checks it).
     */
    public function handle(GoogleProfile $profile, bool $createMissing = false): ?User
    {
        return DB::transaction(function () use ($profile, $createMissing): ?User {
            $user = User::where('google_id', $profile->googleId)->first()
                ?? User::where('email', $profile->email)->lockForUpdate()->first();

            if ($user === null) {
                return $createMissing ? $this->create($profile) : null;
            }

            if ($user->google_id !== null && $user->google_id !== $profile->googleId) {
                throw SocialLoginException::linkedToAnotherGoogleAccount();
            }

            if ($user->email_verified_at === null) {
                $this->evictPreviousHolder($user);
            }

            $user->google_id = $profile->googleId;
            $user->email_verified_at ??= now();
            $user->save();

            return $user;
        });
    }

    private function create(GoogleProfile $profile): User
    {
        $user = new User;
        $user->name = $profile->name;
        $user->email = $profile->email;
        $user->password = Str::password(32);
        $user->google_id = $profile->googleId;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * Nobody ever proved owning this email, so whoever registered it may be
     * an attacker waiting for the real owner (pre-account hijacking). Google
     * now proves ownership: revoke every credential set before that.
     */
    private function evictPreviousHolder(User $user): void
    {
        $user->password = Str::password(32);
        $user->setRememberToken(Str::random(60));
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->passkeys()->delete();

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
