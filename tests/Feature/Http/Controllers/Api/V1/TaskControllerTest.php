<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\TaskPriority;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creates_a_task_in_the_list(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/v1/lists/'.$list->id.'/tasks', [
            'title' => 'Escribir pruebas',
            'description' => 'Cubrir los endpoints de tareas',
            'priority' => TaskPriority::High->value,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Escribir pruebas')
            ->assertJsonPath('data.priority', TaskPriority::High->value)
            ->assertJsonPath('data.position', 1);

        $this->assertDatabaseHas('tasks', [
            'board_list_id' => $list->id,
            'title' => 'Escribir pruebas',
            'created_by' => $member->id,
            'priority' => TaskPriority::High->value,
        ]);
    }

    public function test_task_cannot_be_assigned_to_a_user_outside_the_workspace(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $outsider = User::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/lists/'.$list->id.'/tasks', [
            'title' => 'Tarea',
            'assigned_to' => $outsider->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    }

    public function test_task_can_be_assigned_to_a_workspace_member(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/lists/'.$list->id.'/tasks', [
            'title' => 'Tarea asignada',
            'assigned_to' => $member->id,
        ])->assertCreated()->assertJsonPath('data.assignee.id', $member->id);
    }

    public function test_index_filters_tasks_by_priority(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        Task::factory()->for($list, 'list')->create(['priority' => TaskPriority::High]);
        Task::factory()->for($list, 'list')->create(['priority' => TaskPriority::Low]);

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/boards/'.$board->id.'/tasks?priority='.TaskPriority::High->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.priority', TaskPriority::High->value);
    }

    public function test_index_searches_tasks_by_title(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        Task::factory()->for($list, 'list')->create(['title' => 'Diseñar la base de datos']);
        Task::factory()->for($list, 'list')->create(['title' => 'Configurar el servidor']);

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/boards/'.$board->id.'/tasks?search=base+de+datos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Diseñar la base de datos');
    }

    public function test_task_comment_count_includes_replies(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();
        $parent = Comment::factory()->for($task)->create();
        Comment::factory()->for($task)->create(['parent_id' => $parent->id]);

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/boards/'.$board->id.'/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.comments_count', 2);
    }

    public function test_index_filters_completed_tasks(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        Task::factory()->for($list, 'list')->completed()->create();
        Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/boards/'.$board->id.'/tasks?completed=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_completed', true);
    }

    public function test_member_moves_a_task_to_another_list_of_the_same_board(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $destination = BoardList::factory()->for($board)->create(['name' => 'En progreso']);
        $task = Task::factory()->for($list, 'list')->create();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['board_list_id' => $destination->id])
            ->assertOk()
            ->assertJsonPath('data.board_list_id', $destination->id)
            ->assertJsonPath('data.position', 1);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'board_list_id' => $destination->id,
            'position' => 1,
        ]);
    }

    public function test_moving_a_task_to_a_list_of_another_board_is_rejected(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $otherBoard = Board::factory()->for($workspace)->create();
        $foreignList = BoardList::factory()->for($otherBoard)->create();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['board_list_id' => $foreignList->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('board_list_id');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'board_list_id' => $list->id]);
    }

    public function test_completing_and_reopening_a_task_toggles_the_completed_date(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['completed' => true])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->assertNotNull($task->refresh()->completed_at);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['completed' => false])
            ->assertOk()
            ->assertJsonPath('data.is_completed', false);

        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_creator_can_delete_the_task(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['created_by' => $member->id]);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/tasks/'.$task->id)->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_member_cannot_delete_a_task_created_by_another_user(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $creator = $this->addMember($workspace, WorkspaceRole::Member);
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['created_by' => $creator->id]);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/tasks/'.$task->id)->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_admin_can_delete_a_task_created_by_another_user(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['created_by' => $member->id]);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/tasks/'.$task->id)->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_non_member_cannot_view_a_task(): void
    {
        [, , $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/tasks/'.$task->id)->assertForbidden();
    }

    private function boardWithList(): array
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();

        return [$workspace, $board, $list];
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
