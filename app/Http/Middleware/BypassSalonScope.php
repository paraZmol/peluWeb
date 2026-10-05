<?php

namespace App\Http\Middleware;

use App\Support\CurrentSalon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform panel only: the super admin works across every salon. Runs after
 * authentication and the 2FA check, so only verified platform admins get here.
 */
class BypassSalonScope
{
    public function __construct(private readonly CurrentSalon $currentSalon) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentSalon->bypass();

        return $next($request);
    }
}
