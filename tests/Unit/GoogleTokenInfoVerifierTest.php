<?php

namespace Tests\Unit;

use App\Auth\GoogleTokenInfoVerifier;
use App\Auth\InvalidGoogleIdentityToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleTokenInfoVerifierTest extends TestCase
{
    public function test_it_returns_the_identity_for_a_valid_token(): void
    {
        Http::fake(['*' => Http::response($this->claims(), 200)]);

        $identity = (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');

        $this->assertSame('google-123', $identity->id);
        $this->assertSame('ana@example.com', $identity->email);
        $this->assertSame('Ana Propietaria', $identity->name);
        $this->assertSame('https://example.com/avatar.png', $identity->avatar);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'id_token=id-token'));
    }

    public function test_it_rejects_a_token_issued_for_another_client(): void
    {
        Http::fake(['*' => Http::response($this->claims(['aud' => 'otro-client']), 200)]);

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');
    }

    public function test_it_rejects_a_token_from_an_unknown_issuer(): void
    {
        Http::fake(['*' => Http::response($this->claims(['iss' => 'evil.example.com']), 200)]);

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');
    }

    public function test_it_rejects_a_token_with_an_unverified_email(): void
    {
        Http::fake(['*' => Http::response($this->claims(['email_verified' => 'false']), 200)]);

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');
    }

    public function test_it_rejects_an_expired_token(): void
    {
        Http::fake(['*' => Http::response($this->claims(['exp' => now()->subMinute()->getTimestamp()]), 200)]);

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');
    }

    public function test_it_rejects_a_token_google_does_not_recognise(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 400)]);

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');
    }

    public function test_it_rejects_everything_when_no_client_id_is_configured(): void
    {
        Http::fake();

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier(''))->verify('id-token');

        Http::assertNothingSent();
    }

    public function test_it_reports_a_connection_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(InvalidGoogleIdentityToken::class);

        (new GoogleTokenInfoVerifier('client-123'))->verify('id-token');
    }

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'sub' => 'google-123',
            'email' => 'ANA@example.com',
            'email_verified' => 'true',
            'name' => 'Ana Propietaria',
            'picture' => 'https://example.com/avatar.png',
            'exp' => now()->addHour()->getTimestamp(),
        ], $overrides);
    }
}
