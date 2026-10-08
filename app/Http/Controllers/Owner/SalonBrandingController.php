<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Salon\UpdateSalonBranding;
use App\Http\Requests\Owner\SalonImageRequest;
use App\Http\Requests\Owner\UpdateSalonThemeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalonBrandingController extends SalonAreaController
{
    public function __construct(private readonly UpdateSalonBranding $branding) {}

    public function storeLogo(SalonImageRequest $request): RedirectResponse
    {
        $this->branding->setLogo($this->salon('update'), $request->file('image'));

        return $this->saved('Logo actualizado.');
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->branding->removeImage($this->salon('update'), 'logo_path');

        return $this->saved('Logo eliminado.');
    }

    public function storeCover(SalonImageRequest $request): RedirectResponse
    {
        $this->branding->setCover($this->salon('update'), $request->file('image'));

        return $this->saved('Portada actualizada.');
    }

    public function destroyCover(): RedirectResponse
    {
        $this->branding->removeImage($this->salon('update'), 'cover_path');

        return $this->saved('Portada eliminada.');
    }

    public function updateColor(Request $request): RedirectResponse
    {
        $validated = $request->validate(['brand_color' => ['nullable', 'string', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/']]);

        $adjusted = $this->branding->setColor($this->salon('update'), $validated['brand_color'] ?? null);

        return $this->saved($adjusted
            ? 'Color guardado. Lo ajustamos un poco para que se lea bien en modo claro y oscuro.'
            : 'Color guardado.');
    }

    public function updateTheme(UpdateSalonThemeRequest $request): RedirectResponse
    {
        $this->branding->setTheme($this->salon('update'), $request->theme());

        return $this->saved('Diseño guardado.');
    }

    private function saved(string $message): RedirectResponse
    {
        $this->toast($message);

        return to_route('salon.edit');
    }
}
