<?php

namespace App\Http\Middleware;

use App\Support\CurrentSalon;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards salon-area routes:
 * - without a current salon the request must not go through;
 * - a suspended, past-due or expired-trial salon is read only (the server
 *   enforces it, the UI only explains it).
 */
class EnsureSalonMember
{
    public function __construct(private readonly CurrentSalon $currentSalon) {}

    public function handle(Request $request, Closure $next): Response
    {
        $salon = $this->currentSalon->get();

        if ($salon === null) {
            if ($request->isMethod('GET')) {
                return redirect()->route('salons.create');
            }

            abort(403);
        }

        if (! $request->isMethodSafe() && ! $salon->isWritable()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Tu salón está en pausa. Renueva tu plan para seguir haciendo cambios.',
            ]);

            return back(status: 303);
        }

        return $next($request);
    }
}
