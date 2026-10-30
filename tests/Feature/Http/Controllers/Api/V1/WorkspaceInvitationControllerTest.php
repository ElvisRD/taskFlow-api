<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\WorkspaceInvitationStatus;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceInvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_an_email_and_the_invitation_is_emailed(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create();
        $sentToken = null;

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations', [
            'email' => 'Nueva@Example.com',
            'role' => WorkspaceRole::Member->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'nueva@example.com')
            ->assertJsonPath('data.role', WorkspaceRole::Member->value)
            ->assertJsonPath('data.status', WorkspaceInvitationStatus::Pending->value);

        $invitation = WorkspaceInvitation::query()->firstOrFail();

        Notification::assertSentOnDemand(
            WorkspaceInvitationNotification::class,
            function (WorkspaceInvitationNotification $notification, array $channels, object $notifiable) use (&$sentToken, $invitation): bool {
                $sentToken = $notification->token;

                return $notifiable->routes['mail'] === 'nueva@example.com'
                    && $notification->invitation->is($invitation);
            },
        );

        $this->assertNotNull($sentToken);
        $this->assertSame(WorkspaceInvitation::hashToken($sentToken), $invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());
    }

    public function test_admin_can_invite_an_existing_user_who_is_not_a_member(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'persona@example.com']);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations', [
            'email' => 'persona@example.com',
            'role' => WorkspaceRole::Admin->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'persona@example.com')
            ->assertJsonPath('data.role', WorkspaceRole::Admin->value);

        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
        $this->assertDatabaseMissing('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_an_existing_member_cannot_be_invited(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create();
        $member = User::factory()->create(['email' => 'miembro@example.com']);
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations', [
            'email' => 'miembro@example.com',
            'role' => WorkspaceRole::Member->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_the_workspace_owner_cannot_be_invited(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations', [
            'email' => $workspace->owner->email,
            'role' => WorkspaceRole::Admin->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_duplicate_pending_invitation_is_rejected(): void
    {
        $workspace = Workspace::factory()->create();
        WorkspaceInvitation::factory()->for($workspace)->create(['email' => 'pendiente@example.com']);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations', [
            'email' => 'pendiente@example.com',
            'role' => WorkspaceRole::Member->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_plain_member_cannot_invite_people(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations', [
            'email' => 'nueva@example.com',
            'role' => WorkspaceRole::Member->value,
        ])->assertForbidden();
    }

    public function test_admin_can_list_workspace_invitations(): void
    {
        $workspace = Workspace::factory()->create();
        WorkspaceInvitation::factory()->for($workspace)->create(['email' => 'uno@example.com']);
        WorkspaceInvitation::factory()->for($workspace)->revoked()->create(['email' => 'dos@example.com']);

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/workspaces/'.$workspace->id.'/invitations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_invited_user_can_accept_the_invitation(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $token = 'a-valid-invitation-token';
        $invitation = $this->invitation($workspace, $user->email, $token, [
            'role' => WorkspaceRole::Admin,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.email', 'invitado@example.com')
            ->assertJsonPath('data.role', WorkspaceRole::Admin->value);

        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => WorkspaceRole::Admin->value,
        ]);

        $invitation->refresh();

        $this->assertSame(WorkspaceInvitationStatus::Accepted, $invitation->status);
        $this->assertSame($user->id, $invitation->accepted_by);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_the_invitation_cannot_be_accepted_with_a_different_email(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'otro@example.com']);
        $token = 'a-valid-invitation-token';
        $this->invitation($workspace, 'invitado@example.com', $token);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertForbidden();

        $this->assertDatabaseMissing('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $token = 'an-expired-invitation-token';
        $invitation = $this->invitation($workspace, $user->email, $token, [
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertStatus(410);

        $this->assertSame(WorkspaceInvitationStatus::Expired, $invitation->refresh()->status);
    }

    public function test_a_revoked_invitation_cannot_be_accepted(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $token = 'a-revoked-invitation-token';
        $this->invitation($workspace, $user->email, $token, [
            'status' => WorkspaceInvitationStatus::Revoked,
            'revoked_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertStatus(410);
    }

    public function test_an_invitation_cannot_be_accepted_twice(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $token = 'an-accepted-invitation-token';
        $this->invitation($workspace, $user->email, $token, [
            'status' => WorkspaceInvitationStatus::Accepted,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertStatus(409);
    }

    public function test_accepting_an_invitation_for_an_existing_member_does_not_duplicate_the_membership(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $workspace->members()->attach($user->getKey(), ['role' => WorkspaceRole::Member->value]);
        $token = 'an-existing-member-invitation-token';
        $invitation = $this->invitation($workspace, $user->email, $token);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertOk();

        $this->assertSame(1, $workspace->members()->whereKey($user->getKey())->count());
        $this->assertSame(WorkspaceInvitationStatus::Accepted, $invitation->refresh()->status);
    }

    public function test_admin_can_resend_a_pending_invitation_and_the_old_token_stops_working(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $oldToken = 'the-old-invitation-token';
        $invitation = $this->invitation($workspace, $user->email, $oldToken, [
            'expires_at' => now()->addHour(),
        ]);
        $newToken = null;

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id.'/resend')
            ->assertOk()
            ->assertJsonPath('data.status', WorkspaceInvitationStatus::Pending->value);

        Notification::assertSentOnDemand(
            WorkspaceInvitationNotification::class,
            function (WorkspaceInvitationNotification $notification) use (&$newToken): bool {
                $newToken = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(WorkspaceInvitation::hashToken($newToken), $invitation->refresh()->token);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$oldToken.'/accept')
            ->assertNotFound();

        $this->postJson('/api/v1/workspace-invitations/'.$newToken.'/accept')
            ->assertOk();
    }

    public function test_expired_invitations_can_be_resent(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create();
        $invitation = WorkspaceInvitation::factory()->for($workspace)->expired()->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id.'/resend')
            ->assertOk()
            ->assertJsonPath('data.status', WorkspaceInvitationStatus::Pending->value);

        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
    }

    public function test_admin_can_revoke_an_invitation(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'invitado@example.com']);
        $token = 'the-invitation-to-revoke';
        $invitation = $this->invitation($workspace, $user->email, $token);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id)
            ->assertNoContent();

        $this->assertSame(WorkspaceInvitationStatus::Revoked, $invitation->refresh()->status);
        $this->assertNotNull($invitation->revoked_at);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace-invitations/'.$token.'/accept')
            ->assertStatus(410);
    }

    public function test_a_revoked_invitation_cannot_be_resent(): void
    {
        $workspace = Workspace::factory()->create();
        $invitation = WorkspaceInvitation::factory()->for($workspace)->revoked()->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id.'/resend')
            ->assertUnprocessable();
    }

    public function test_a_plain_member_cannot_resend_or_revoke_invitations(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);
        $invitation = WorkspaceInvitation::factory()->for($workspace)->create();

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id.'/resend')
            ->assertForbidden();

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id)
            ->assertForbidden();
    }

    public function test_an_invitation_cannot_be_managed_from_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $invitation = WorkspaceInvitation::factory()->for($otherWorkspace)->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id.'/resend')
            ->assertNotFound();

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/invitations/'.$invitation->id)
            ->assertNotFound();
    }

    public function test_accepting_an_invitation_requires_authentication(): void
    {
        $this->postJson('/api/v1/workspace-invitations/a-token/accept')
            ->assertUnauthorized();
    }

    private function invitation(
        Workspace $workspace,
        string $email,
        string $token,
        array $attributes = [],
    ): WorkspaceInvitation {
        return WorkspaceInvitation::factory()->for($workspace)->create([
            'email' => $email,
            'token' => WorkspaceInvitation::hashToken($token),
            'role' => WorkspaceRole::Member,
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
            ...$attributes,
        ]);
    }
}
