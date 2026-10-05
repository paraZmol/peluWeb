<?php

namespace App\Http\Middleware;

use App\Support\CurrentSalon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards salon-area routes: without a current salon the BelongsToSalon
 * scope would not filter anything, so the request must not go through.
 */
class EnsureSalonMember
{
    public function __construct(private readonly CurrentSalon $currentSalon) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentSalon->has()) {
            if ($request->isMethod('GET')) {
                return redirect()->route('salons.create');
            }

            abort(403);
        }

        return $next($request);
    }
}
