<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteGoogleSignup;
use App\Actions\Auth\ResolveGoogleUser;
use App\Data\Auth\GoogleProfile;
use App\Data\Legal\AcceptanceProof;
use App\Enums\AcceptanceChannel;
use App\Exceptions\SocialLoginException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptLegalTermsRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Models\User;
use App\Support\Legal\LegalLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleLoginController extends Controller
{
    /** A new person between Google and the terms: no account yet. */
    public const string PENDING_KEY = 'google_signup';

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
            $profile = GoogleProfile::fromSocialite(Socialite::driver('google')->user());
            $user = $resolveGoogleUser->handle($profile);
        } catch (SocialLoginException $exception) {
            return to_route('login')->withErrors(['email' => $exception->getMessage()]);
        } catch (Throwable) {
            return to_route('login')->withErrors(['email' => SocialLoginException::cancelled()->getMessage()]);
        }

        if ($user === null) {
            $request->session()->put(self::PENDING_KEY, $profile->toSession());

            return to_route('google.terms');
        }

        return $this->signIn($request, $user);
    }

    /**
     * "Al continuar verás los términos antes de crear tu cuenta."
     */
    public function terms(Request $request, LegalLibrary $library): Response|RedirectResponse
    {
        $profile = GoogleProfile::fromSession($request->session()->get(self::PENDING_KEY));

        if ($profile === null) {
            return to_route('login');
        }

        return Inertia::render('auth/accept-terms', [
            'mode' => 'google',
            'account' => ['name' => $profile->name, 'email' => $profile->email],
            'documents' => LegalDocumentResource::forAccounts($library),
        ]);
    }

    public function completeSignup(AcceptLegalTermsRequest $request, CompleteGoogleSignup $completeSignup): RedirectResponse
    {
        $profile = GoogleProfile::fromSession($request->session()->pull(self::PENDING_KEY));

        if ($profile === null) {
            return to_route('login');
        }

        $user = $completeSignup->handle($profile, AcceptanceProof::fromRequest($request, AcceptanceChannel::Google));

        return $this->signIn($request, $user);
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
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
