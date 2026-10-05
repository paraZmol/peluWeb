<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify only throttles login and two-factor. Registration and password
 * reset requests are throttled here so emails cannot be enumerated or
 * pre-registered in bulk.
 */
class ThrottleAccountEndpoints
{
    /** @var array<string, int> path => attempts per minute per IP */
    private const array LIMITS = [
        'register' => 5,
        'forgot-password' => 3,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');

        if (! $request->isMethod('POST') || ! array_key_exists($path, self::LIMITS)) {
            return $next($request);
        }

        $key = "account:{$path}:".$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::LIMITS[$path])) {
            abort(429, 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
