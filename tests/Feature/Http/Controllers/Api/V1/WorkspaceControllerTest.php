<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_workspace_and_becomes_admin_member(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/workspaces', [
            'name' => 'Mi Espacio',
            'description' => 'Un espacio de prueba',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mi Espacio')
            ->assertJsonPath('data.slug', 'mi-espacio')
            ->assertJsonPath('data.role', 'admin');

        $workspace = Workspace::firstWhere('slug', 'mi-espacio');

        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => WorkspaceRole::Admin->value,
        ]);
    }

    public function test_index_only_returns_workspaces_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->getKey()]);
        Workspace::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workspace->id);
    }

    public function test_non_member_cannot_view_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/workspaces/'.$workspace->id)->assertForbidden();
    }

    public function test_member_can_view_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/workspaces/'.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonPath('data.role', WorkspaceRole::Member->value);
    }

    public function test_admin_member_can_update_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->addMember($workspace, WorkspaceRole::Admin);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['name' => 'Nombre Nuevo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre Nuevo');

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Nombre Nuevo']);
    }

    public function test_plain_member_cannot_update_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['name' => 'Nombre Nuevo'])
            ->assertForbidden();
    }

    public function test_only_the_owner_can_delete_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->addMember($workspace, WorkspaceRole::Admin);

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id)->assertForbidden();
    }

    public function test_owner_deletes_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->getKey()]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id)->assertNoContent();

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
