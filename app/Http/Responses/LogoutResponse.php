<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leaving also throws away the key of the encrypted pages kept in the
 * browser's history, so "Back" on a shared computer cannot bring a
 * client's file back.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        Inertia::clearHistory();

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : new RedirectResponse(Fortify::redirects('logout', '/'));
    }
}
