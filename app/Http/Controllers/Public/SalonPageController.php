<?php

namespace App\Http\Controllers\Public;

use App\Actions\Promotions\PublicPromotions;
use App\Actions\Reviews\SalonReviews;
use App\Data\Salon\PublicSalonContent;
use App\Data\Salon\SalonPreview;
use App\Domain\Schedule\TimeRange;
use App\Enums\SalonStatus;
use App\Enums\SalonTheme;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Resources\PublicSalonResource;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\SalonPhoto;
use App\Models\Service;
use App\Models\Staff;
use App\Support\Images\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SalonPageController extends Controller
{
    /**
     * Public profile. Visitors have no current salon, so the salon scope is
     * bypassed explicitly and replaced by `where salon_id = this salon`.
     */
    public function show(Request $request, Salon $salon): Response
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $content = new PublicSalonContent(
            services: Service::withoutGlobalScope('salon')
                ->where('salon_id', $salon->id)
                ->where('is_active', true)
                ->where('is_addon', false)
                ->with('variants')
                ->orderBy('position')
                ->get(),
            photos: SalonPhoto::withoutGlobalScope('salon')->where('salon_id', $salon->id)->orderBy('position')->get(),
            team: Staff::withoutGlobalScope('salon')
                ->where('salon_id', $salon->id)
                ->where('is_active', true)
                ->with('user')
                ->orderBy('position')
                ->get(),
            openingHours: $this->openingHours($salon),
        );

        $preview = $this->preview($request, $salon);

        // "Mi salón" shows the preview inside a frame of this same site.
        if ($preview !== null) {
            $request->attributes->set(SecurityHeaders::FRAMEABLE_BY_SELF, true);
        }

        return Inertia::render('public/salon', [
            'salon' => new PublicSalonResource($salon, $content, $preview),
            // Null unless the salon shows its reviews.
            'reviews' => app(SalonReviews::class)->forPublic($salon),
            // What it is running today; `shared` when the visitor came by a promotion's link.
            'promotions' => app(PublicPromotions::class)->handle($salon, $request->filled('promo') ? $request->string('promo')->limit(16, '')->toString() : null),
        ])->withViewData(['meta' => $this->meta($salon), ...self::installData($salon)]);
    }

    /**
     * The link a salon shares for one promotion: its page, with that
     * promotion pointed at. The page decides what to say when it is over.
     */
    public function promotion(Salon $salon, string $code): RedirectResponse
    {
        return to_route('salons.show', ['salon' => $salon->slug, 'promo' => $code]);
    }

    /**
     * Installing from a salon's page puts that salon on the home screen.
     *
     * @return array{manifest: string, appIcon: string, appTitle: string}
     */
    public static function installData(Salon $salon): array
    {
        return [
            'manifest' => route('salons.manifest', $salon, absolute: false),
            'appIcon' => route('salons.icon', [$salon, 192], absolute: false),
            'appTitle' => $salon->name,
        ];
    }

    /**
     * "How would it look with that template / with photos": only for
     * whoever manages the salon. Visitors always get the saved template.
     */
    private function preview(Request $request, Salon $salon): ?SalonPreview
    {
        if (! $request->hasAny(['diseno', 'muestra']) || ! ($request->user()?->can('update', $salon) ?? false)) {
            return null;
        }

        return new SalonPreview(
            theme: SalonTheme::tryFrom($request->string('diseno')->toString()) ?? $salon->theme,
            withSamples: $request->boolean('muestra'),
        );
    }

    /**
     * Weekly opening hours and whether the salon is open right now, on the
     * salon's own clock. Null until some professional has working hours.
     *
     * @return array<string, mixed>|null
     */
    private function openingHours(Salon $salon): ?array
    {
        $hours = $salon->openingHours();

        if ($hours->isEmpty()) {
            return null;
        }

        $now = $salon->localNow();
        $holidays = SalonHoliday::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereDate('date', '>=', $now->toDateString())
            ->get();

        $status = $hours->statusAt(
            $now,
            $holidays->map(fn (SalonHoliday $holiday): string => $holiday->date->format('Y-m-d'))->values()->all(),
        );

        return [
            'days' => collect($hours->days())->map(fn (array $ranges, int $weekday) => [
                'weekday' => $weekday,
                'ranges' => array_map(fn (TimeRange $range) => ['start' => $range->start(), 'end' => $range->end()], $ranges),
            ])->values(),
            'today' => $now->dayOfWeekIso,
            'closedToday' => $holidays->first(fn (SalonHoliday $holiday) => $holiday->date->format('Y-m-d') === $now->toDateString())?->name,
            'status' => [
                'isOpen' => $status->isOpen,
                'time' => $status->time,
                'daysAhead' => $status->daysAhead,
                'weekday' => $status->weekday,
            ],
        ];
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
