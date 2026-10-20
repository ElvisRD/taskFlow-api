<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoardListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reorders_the_lists_of_the_board(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $todo = BoardList::factory()->for($board)->create(['name' => 'Por hacer', 'position' => 1]);
        $doing = BoardList::factory()->for($board)->create(['name' => 'En progreso', 'position' => 2]);
        $done = BoardList::factory()->for($board)->create(['name' => 'Hecho', 'position' => 3]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [$done->id, $todo->id, $doing->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $done->id)
            ->assertJsonPath('data.1.id', $todo->id)
            ->assertJsonPath('data.2.id', $doing->id)
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.2.position', 3);

        $this->assertDatabaseHas('board_lists', ['id' => $done->id, 'position' => 1]);
        $this->assertDatabaseHas('board_lists', ['id' => $todo->id, 'position' => 2]);
        $this->assertDatabaseHas('board_lists', ['id' => $doing->id, 'position' => 3]);
    }

    public function test_plain_member_cannot_reorder_the_lists(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $first = BoardList::factory()->for($board)->create(['position' => 1]);
        $second = BoardList::factory()->for($board)->create(['position' => 2]);
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [$second->id, $first->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('board_lists', ['id' => $first->id, 'position' => 1]);
    }

    public function test_non_member_cannot_reorder_the_lists(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $first = BoardList::factory()->for($board)->create(['position' => 1]);
        $second = BoardList::factory()->for($board)->create(['position' => 2]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [$second->id, $first->id],
        ])->assertForbidden();
    }

    public function test_reordering_lists_requires_every_list_of_the_board(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $first = BoardList::factory()->for($board)->create(['position' => 1]);
        BoardList::factory()->for($board)->create(['position' => 2]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [$first->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('lists');
    }

    public function test_reordering_lists_rejects_a_list_from_another_board(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        BoardList::factory()->for($board)->create(['position' => 1]);
        $foreign = BoardList::factory()->for(Board::factory()->for($workspace))->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [$foreign->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('lists');
    }

    public function test_admin_can_reorder_a_board_without_lists(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_guest_cannot_reorder_lists(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();

        $this->patchJson('/api/v1/boards/'.$board->id.'/lists/reorder', [
            'lists' => [],
        ])->assertUnauthorized();
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
