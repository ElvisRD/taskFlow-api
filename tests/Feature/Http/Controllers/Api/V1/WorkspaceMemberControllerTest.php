<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_an_existing_user_as_member(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email' => 'nuevo@example.com']);

        Sanctum::actingAs($workspace->owner);

        $response = $this->postJson('/api/v1/workspaces/'.$workspace->id.'/members', [
            'email' => 'nuevo@example.com',
            'role' => WorkspaceRole::Member->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'nuevo@example.com')
            ->assertJsonPath('data.role', WorkspaceRole::Member->value);

        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => WorkspaceRole::Member->value,
        ]);
    }

    public function test_adding_an_unknown_email_fails_validation(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/members', [
            'email' => 'nadie@example.com',
            'role' => WorkspaceRole::Member->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_adding_an_existing_member_fails_validation(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create(['email' => 'miembro@example.com']);
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/members', [
            'email' => 'miembro@example.com',
            'role' => WorkspaceRole::Member->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_can_update_the_role_of_a_member(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson(
            '/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id,
            ['role' => WorkspaceRole::Admin->value],
        )->assertOk()->assertJsonPath('data.role', WorkspaceRole::Admin->value);

        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
            'role' => WorkspaceRole::Admin->value,
        ]);
    }

    public function test_the_owner_role_cannot_be_changed(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson(
            '/api/v1/workspaces/'.$workspace->id.'/members/'.$workspace->owner_id,
            ['role' => WorkspaceRole::Member->value],
        )->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_admin_can_remove_a_member(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('user_workspace', [
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$workspace->owner_id)
            ->assertForbidden();
    }

    public function test_removing_a_user_that_is_not_a_member_returns_404(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.User::factory()->create()->id)
            ->assertNotFound();
    }

    public function test_plain_member_cannot_add_members(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);
        $candidate = User::factory()->create(['email' => 'candidato@example.com']);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/members', [
            'email' => 'candidato@example.com',
            'role' => WorkspaceRole::Member->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('user_workspace', [
            'user_id' => $candidate->id,
            'workspace_id' => $workspace->id,
        ]);
    }
}
