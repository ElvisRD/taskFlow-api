<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    /**
     * Register a new user and issue an API token.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(fn (): User => User::create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->lower()->trim()->toString(),
            'password' => $request->string('password')->toString(),
        ]));

        $token = $user->createToken($request->string('device_name', 'web')->toString());

        return response()->json([
            'data' => new UserResource($user),
            'token' => $token->plainTextToken,
        ], 201);
    }
}
