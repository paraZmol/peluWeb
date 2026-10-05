<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profile\UpdateAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AvatarController extends Controller
{
    public function store(AvatarRequest $request, UpdateAvatar $updateAvatar): RedirectResponse
    {
        $updateAvatar->handle($request->user(), $request->file('avatar'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto actualizada.']);

        return to_route('profile.edit');
    }

    public function destroy(Request $request, UpdateAvatar $updateAvatar): RedirectResponse
    {
        $updateAvatar->remove($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto eliminada.']);

        return to_route('profile.edit');
    }
}
