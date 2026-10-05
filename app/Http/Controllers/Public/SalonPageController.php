<?php

namespace App\Http\Controllers\Public;

use App\Enums\SalonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSalonResource;
use App\Models\Salon;
use App\Models\SalonPhoto;
use App\Models\Service;
use App\Support\Images\ImageStore;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SalonPageController extends Controller
{
    /**
     * Public profile. Visitors have no current salon, so the salon scope is
     * bypassed explicitly and replaced by `where salon_id = this salon`.
     */
    public function show(Salon $salon): Response
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $services = Service::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->where('is_addon', false)
            ->with('variants')
            ->orderBy('position')
            ->get();

        $photos = SalonPhoto::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->orderBy('position')
            ->get();

        return Inertia::render('public/salon', [
            'salon' => new PublicSalonResource($salon, $services, $photos),
        ])->withViewData(['meta' => $this->meta($salon)]);
    }

    /**
     * Server-rendered tags for search engines and WhatsApp/Facebook link
     * previews, which do not run JavaScript.
     *
     * @return array<string, string|null>
     */
    private function meta(Salon $salon): array
    {
        $images = app(ImageStore::class);
        $image = $images->url($salon->cover_path ?? $salon->logo_path);
        $place = collect([$salon->district, $salon->city])->filter()->join(', ');

        return [
            'title' => "{$salon->name} · {$salon->business_type->label()}".($place !== '' ? " en {$place}" : ''),
            'description' => Str::limit($salon->description ?? "Reserva en {$salon->name} desde tu celular.", 155),
            // Link-preview bots need an absolute URL.
            'image' => $image === null ? null : url($image),
            'url' => route('salons.show', $salon->slug),
        ];
    }
}
