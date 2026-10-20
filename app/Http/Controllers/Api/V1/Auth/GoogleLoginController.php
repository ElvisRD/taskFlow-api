<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\GoogleIdentity;
use App\Auth\GoogleTokenVerifier;
use App\Auth\InvalidGoogleIdentityToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleLoginController extends Controller
{
    public function __construct(private GoogleTokenVerifier $verifier) {}

    /**
     * Authenticate a user with a Google ID token and issue an API token.
     */
    public function __invoke(GoogleLoginRequest $request): JsonResponse
    {
        try {
            $identity = $this->verifier->verify($request->string('credential')->toString());
        } catch (InvalidGoogleIdentityToken $exception) {
            throw ValidationException::withMessages([
                'credential' => [$exception->getMessage()],
            ]);
        }

        [$user, $created] = $this->resolveUser($identity);

        $token = $user->createToken($request->string('device_name', 'google')->toString());

        return response()->json([
            'data' => new UserResource($user),
            'token' => $token->plainTextToken,
            'created' => $created,
        ], $created ? 201 : 200);
    }

    /**
     * Find the user linked to the Google account, link it to an existing
     * account with the same email, or create a brand new one.
     *
     * @return array{0: User, 1: bool}
     */
    private function resolveUser(GoogleIdentity $identity): array
    {
        $email = Str::lower($identity->email);

        return DB::transaction(function () use ($identity, $email): array {
            $user = User::query()->where('google_id', $identity->id)->first();

            if ($user !== null) {
                $user->forceFill(['avatar' => $identity->avatar])->save();

                return [$user, false];
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user !== null) {
                $user->forceFill([
                    'google_id' => $identity->id,
                    'avatar' => $identity->avatar,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return [$user, false];
            }

            $user = new User([
                'name' => $identity->name,
                'email' => $email,
                'google_id' => $identity->id,
                'avatar' => $identity->avatar,
            ]);

            // Google ya ha verificado el correo por nosotros.
            $user->email_verified_at = now();
            $user->save();

            return [$user, true];
        });
    }
}
