<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * What a phone needs to install the site as an app: the platform's own
 * manifest, and one per salon so that installing from a salon's page puts
 * that salon's name and logo on the home screen.
 */
class ManifestController extends Controller
{
    /** The sizes a manifest asks for; anything else is a 404. */
    public const array ICON_SIZES = [192, 512];

    private const string BACKGROUND = '#0b0f14';

    private const string PLATFORM_COLOR = '#8f5e45';

    private const int SHORT_NAME_LENGTH = 12;

    public function platform(): JsonResponse
    {
        return $this->manifest([
            'id' => '/',
            'name' => config('app.name'),
            'short_name' => config('app.name'),
            'description' => 'Reserva en tu peluquería, barbería o salón.',
            'start_url' => route('dashboard', absolute: false),
            'theme_color' => self::PLATFORM_COLOR,
            'icons' => [
                $this->iconEntry('/icons/icon-192.png', 192),
                $this->iconEntry('/icons/icon-512.png', 512),
                $this->iconEntry('/icons/icon-maskable-512.png', 512, 'maskable'),
            ],
        ]);
    }

    public function salon(Salon $salon): JsonResponse
    {
        $page = route('salons.show', $salon, absolute: false);

        return $this->manifest([
            'id' => $page,
            'name' => $salon->name,
            'short_name' => mb_substr($salon->name, 0, self::SHORT_NAME_LENGTH),
            'description' => "Reserva tu cita en {$salon->name}.",
            'start_url' => $page,
            'theme_color' => $salon->brandTokens()['light']['primary'] ?? self::PLATFORM_COLOR,
            'icons' => array_map(
                fn (int $size): array => $this->iconEntry(route('salons.icon', [$salon, $size], absolute: false), $size),
                self::ICON_SIZES,
            ),
        ]);
    }

    /**
     * The salon's logo as a square PNG (phones want PNG, logos are kept as
     * WebP); the platform's icon while the salon has none.
     */
    public function icon(Salon $salon, int $size): Response|RedirectResponse
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        $logo = $salon->logo_path === null ? null : Storage::disk('public')->get($salon->logo_path);

        if ($logo === null) {
            return redirect("/icons/icon-{$size}.png");
        }

        try {
            $png = (string) (new ImageManager(new Driver))->read($logo)->cover($size, $size)->toPng();
        } catch (Throwable) {
            return redirect("/icons/icon-{$size}.png");
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    /**
     * @param  array<string, mixed>  $own
     */
    private function manifest(array $own): JsonResponse
    {
        return response()->json([
            ...$own,
            'lang' => 'es',
            // The whole site: an installed salon also opens "Mis reservas".
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => self::BACKGROUND,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{src: string, sizes: string, type: string, purpose: string}
     */
    private function iconEntry(string $src, int $size, string $purpose = 'any'): array
    {
        return ['src' => $src, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => $purpose];
    }
}
