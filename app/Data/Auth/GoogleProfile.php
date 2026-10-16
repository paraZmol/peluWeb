<?php

namespace App\Data\Auth;

use App\Exceptions\SocialLoginException;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Who Google says signed in, once Google vouches for the email. Kept in the
 * session while a new person reads the terms, before their account exists.
 */
final readonly class GoogleProfile
{
    public function __construct(
        public string $googleId,
        public string $email,
        public string $name,
    ) {}

    public static function fromSocialite(SocialiteUser $googleUser): self
    {
        $email = Str::lower((string) $googleUser->getEmail());
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];

        // Otherwise anyone could create a Google account with someone else's
        // address and take over their peluWeb account.
        if ($email === '' || ($raw['email_verified'] ?? false) !== true) {
            throw SocialLoginException::unverifiedEmail();
        }

        return new self((string) $googleUser->getId(), $email, $googleUser->getName() ?: Str::before($email, '@'));
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromSession(?array $stored): ?self
    {
        if (! is_string($stored['googleId'] ?? null) || ! is_string($stored['email'] ?? null) || ! is_string($stored['name'] ?? null)) {
            return null;
        }

        return new self($stored['googleId'], $stored['email'], $stored['name']);
    }

    /**
     * @return array{googleId: string, email: string, name: string}
     */
    public function toSession(): array
    {
        return ['googleId' => $this->googleId, 'email' => $this->email, 'name' => $this->name];
    }
}
