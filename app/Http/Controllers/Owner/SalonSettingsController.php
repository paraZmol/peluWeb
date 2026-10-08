<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Salon\UpdateSalonDetails;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonTheme;
use App\Http\Requests\Owner\UpdateSalonBookingRequest;
use App\Http\Requests\Owner\UpdateSalonLocationRequest;
use App\Http\Requests\Owner\UpdateSalonProfileRequest;
use App\Http\Resources\SalonSettingsResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SalonSettingsController extends SalonAreaController
{
    public function edit(): Response
    {
        $salon = $this->salon('update')->load('photos');

        return Inertia::render('owner/salon/edit', [
            'salon' => new SalonSettingsResource($salon),
            'businessTypes' => collect(BusinessType::cases())->map(fn (BusinessType $type) => ['value' => $type->value, 'label' => $type->label()]),
            'slotSteps' => UpdateSalonBookingRequest::SLOT_STEPS,
            'themes' => collect(SalonTheme::cases())->map(fn (SalonTheme $theme) => [
                'value' => $theme->value,
                'label' => $theme->label(),
                'tagline' => $theme->tagline(),
                // The public page as it would look with that template and, where photos are missing, with samples.
                'previewUrl' => route('salons.show', ['salon' => $salon->slug, 'diseno' => $theme->value, 'muestra' => 1]),
            ]),
            'canManageBookingRules' => request()->user()?->can('manageBookingRules', $salon) ?? false,
            'clienteles' => collect(Clientele::cases())->map(fn (Clientele $clientele) => ['value' => $clientele->value, 'label' => $clientele->label()]),
        ]);
    }

    public function updateProfile(UpdateSalonProfileRequest $request, UpdateSalonDetails $update): RedirectResponse
    {
        $update->handle($this->salon('update'), $request->validated());
        $this->toast('Datos del salón guardados.');

        return to_route('salon.edit');
    }

    public function updateBooking(UpdateSalonBookingRequest $request, UpdateSalonDetails $update): RedirectResponse
    {
        $update->handle($this->salon('manageBookingRules'), $request->validated());
        $this->toast('Reglas de reserva guardadas.');

        return to_route('salon.edit');
    }

    public function updateLocation(UpdateSalonLocationRequest $request, UpdateSalonDetails $update): RedirectResponse
    {
        $update->handle($this->salon('update'), $request->validated());
        $this->toast('Ubicación guardada.');

        return to_route('salon.edit');
    }
}
