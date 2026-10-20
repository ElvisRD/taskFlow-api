<?php

namespace App\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleTokenInfoVerifier implements GoogleTokenVerifier
{
    private const ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(private string $clientId) {}

    public function verify(string $idToken): GoogleIdentity
    {
        if ($this->clientId === '') {
            throw new InvalidGoogleIdentityToken('El inicio de sesión con Google no está configurado.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(self::ENDPOINT, ['id_token' => $idToken]);
        } catch (ConnectionException $exception) {
            throw new InvalidGoogleIdentityToken(
                'No se pudo contactar con Google para validar la sesión.',
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw new InvalidGoogleIdentityToken('El token de Google no es válido o ha caducado.');
        }

        $claims = $response->json() ?? [];

        $this->assertClaimsAreTrustworthy($claims);

        return new GoogleIdentity(
            id: (string) $claims['sub'],
            email: Str::lower((string) $claims['email']),
            name: (string) ($claims['name'] ?? $claims['email']),
            avatar: isset($claims['picture']) ? (string) $claims['picture'] : null,
        );
    }

    private function assertClaimsAreTrustworthy(array $claims): void
    {
        if (! in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new InvalidGoogleIdentityToken('El emisor del token de Google no es válido.');
        }

        if (! hash_equals($this->clientId, (string) ($claims['aud'] ?? ''))) {
            throw new InvalidGoogleIdentityToken('El token de Google no corresponde a esta aplicación.');
        }

        if (! filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new InvalidGoogleIdentityToken('Tu cuenta de Google no tiene el correo verificado.');
        }

        if (empty($claims['sub']) || empty($claims['email'])) {
            throw new InvalidGoogleIdentityToken('Google no devolvió los datos necesarios.');
        }

        if (isset($claims['exp']) && (int) $claims['exp'] < now()->getTimestamp()) {
            throw new InvalidGoogleIdentityToken('El token de Google ha caducado.');
        }
    }
}
