<?php

namespace App\Http\Middleware;

use App\Enums\SalonPermission;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Models\SupportVisit;
use App\Support\CurrentSalon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->userProps($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'features' => [
                'googleLogin' => GoogleLoginController::isEnabled(),
                // The public half of the push keys; null hides the switch.
                'pushKey' => config('services.webpush.private_key') ? config('services.webpush.public_key') : null,
            ],
            ...$this->salonProps($request),
            'support' => $this->supportProps(),
            'legal' => $this->legalProps(),
        ];
    }

    /**
     * Who provides the service and where to complain, for the legal footer
     * of every page. Empty values stay null: the footer leaves them out.
     *
     * @return array{providerName: string|null, ruc: string|null, contactEmail: string|null, complaintsUrl: string|null}
     */
    private function legalProps(): array
    {
        return [
            'providerName' => config('legal.provider.name') ?: null,
            'ruc' => config('legal.provider.ruc') ?: null,
            'contactEmail' => config('legal.contact.email') ?: null,
            // The Libro de Reclamaciones has its own route; never a broken link.
            'complaintsUrl' => Route::has('complaints.create') ? route('complaints.create') : null,
        ];
    }

    /**
     * An explicit allow-list: the whole User model (with loaded relations,
     * flags and 2FA timestamps) must never reach the page or browser history.
     *
     * @return array<string, mixed>|null
     */
    private function userProps(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'booking_reminders' => $user->booking_reminders,
            'avatar' => $user->avatar,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The current salon, the user's role and permissions in it, and the list
     * of salons they can switch to. Permissions here only drive the UI;
     * every action is authorized again on the server.
     *
     * @return array{currentSalon: array<string, mixed>|null, salons: array<int, array<string, mixed>>}
     */
    private function salonProps(Request $request): array
    {
        $user = $request->user();
        $salon = app(CurrentSalon::class)->get();

        if ($user === null) {
            return ['currentSalon' => null, 'salons' => []];
        }

        $membership = $salon !== null ? $user->membershipIn($salon) : null;

        return [
            'currentSalon' => $salon === null || $membership === null ? null : [
                'id' => $salon->id,
                'name' => $salon->name,
                'slug' => $salon->slug,
                'role' => $membership->role->value,
                'brand' => $salon->brandTokens(),
                // Drives the "Mi horario" menu entry: the user attends clients here.
                'hasStaffProfile' => Staff::query()->where('user_id', $user->id)->exists(),
                // The team keeps a line of people without a booking: shows its menu entry.
                'walkInsEnabled' => $salon->walk_ins_enabled,
                // Drives the notice about paying (owners) or the salon being paused (everyone).
                'subscription' => $this->subscription($salon),
                'permissions' => collect(SalonPermission::cases())
                    ->filter(fn (SalonPermission $permission) => $membership->hasPermission($permission))
                    ->map(fn (SalonPermission $permission) => $permission->value)
                    ->values()
                    ->all(),
            ],
            'salons' => $user->memberships
                ->filter(fn (SalonMember $member) => $member->salon !== null)
                ->map(fn (SalonMember $member) => [
                    'id' => $member->salon_id,
                    'name' => $member->salon->name,
                    'role' => $member->role->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{state: string, daysLeft: int|null, graceEndsOn: string|null}
     */
    private function subscription(Salon $salon): array
    {
        $standing = $salon->standing();

        return [
            'state' => $standing->state->value,
            'daysLeft' => $standing->daysLeft,
            'graceEndsOn' => $standing->coveredUntil?->addDays((int) config('billing.grace_days'))->toDateString(),
        ];
    }

    /**
     * The platform admin looking at a salon for support: drives the band
     * that says so and the way out.
     *
     * @return array{salonName: string, endsAt: string}|null
     */
    private function supportProps(): ?array
    {
        $visit = app(CurrentSalon::class)->supportVisit();

        return $visit === null ? null : [
            'salonName' => $visit->salon->name,
            'endsAt' => $visit->started_at->addMinutes(SupportVisit::MAX_MINUTES)->toIso8601String(),
        ];
    }
}
