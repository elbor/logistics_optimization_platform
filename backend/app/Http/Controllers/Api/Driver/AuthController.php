<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\LoginRequest;
use App\Http\Resources\DriverResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Вхід водія в застосунок: токени Sanctum (SPEC 7.2).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): DriverResource
    {
        $user = $request->authenticate();

        $minutes = config('sanctum.expiration');
        $token = $user->createToken(
            $request->validated('device_name'),
            expiresAt: $minutes ? now()->addMinutes($minutes) : null,
        );

        return DriverResource::make($user)->additional([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601ZuluString(),
        ]);
    }

    /**
     * Відкликає лише токен цього пристрою.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): DriverResource
    {
        return DriverResource::make($request->user());
    }
}
