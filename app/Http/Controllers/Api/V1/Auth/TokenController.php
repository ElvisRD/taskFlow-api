<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();

        $tokens = $request->user()->tokens()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->getKey(),
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
                'is_current' => $currentToken instanceof PersonalAccessToken
                    && $currentToken->is($token),
            ]);

        return response()->json(['data' => $tokens]);
    }

    public function destroy(Request $request, PersonalAccessToken $token): Response
    {
        abort_unless(
            $token->tokenable_type === $request->user()::class
                && (int) $token->tokenable_id === (int) $request->user()->getKey(),
            404,
        );

        $token->delete();

        return response()->noContent();
    }

    public function destroyOthers(Request $request): Response
    {
        $currentToken = $request->user()->currentAccessToken();

        $query = $request->user()->tokens();

        if ($currentToken instanceof PersonalAccessToken) {
            $query->whereKeyNot($currentToken->getKey());
        }

        $query->delete();

        return response()->noContent();
    }
}
