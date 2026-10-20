<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Authenticate the user and issue an API token.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $email = $request->string('email')->lower()->trim()->toString();

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null || $user->password === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($request->string('device_name', 'web')->toString());

        return response()->json([
            'data' => new UserResource($user),
            'token' => $token->plainTextToken,
        ]);
    }
}
