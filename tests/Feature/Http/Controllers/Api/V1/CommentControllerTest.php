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

    public function test_comment_requires_a_body(): void
    {
        [$workspace, $task] = $this->taskInWorkspace();

        Sanctum::actingAs($workspace->owner);

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
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

    /**
     * @return array{0: Workspace, 1: Task}
     */
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
