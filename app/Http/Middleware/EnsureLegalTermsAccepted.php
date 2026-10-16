<?php

namespace App\Http\Middleware;

use App\Actions\Legal\PendingLegalAcceptances;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account without the current version of the terms and the privacy
 * policy (requisito P3) sees the acceptance screen and nothing else, but
 * may still read the texts, sign out, delete the account or file a
 * complaint. The platform admin in support mode accepts for themselves:
 * the check is always about the signed-in person, never the salon.
 */
class EnsureLegalTermsAccepted
{
    public const string SESSION_KEY = 'legal_terms_accepted';

    public const string API_CODE = 'terms_not_accepted';

    /** @var list<string> */
    private const array ALWAYS_OPEN = [
        'legal.*',
        'logout',
        'profile.destroy',
        'support.end',
        'complaints.*',
        'api.v1.me',
        'api.v1.logout',
    ];

    public function __construct(private readonly PendingLegalAcceptances $pending) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The platform panel (Filament + Livewire) is not part of the product.
        if ($user === null || $request->routeIs(self::ALWAYS_OPEN) || $request->is('plataforma', 'plataforma/*', 'livewire*')) {
            return $next($request);
        }

        $signature = $this->pending->currentSignature($user);
        $session = $request->hasSession() ? $request->session() : null;

        if ($session?->get(self::SESSION_KEY) === $signature) {
            return $next($request);
        }

        if ($this->pending->for($user) === []) {
            $session?->put(self::SESSION_KEY, $signature);

            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Antes de seguir, acepta la versión vigente de los Términos y la Política de privacidad.',
                'code' => self::API_CODE,
            ], 403);
        }

        return $request->isMethod('GET')
            ? redirect()->guest(route('legal.acceptance.show'))
            : redirect()->route('legal.acceptance.show');
    }
}
