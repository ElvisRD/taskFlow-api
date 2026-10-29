<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_user_tokens_and_marks_the_current_one(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');
        $other = $user->createApiToken('other');

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'current', 'is_current' => true])
            ->assertJsonFragment(['name' => 'other', 'is_current' => false]);
    }

    public function test_it_revokes_another_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');
        $other = $user->createApiToken('other');

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$other->accessToken->getKey())
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->getKey()]);
    }

    public function test_it_cannot_revoke_a_token_from_another_user(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');

        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createApiToken('other');

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$otherToken->accessToken->getKey())
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->getKey()]);
    }

    public function test_it_revokes_every_other_token_but_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');
        $other = $user->createApiToken('other');

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens')
            ->assertNoContent();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->getKey()]);
    }

    public function test_revoking_the_current_token_invalidates_it(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/v1/auth/tokens/'.$current->accessToken->getKey())
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');
        $user->createApiToken('other');

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/auth/logout-all')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_tokens_include_their_expiration_when_configured(): void
    {
        config(['sanctum.expiration' => 30]);

        $user = User::factory()->create();
        $token = $user->createApiToken('current');

        $this->assertNotNull($token->accessToken->expires_at);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonPath('data.0.expires_at', fn (mixed $expiresAt): bool => $expiresAt !== null);
    }

    public function test_expired_tokens_are_rejected(): void
    {
        config(['sanctum.expiration' => 1]);

        $user = User::factory()->create();
        $token = $user->createApiToken('current');

        $this->travel(2)->minutes();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
