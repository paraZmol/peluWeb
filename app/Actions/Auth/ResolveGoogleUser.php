<?php

namespace App\Actions\Auth;

use App\Exceptions\SocialLoginException;
use App\Models\User;
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
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];

        if ($email === '' || ($raw['email_verified'] ?? false) !== true) {
            throw SocialLoginException::unverifiedEmail();
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user === null) {
            $user = new User;
            $user->name = $googleUser->getName() ?: Str::before($email, '@');
            $user->email = $email;
            $user->password = Str::password(32);
        }

        $user->google_id = (string) $googleUser->getId();
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }
}
