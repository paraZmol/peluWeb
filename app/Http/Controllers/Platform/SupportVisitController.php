<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\SupportVisits;
use App\Filament\Resources\Salons\SalonResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Salir del modo soporte": ends the visit and goes back to the salon in
 * the platform panel. Outside the salon middleware on purpose, so it works
 * while every change in the salon is refused.
 */
class SupportVisitController extends Controller
{
    public function destroy(Request $request, SupportVisits $support): RedirectResponse
    {
        $visit = $support->end($request->session());

        return $visit === null
            ? to_route('dashboard')
            : redirect(SalonResource::getUrl('view', ['record' => $visit->salon_id], panel: 'plataforma'));
    }
}
