<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_board_with_the_next_position(): void
    {
        $workspace = Workspace::factory()->create();
        Board::factory()->for($workspace)->create(['position' => 1]);

        Sanctum::actingAs($workspace->owner);

        $response = $this->postJson('/api/v1/workspaces/'.$workspace->id.'/boards', [
            'name' => 'Sprint 2',
            'color' => '#22c55e',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Sprint 2')
            ->assertJsonPath('data.position', 2);

        $this->assertDatabaseHas('boards', [
            'workspace_id' => $workspace->id,
            'name' => 'Sprint 2',
        ]);
    }

    public function test_plain_member_cannot_create_a_board(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/workspaces/'.$workspace->id.'/boards', ['name' => 'Sprint 2'])
            ->assertForbidden();

        $this->assertDatabaseMissing('boards', ['workspace_id' => $workspace->id, 'name' => 'Sprint 2']);
    }

    public function test_member_can_list_the_boards_of_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Board::factory()->for($workspace)->create(['name' => 'Sprint 1']);
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/workspaces/'.$workspace->id.'/boards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sprint 1');
    }

    public function test_non_member_cannot_list_the_boards_of_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/workspaces/'.$workspace->id.'/boards')->assertForbidden();
    }

    public function test_show_returns_the_lists_with_their_tasks(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create(['name' => 'Por hacer', 'position' => 1]);
        Task::factory()->for($list, 'list')->create(['title' => 'Primera tarea']);

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/workspaces/'.$workspace->id.'/boards/'.$board->id)
            ->assertOk()
            ->assertJsonPath('data.lists.0.name', 'Por hacer')
            ->assertJsonPath('data.lists.0.tasks.0.title', 'Primera tarea');
    }

    public function test_board_that_belongs_to_another_workspace_returns_404(): void
    {
        $owner = User::factory()->create();
        $workspaceA = Workspace::factory()->create(['owner_id' => $owner->getKey()]);
        $workspaceB = Workspace::factory()->create(['owner_id' => $owner->getKey()]);
        $board = Board::factory()->for($workspaceB)->create();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/workspaces/'.$workspaceA->id.'/boards/'.$board->id)
            ->assertNotFound();
    }

    public function test_admin_updates_the_board(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/workspaces/'.$workspace->id.'/boards/'.$board->id, [
            'name' => 'Renombrado',
            'color' => '#ef4444',
        ])->assertOk()->assertJsonPath('data.name', 'Renombrado');

        $this->assertDatabaseHas('boards', ['id' => $board->id, 'name' => 'Renombrado']);
    }

    public function test_admin_deletes_the_board(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/boards/'.$board->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('boards', ['id' => $board->id]);
    }

    public function test_plain_member_cannot_delete_the_board(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/boards/'.$board->id)
            ->assertForbidden();
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
