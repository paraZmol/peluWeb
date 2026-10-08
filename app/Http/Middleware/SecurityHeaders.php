<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every page, including /plataforma.
 * Framing is forbidden so no page can be used for clickjacking; the one
 * exception is a page that asks to be framed by this same site (the
 * template preview inside "Mi salón"), never by anyone else.
 */
class SecurityHeaders
{
    /** Set on the request by a controller that renders a same-origin preview. */
    public const string FRAMEABLE_BY_SELF = 'frameable-by-self';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->attributes->get(self::FRAMEABLE_BY_SELF) === true) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        } else {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
