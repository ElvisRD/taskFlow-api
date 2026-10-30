<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssigned;
use App\NotificationType;
use App\TaskActivityType;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_removing_a_member_unassigns_their_tasks_in_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        $task = $this->taskIn($workspace, $member);
        $taskInAnotherWorkspace = $this->taskIn(Workspace::factory()->create(), $member);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id)
            ->assertNoContent();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assigned_to' => null,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $taskInAnotherWorkspace->id,
            'assigned_to' => $member->id,
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'type' => TaskActivityType::Unassigned->value,
        ]);
    }

    public function test_removing_a_member_can_reassign_their_tasks_to_another_member(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $replacement = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);
        $workspace->members()->attach($replacement->getKey(), ['role' => WorkspaceRole::Member->value]);

        $task = $this->taskIn($workspace, $member);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id, [
            'reassign_to' => $replacement->id,
        ])->assertNoContent();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assigned_to' => $replacement->id,
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'type' => TaskActivityType::Assigned->value,
        ]);
        Notification::assertSentTo($replacement, TaskAssigned::class);
    }

    public function test_tasks_cannot_be_reassigned_to_a_non_member(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);
        $task = $this->taskIn($workspace, $member);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id, [
            'reassign_to' => User::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('reassign_to');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assigned_to' => $member->id,
        ]);
    }

    public function test_tasks_cannot_be_reassigned_to_the_member_being_removed(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id, [
            'reassign_to' => $member->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('reassign_to');
    }

    public function test_removing_a_member_stops_notifications_from_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member->getKey(), ['role' => WorkspaceRole::Member->value]);
        $task = $this->taskIn($workspace, $member);

        $member->notifyNow(new TaskAssigned($task, $workspace->owner), ['database']);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id]);

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson('/api/v1/workspaces/'.$workspace->id.'/members/'.$member->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $member->id]);
        $this->assertSame(
            [],
            $member->notificationChannels(NotificationType::TaskAssigned, $task),
        );
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

    private function taskIn(Workspace $workspace, User $assignee): Task
    {
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();

        return Task::factory()->for($list, 'list')->create([
            'assigned_to' => $assignee->getKey(),
        ]);
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
