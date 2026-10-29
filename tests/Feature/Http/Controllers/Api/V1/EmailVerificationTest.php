<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email_verified', false);

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_users_cannot_access_protected_endpoints(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/workspaces')
            ->assertForbidden();
    }

    public function test_verified_users_can_access_protected_endpoints(): void
    {
        $user = User::factory()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/workspaces')
            ->assertOk();
    }

    public function test_unverified_user_can_request_a_new_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertNoContent();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verified_user_does_not_receive_another_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $user->createApiToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_email_can_be_verified_with_a_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Correo electrónico verificado correctamente.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_redirects_browser_requests_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'http://localhost:5173']);

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect('http://localhost:5173/email/verified');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_verification_links_redirect_browsers_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'http://localhost:5173']);

        $user = User::factory()->unverified()->create();

        $url = route('verification.verify', [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect('http://localhost:5173/email/verified?status=invalid');
    }

    public function test_verification_link_without_a_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = route('verification.verify', [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_with_an_invalid_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1('otro@example.com'),
        ]);

        $this->getJson($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_is_idempotent(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_resend_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createApiToken('phpunit');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withToken($token->plainTextToken)
                ->postJson('/api/v1/auth/email/verification-notification')
                ->assertNoContent();
        }

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertTooManyRequests();
    }
}
