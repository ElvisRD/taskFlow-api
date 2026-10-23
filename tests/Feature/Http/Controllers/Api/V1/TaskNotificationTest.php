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
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_unassigning_a_task_does_not_notify_the_previous_assignee(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['assigned_to' => $assignee->id]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => null])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_reassigning_a_task_notifies_only_the_new_assignee(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $previousAssignee = $this->addMember($workspace, WorkspaceRole::Member);
        $newAssignee = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['assigned_to' => $previousAssignee->id]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => $newAssignee->id])->assertOk();

        Notification::assertSentToTimes($newAssignee, TaskAssigned::class, 1);
        Notification::assertNotSentTo($previousAssignee, TaskAssigned::class);
    }

    public function test_assigning_the_same_user_does_not_notify_again(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create(['assigned_to' => $assignee->id]);

        Sanctum::actingAs($workspace->owner);

        $this->patchJson('/api/v1/tasks/'.$task->id, ['assigned_to' => $assignee->id])->assertOk();

        Notification::assertNothingSent();
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

    public function test_creator_commenting_notifies_the_assignee_but_not_the_creator(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $creator = $workspace->owner;
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create([
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
        ]);

        Sanctum::actingAs($creator);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'Un comentario'])->assertCreated();

        Notification::assertSentTo($assignee, TaskCommented::class);
        Notification::assertNotSentTo($creator, TaskCommented::class);
    }

    public function test_commenting_notifies_a_shared_creator_and_assignee_only_once(): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $participant = $workspace->owner;
        $author = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create([
            'created_by' => $participant->id,
            'assigned_to' => $participant->id,
        ]);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'Un comentario'])->assertCreated();

        Notification::assertSentToTimes($participant, TaskCommented::class, 1);
        Notification::assertNotSentTo($author, TaskCommented::class);
    }

    #[DataProvider('taskParticipantRoles')]
    public function test_replying_to_a_task_participant_sends_only_the_reply_notification(string $role): void
    {
        Notification::fake();

        [$workspace, $board, $list] = $this->boardWithList();
        $creator = $workspace->owner;
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $replier = $this->addMember($workspace, WorkspaceRole::Member);
        $parentAuthor = $role === 'creator' ? $creator : $assignee;
        $otherParticipant = $role === 'creator' ? $assignee : $creator;
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

        Notification::assertSentToTimes($parentAuthor, CommentReply::class, 1);
        Notification::assertNotSentTo($parentAuthor, TaskCommented::class);
        Notification::assertSentTo($otherParticipant, TaskCommented::class);
        Notification::assertNotSentTo($replier, CommentReply::class);
        Notification::assertNotSentTo($replier, TaskCommented::class);
    }

    public static function taskParticipantRoles(): array
    {
        return [
            'creator' => ['creator'],
            'assignee' => ['assignee'],
        ];
    }

    public function test_notifications_are_broadcast_to_the_recipient_private_channel(): void
    {
        Event::fake([BroadcastNotificationCreated::class]);

        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();
        $recipient = $this->addMember($workspace, WorkspaceRole::Member);

        $recipient->notifyNow(new TaskAssigned($task, $workspace->owner), ['broadcast']);

        Event::assertDispatched(
            BroadcastNotificationCreated::class,
            function (BroadcastNotificationCreated $event) use ($recipient): bool {
                $channels = $event->broadcastOn();

                return $event->notifiable->is($recipient)
                    && count($channels) === 1
                    && $channels[0]->name === 'private-App.Models.User.'.$recipient->id;
            },
        );
    }

    public function test_notifications_include_the_board_and_workspace_context(): void
    {
        [$workspace, $board, $list] = $this->boardWithList();
        $task = Task::factory()->for($list, 'list')->create();
        $recipient = $this->addMember($workspace, WorkspaceRole::Member);

        $recipient->notifyNow(new TaskAssigned($task, $workspace->owner), ['database']);

        $payload = $recipient->notifications()->latest('created_at')->firstOrFail()->data;

        $this->assertSame($workspace->id, $payload['workspace_id']);
        $this->assertSame($board->id, $payload['board_id']);
        $this->assertSame($task->id, $payload['task_id']);
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
