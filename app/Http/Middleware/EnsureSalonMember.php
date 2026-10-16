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
    /**
     * What a paused salon may still do (PLAN 7.3): the bookings it already
     * took are respected, so a deposit a client already paid can be
     * confirmed or given back, and a booking called off or marked missed.
     */
    private const array ALLOWED_WHILE_PAUSED = [
        'agenda.bookings.confirm',
        'agenda.bookings.refund-deposit',
        'agenda.bookings.no-show',
        'agenda.bookings.cancel',
    ];

    /** Files with the salon's or third parties' data that support never downloads. */
    private const array HIDDEN_FROM_SUPPORT = ['reports.export', 'expenses.receipt'];

    public function __construct(private readonly CurrentSalon $currentSalon) {}

    public function handle(Request $request, Closure $next): Response
    {
        $salon = $this->currentSalon->get();

        if ($salon === null) {
            if ($request->isMethod('GET')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'Esa pantalla es de un salón y tu cuenta no pertenece a ninguno.']);

                return redirect()->route('salons.create');
            }

            abort(403);
        }

        // Support looks, it does not take: no downloads of the cash or of receipts.
        if ($this->currentSalon->supportVisit() !== null && $request->routeIs(self::HIDDEN_FROM_SUPPORT)) {
            abort(403);
        }

        if (! $request->isMethodSafe() && $this->currentSalon->supportVisit() !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Estás en modo soporte: puedes ver todo, pero no hacer cambios.']);

            return back(status: 303);
        }

        if (! $request->isMethodSafe() && ! $salon->isWritable() && ! $request->routeIs(self::ALLOWED_WHILE_PAUSED)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Tu salón está en pausa. Renueva tu plan para seguir haciendo cambios.',
            ]);

            return back(status: 303);
        }

        return $next($request);
    }
}
