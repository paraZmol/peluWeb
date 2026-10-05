<?php

namespace App\Actions\Auth;

use App\Exceptions\SocialLoginException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveGoogleUser
{
    /**
     * Find, link or create the account behind a Google sign-in.
     *
     * An existing account is only linked when Google vouches for the email;
     * otherwise anyone could create a Google account with someone else's
     * address and take over their peluWeb account.
     */
    public function handle(SocialiteUser $googleUser): User
    {
        $email = Str::lower((string) $googleUser->getEmail());
        $googleId = (string) $googleUser->getId();
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];

        if ($email === '' || ($raw['email_verified'] ?? false) !== true) {
            throw SocialLoginException::unverifiedEmail();
        }

        return DB::transaction(function () use ($googleUser, $email, $googleId): User {
            $user = User::where('google_id', $googleId)->first()
                ?? User::where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                return $this->create($googleUser, $email, $googleId);
            }

            if ($user->google_id !== null && $user->google_id !== $googleId) {
                throw SocialLoginException::linkedToAnotherGoogleAccount();
            }

            if ($user->email_verified_at === null) {
                $this->evictPreviousHolder($user);
            }

            $user->google_id = $googleId;
            $user->email_verified_at ??= now();
            $user->save();

            return $user;
        });
    }

    private function create(SocialiteUser $googleUser, string $email, string $googleId): User
    {
        $user = new User;
        $user->name = $googleUser->getName() ?: Str::before($email, '@');
        $user->email = $email;
        $user->password = Str::password(32);
        $user->google_id = $googleId;
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
