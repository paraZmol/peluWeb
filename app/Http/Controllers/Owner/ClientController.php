<?php

namespace App\Http\Controllers\Owner;

use App\Models\SalonClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The salon's clients and who among them is VIP. `{client}` is filtered by
 * the current salon (BelongsToSalon), so another salon's client is a 404.
 */
class ClientController extends SalonAreaController
{
    private const int PAGE_SIZE = 50;

    public function index(Request $request): Response
    {
        $this->salon('manageVipClients');
        $search = $request->string('buscar')->trim()->limit(60, '')->toString();
        // "%" and "_" typed by the user are text to find, not wildcards.
        $like = '%'.addcslashes($search, '%_\\').'%';

        $clients = SalonClient::query()
            ->withCount('bookings')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('name', $like)
                ->orWhereLike('phone', $like)))
            ->orderByDesc('is_vip')
            ->orderBy('name')
            ->limit(self::PAGE_SIZE)
            ->get();

        return Inertia::render('owner/clients/index', [
            'clients' => $clients->map(fn (SalonClient $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'isVip' => $client->is_vip,
                'hasAccount' => $client->user_id !== null,
                'bookingsCount' => (int) $client->getAttribute('bookings_count'),
                'noShows' => $client->no_show_count,
            ]),
            'search' => $search,
            'isTruncated' => $clients->count() === self::PAGE_SIZE,
        ]);
    }

    public function updateVip(Request $request, SalonClient $client): RedirectResponse
    {
        $salon = $this->salon('manageVipClients');
        // Nobody grants VIP hours and VIP deposits to themselves, except an owner.
        abort_if($client->user_id === $request->user()?->id && ! $request->user()?->can('manageTeam', $salon), 403);

        $client->is_vip = $request->boolean('is_vip');
        $client->save();
        $this->toast($client->is_vip ? "{$client->name} ahora es VIP." : "{$client->name} ya no es VIP.");

        return back();
    }
}
