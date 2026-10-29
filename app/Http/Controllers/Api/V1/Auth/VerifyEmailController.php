<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): JsonResponse|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->invalidLink($request);
        }

        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->invalidLink($request);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        if (! $request->expectsJson()) {
            return redirect()->away($this->frontendUrl().'/email/verified');
        }

        return response()->json([
            'message' => 'Correo electrónico verificado correctamente.',
        ]);
    }

    protected function invalidLink(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->away($this->frontendUrl().'/email/verified?status=invalid');
        }

        return response()->json([
            'message' => 'El enlace de verificación no es válido o ha expirado.',
        ], 403);
    }

    protected function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
