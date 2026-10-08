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
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        // The same answer for an unknown account and a wrong password.
        if ($user === null || ! Hash::check($request->string('password')->toString(), (string) $user->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }

        // A second factor cannot be asked for here yet: those accounts
        // sign in on the web, where it is.
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['email' => 'Tu cuenta usa verificación en dos pasos. Por ahora entra desde la web.']);
        }

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
            'user' => $this->profile($user),
        ], 201);
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
