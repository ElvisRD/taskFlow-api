<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_only_user_can_set_a_password_without_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'password' => null,
        ]);
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertNoContent();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_password', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@example.com',
            'password' => 'NewPassword123!',
        ])->assertOk();
    }

    public function test_user_with_a_password_must_provide_the_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = User::factory()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_updating_the_password_revokes_other_tokens(): void
    {
        $user = User::factory()->create();
        $current = $user->createApiToken('current');
        $other = $user->createApiToken('other');

        $this->withToken($current->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_demo_accounts_cannot_update_their_password(): void
    {
        $user = User::factory()->create(['email' => 'ana@taskflow.test']);
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'AnotherPassword123!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->putJson('/api/v1/auth/password', [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnauthorized();
    }
}
