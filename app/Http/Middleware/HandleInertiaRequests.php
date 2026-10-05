<?php

namespace App\Http\Middleware;

use App\Enums\SalonPermission;
use App\Models\SalonMember;
use App\Support\CurrentSalon;
use Illuminate\Http\Request;
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
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ...$this->salonProps($request),
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
}
