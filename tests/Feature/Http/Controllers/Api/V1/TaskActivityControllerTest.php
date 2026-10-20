<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssigned;
use App\TaskActivityType;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_task_records_and_lists_the_created_activity(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();

        Sanctum::actingAs($workspace->owner);

        $taskId = $this->postJson('/api/v1/lists/'.$list->id.'/tasks', ['title' => 'Nueva tarea'])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $taskId,
            'user_id' => $workspace->owner_id,
            'type' => TaskActivityType::Created->value,
        ]);

        $this->getJson('/api/v1/tasks/'.$taskId.'/activities')
            ->assertOk()
            ->assertJsonPath('data.0.type', TaskActivityType::Created->value)
            ->assertJsonPath('data.0.user.id', $workspace->owner_id);
    }

    public function test_moving_a_task_records_the_origin_and_destination(): void
    {
        [$workspace, $board, $source] = $this->boardWithList();
        $destination = BoardList::factory()->for($board)->create();
        $task = Task::factory()->for($source, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id.'/move', [
            'board_list_id' => $destination->id,
        ])->assertOk();

        $activity = TaskActivity::query()
            ->where('task_id', $task->id)
            ->where('type', TaskActivityType::Moved->value)
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($workspace->owner_id, $activity->user_id);
        $this->assertSame($source->id, $activity->data['from_board_list_id']);
        $this->assertSame($destination->id, $activity->data['to_board_list_id']);
    }

    public function test_completing_and_reopening_a_task_records_activities(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['completed' => true])->assertOk();
        $this->patchJson('/api/v1/tasks/'.$task->id, ['completed' => false])->assertOk();

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'type' => TaskActivityType::Completed->value,
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'type' => TaskActivityType::Reopened->value,
        ]);
    }

    public function test_assigning_a_task_records_an_activity_and_notifies_the_assignee(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => $member->id])->assertOk();

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'user_id' => $workspace->owner_id,
            'type' => TaskActivityType::Assigned->value,
        ]);

        Notification::assertSentTo($member, TaskAssigned::class);
    }

    public function test_unassigning_a_task_records_an_activity(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['assigned_to' => $member->id]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => null])->assertOk();

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'type' => TaskActivityType::Unassigned->value,
        ]);
    }

    public function test_commenting_records_a_comment_activity(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $commentId = $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'Un comentario'])
            ->assertCreated()
            ->json('data.id');

        $activity = TaskActivity::query()
            ->where('task_id', $task->id)
            ->where('type', TaskActivityType::CommentAdded->value)
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($commentId, $activity->data['comment_id']);
    }

    public function test_non_member_cannot_view_task_activities(): void
    {
        [, , $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/tasks/'.$task->id.'/activities')->assertForbidden();
    }

    public function test_guest_cannot_view_task_activities(): void
    {
        [, , $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        $this->getJson('/api/v1/tasks/'.$task->id.'/activities')->assertUnauthorized();
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
