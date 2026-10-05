<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveGoogleUser;
use App\Exceptions\SocialLoginException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleLoginController extends Controller
{
    public static function isEnabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::isEnabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, ResolveGoogleUser $resolveGoogleUser): RedirectResponse
    {
        abort_unless(self::isEnabled(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
            $user = $resolveGoogleUser->handle($googleUser);
        } catch (SocialLoginException $exception) {
            return to_route('login')->withErrors(['email' => $exception->getMessage()]);
        } catch (Throwable) {
            return to_route('login')->withErrors(['email' => SocialLoginException::cancelled()->getMessage()]);
        }

        // Google proves the email, not the second factor: 2FA still applies.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);

            return to_route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
