<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For background lookups (free times, client suggestions) that the page
 * fires while a form is being sent.
 *
 * Validation errors and toasts travel in the session as "flash" data that
 * lives for exactly one more request. If a lookup lands between a form's
 * POST and the redirect that follows it, the lookup would be that "one more
 * request" and the page would come back with no error at all. Lookups pass
 * the flash data on untouched instead.
 */
class KeepFlashedData
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        return $response;
    }
}
