<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Auth\GoogleIdentity;
use App\Auth\GoogleTokenVerifier;
use App\Auth\InvalidGoogleIdentityToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_creates_a_user_and_returns_a_token(): void
    {
        $this->fakeIdentity(new GoogleIdentity(
            id: 'google-123',
            email: 'nueva@example.com',
            name: 'Nueva Persona',
            avatar: 'https://example.com/avatar.png',
        ));

        $response = $this->postJson('/api/v1/auth/google', [
            'credential' => 'valid-credential',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'avatar'], 'token', 'created'])
            ->assertJsonPath('data.email', 'nueva@example.com')
            ->assertJsonPath('data.avatar', 'https://example.com/avatar.png')
            ->assertJsonPath('created', true);

        $this->assertDatabaseHas('users', [
            'email' => 'nueva@example.com',
            'google_id' => 'google-123',
        ]);

        $this->assertNull(User::query()->where('email', 'nueva@example.com')->value('password'));

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'phpunit']);
    }

    public function test_google_login_lowercases_the_email_and_uses_it_as_fallback_name(): void
    {
        $this->fakeIdentity(new GoogleIdentity(
            id: 'google-321',
            email: 'mixto@example.com',
            name: 'mixto@example.com',
        ));

        $this->postJson('/api/v1/auth/google', ['credential' => 'valid-credential'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'mixto@example.com');
    }

    public function test_google_login_returns_the_user_already_linked_to_the_google_account(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'google_id' => 'google-123',
            'avatar' => 'https://example.com/old.png',
        ]);

        $this->fakeIdentity(new GoogleIdentity(
            id: 'google-123',
            email: 'ana@example.com',
            name: 'Ana Propietaria',
            avatar: 'https://example.com/new.png',
        ));

        $this->postJson('/api/v1/auth/google', ['credential' => 'valid-credential'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('created', false);

        $this->assertSame('https://example.com/new.png', $user->refresh()->avatar);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_login_links_an_existing_account_with_the_same_email(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'google_id' => null,
        ]);

        $this->fakeIdentity(new GoogleIdentity(
            id: 'google-999',
            email: 'ANA@example.com',
            name: 'Ana Propietaria',
        ));

        $this->postJson('/api/v1/auth/google', ['credential' => 'valid-credential'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('created', false);

        $this->assertSame('google-999', $user->refresh()->google_id);
        $this->assertNotNull($user->password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_login_rejects_an_invalid_token(): void
    {
        $this->mock(GoogleTokenVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->once()->andThrow(
                new InvalidGoogleIdentityToken('El token de Google no es válido o ha caducado.'),
            );
        });

        $this->postJson('/api/v1/auth/google', ['credential' => 'broken-credential'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_login_requires_a_credential(): void
    {
        $this->postJson('/api/v1/auth/google', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');
    }

    public function test_google_login_is_throttled_after_ten_attempts(): void
    {
        $this->mock(GoogleTokenVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->andThrow(
                new InvalidGoogleIdentityToken('El token de Google no es válido o ha caducado.'),
            );
        });

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/google', ['credential' => 'broken-credential'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/google', ['credential' => 'broken-credential'])
            ->assertTooManyRequests();
    }

    private function fakeIdentity(GoogleIdentity $identity): void
    {
        $this->mock(GoogleTokenVerifier::class, function (MockInterface $mock) use ($identity): void {
            $mock->shouldReceive('verify')->once()->andReturn($identity);
        });
    }
}
