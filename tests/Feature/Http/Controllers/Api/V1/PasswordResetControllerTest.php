<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_reset_link_to_a_registered_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ANA@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_it_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nadie@example.com'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_it_resets_the_password_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = $this->requestResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'ana@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_resetting_the_password_revokes_all_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->createApiToken('old-session');
        $token = $this->requestResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'ana@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'ana@example.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertFalse(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_it_requires_a_confirmed_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = $this->requestResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'ana@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_it_is_throttled_after_three_requests_for_the_same_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'ana@example.com']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@example.com'])
                ->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@example.com'])
            ->assertTooManyRequests();
    }

    private function requestResetToken(User $user): string
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        return $token;
    }
}
