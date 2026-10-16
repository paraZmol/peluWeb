<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\SupportVisits;
use App\Filament\Resources\Salons\SalonResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Salir del modo soporte": ends the visit and goes back to the salon in
 * the platform panel. Outside the salon middleware on purpose, so it works
 * while every change in the salon is refused.
 */
class SupportVisitController extends Controller
{
    public function destroy(Request $request, SupportVisits $support): Response
    {
        $visit = $support->end($request->session());

        // The panel is Filament, not Inertia: a full page load, not a visit.
        return $visit === null
            ? to_route('dashboard')
            : Inertia::location(SalonResource::getUrl('view', ['record' => $visit->salon_id], panel: 'plataforma'));
    }
}
