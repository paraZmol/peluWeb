<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Platform resources do not use the app's model policies (those describe
 * what salon owners may do). Here only the platform admin may act, and only
 * through the actions each resource explicitly allows.
 */
trait AuthorizesPlatformAdmin
{
    /**
     * @return list<string>
     */
    abstract protected static function allowedActions(): array;

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;
        $user = auth()->user();

        $allowed = $user instanceof User
            && $user->is_platform_admin
            && in_array($action, static::allowedActions(), true);

        return $allowed ? Response::allow() : Response::deny();
    }
}
