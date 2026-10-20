<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CommentReply;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_a_task_notifies_the_assignee(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => $member->id])->assertOk();

        Notification::assertSentTo($member, TaskAssigned::class);
    }

    public function test_creating_an_assigned_task_notifies_the_assignee(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/lists/'.$list->id.'/tasks', [
            'title' => 'Tarea asignada',
            'assigned_to' => $member->id,
        ])->assertCreated();

        Notification::assertSentTo($member, TaskAssigned::class);
    }

    public function test_assigning_a_task_to_yourself_does_not_notify(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => $workspace->owner_id])->assertOk();

        Notification::assertNotSentTo($workspace->owner, TaskAssigned::class);
    }

    public function test_commenting_notifies_creator_and_assignee_but_not_the_author(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $creator = $workspace->owner;
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $author = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create([
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
        ]);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'Un comentario'])->assertCreated();

        Notification::assertSentTo($creator, TaskCommented::class);
        Notification::assertSentTo($assignee, TaskCommented::class);
        Notification::assertNotSentTo($author, TaskCommented::class);
    }

    public function test_replying_notifies_the_parent_author_with_a_reply_notification(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $creator = $workspace->owner;
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $parentAuthor = $this->addMember($workspace, WorkspaceRole::Member);
        $replier = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create([
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
        ]);
        $parent = Comment::factory()->for($task)->create(['user_id' => $parentAuthor->id]);

        Sanctum::actingAs($replier);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', [
            'body' => 'Una respuesta',
            'parent_id' => $parent->id,
        ])->assertCreated();

        Notification::assertSentTo($parentAuthor, CommentReply::class);
        Notification::assertNotSentTo($parentAuthor, TaskCommented::class);
        Notification::assertSentTo($creator, TaskCommented::class);
        Notification::assertSentTo($assignee, TaskCommented::class);
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
