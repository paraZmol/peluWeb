<?php

namespace App\Http\Controllers;

use App\Actions\Salon\SalonSetupChecklist;
use App\Support\CurrentSalon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentSalon $currentSalon, SalonSetupChecklist $checklist): Response
    {
        $salon = $currentSalon->get();
        $user = $request->user();
        $canEdit = $salon !== null && $user?->can('update', $salon);

        return Inertia::render('dashboard', [
            'setup' => $canEdit ? $checklist->handle($salon) : null,
            'publicUrl' => $salon === null ? null : route('salons.show', $salon->slug),
            // Here only, never with the props every page shares: who runs the
            // platform gets the way into its panel. /plataforma checks again.
            'platform' => $user?->is_platform_admin === true ? ['hasTwoFactor' => $user->hasTwoFactorEnabled()] : null,
        ]);
    }
}
