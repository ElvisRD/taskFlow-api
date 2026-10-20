<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creates_a_comment_on_the_task(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/v1/tasks/'.$task->id.'/comments', [
            'body' => 'Reviso esta tarea hoy.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Reviso esta tarea hoy.')
            ->assertJsonPath('data.author.id', $member->id);

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'body' => 'Reviso esta tarea hoy.',
        ]);
    }

    public function test_member_replies_to_a_comment_on_the_same_task(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $parent = Comment::factory()->for($task)->create();

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/v1/tasks/'.$task->id.'/comments', [
            'body' => 'Estoy de acuerdo.',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.body', 'Estoy de acuerdo.');

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'parent_id' => $parent->id,
            'user_id' => $member->id,
            'body' => 'Estoy de acuerdo.',
        ]);
    }

    public function test_comment_requires_a_body(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_reply_parent_must_belong_to_the_same_task(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        [, $otherTask] = $this->taskInWorkspace();
        $foreignComment = Comment::factory()->for($otherTask)->create();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', [
            'body' => 'Respuesta inválida.',
            'parent_id' => $foreignComment->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_replies_can_only_target_top_level_comments(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $parent = Comment::factory()->for($task)->create();
        $reply = Comment::factory()->for($task)->create(['parent_id' => $parent->id]);

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', [
            'body' => 'Respuesta anidada.',
            'parent_id' => $reply->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_comments_include_their_replies(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $parent = Comment::factory()->for($task)->create();
        $reply = Comment::factory()->for($task)->create([
            'parent_id' => $parent->id,
            'user_id' => $workspace->owner->id,
        ]);

        Sanctum::actingAs($workspace->owner);

        $this->getJson('/api/v1/tasks/'.$task->id.'/comments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonPath('data.0.replies.0.id', $reply->id)
            ->assertJsonPath('data.0.replies.0.parent_id', $parent->id)
            ->assertJsonPath('data.0.replies.0.author.id', $workspace->owner->id);
    }

    public function test_non_member_cannot_comment_on_the_task(): void
    {
        [, $task] = $this->taskInWorkspace();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => 'Hola'])
            ->assertForbidden();
    }

    public function test_author_can_delete_own_comment(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $comment = Comment::factory()->for($task)->create(['user_id' => $member->id]);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/comments/'.$comment->id)->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_other_member_cannot_delete_the_comment(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $author = $this->addMember($workspace, WorkspaceRole::Member);
        $other = $this->addMember($workspace, WorkspaceRole::Member);
        $comment = Comment::factory()->for($task)->create(['user_id' => $author->id]);

        Sanctum::actingAs($other);

        $this->deleteJson('/api/v1/comments/'.$comment->id)->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_admin_can_delete_a_comment_from_another_member(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $author = $this->addMember($workspace, WorkspaceRole::Member);
        $comment = Comment::factory()->for($task)->create(['user_id' => $author->id]);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/comments/'.$comment->id)->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_deleting_a_comment_also_deletes_its_replies(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();
        $parent = Comment::factory()->for($task)->create();
        $reply = Comment::factory()->for($task)->create(['parent_id' => $parent->id]);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/comments/'.$parent->id)->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $parent->id]);
        $this->assertDatabaseMissing('comments', ['id' => $reply->id]);
    }

    private function taskInWorkspace(): array
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();
        $task = Task::factory()->for($list, 'list')->create();

        return [$workspace, $task];
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
