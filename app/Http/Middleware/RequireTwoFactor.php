<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform panel can suspend any salon, so a stolen password alone must
 * not be enough: two-factor authentication has to be confirmed first.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('security.edit')
                ->with('status', 'Activa la verificación en dos pasos para entrar al panel de la plataforma.');
        }

        return $next($request);
    }
}
