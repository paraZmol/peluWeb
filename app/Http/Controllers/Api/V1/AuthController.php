<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Tokens for the mobile app. A token is the account: it is only handed out
 * for the right password, shown once, and stored hashed.
 */
class AuthController extends Controller
{
    /** Checked when the account does not exist, so both answers take as long. */
    private const string DUMMY_HASH = '$2y$12$e1ccJgqG0vPJj6PiFLSrq.xeA4RGTSL4mGpeYMTgGk8JN83W0TjNG';

    /** Old tokens removed per sign-in; far more than can pile up. */
    private const int SWEEP = 1000;

    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();
        $matches = Hash::check($request->string('password')->toString(), (string) ($user->password ?? self::DUMMY_HASH));

        // The same answer for an unknown account and a wrong password.
        if ($user === null || ! $matches) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }

        $this->ensureMayUseTheApp($user);

        // The oldest devices make room: the list never grows without end.
        $stale = $user->tokens()->orderByDesc('id')->skip(User::MAX_API_TOKENS - 1)->take(self::SWEEP)->pluck('id');
        $user->tokens()->whereKey($stale)->delete();

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
            'user' => $this->profile($user),
        ], 201);
    }

    /**
     * Who gets no token even with the right password.
     */
    private function ensureMayUseTheApp(User $user): void
    {
        $reason = match (true) {
            // Until the email is verified nobody has proved owning it: a
            // token handed out now would outlive the real owner claiming it.
            ! $user->hasVerifiedEmail() => 'Primero verifica tu correo desde la web.',
            // A second factor cannot be asked for here yet.
            $user->two_factor_confirmed_at !== null => 'Tu cuenta usa verificación en dos pasos. Por ahora entra desde la web.',
            // The platform's own account has no business in the client app.
            $user->is_platform_admin => 'Esta cuenta solo entra desde la web.',
            default => null,
        };

        if ($reason !== null) {
            throw ValidationException::withMessages(['email' => $reason]);
        }
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->profile($user)]);
    }

    /**
     * Signs this device out; other devices keep their own token.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->whereKey($user->currentAccessToken()->getKey())->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{name: string, email: string, phone: string|null, emailVerified: bool}
     */
    private function profile(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'emailVerified' => $user->hasVerifiedEmail(),
        ];
    }
}
