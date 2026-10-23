<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\NotificationMute;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssigned;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationMuteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mute_a_workspace(): void
    {
        [$workspace, , , $member] = $this->workspaceContext();

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'workspace')
            ->assertJsonPath('data.target_id', $workspace->id)
            ->assertJsonPath('data.name', $workspace->name);

        $this->assertDatabaseHas('notification_mutes', [
            'user_id' => $member->id,
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);
    }

    public function test_user_can_mute_a_board(): void
    {
        [$workspace, $board, , $member] = $this->workspaceContext();

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'board',
            'target_id' => $board->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'board')
            ->assertJsonPath('data.target_id', $board->id)
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.name', $board->name);
    }

    public function test_muting_the_same_target_twice_is_idempotent(): void
    {
        [$workspace, , , $member] = $this->workspaceContext();

        Sanctum::actingAs($member);

        $payload = ['target_type' => 'workspace', 'target_id' => $workspace->id];

        $this->postJson('/api/v1/notifications/mutes', $payload)->assertCreated();
        $this->postJson('/api/v1/notifications/mutes', $payload)->assertOk();

        $this->assertSame(1, $member->notificationMutes()->count());
    }

    public function test_muting_a_workspace_suppresses_task_notifications(): void
    {
        [$workspace, $board, $list, $member] = $this->workspaceContext();
        $task = Task::factory()->for($list, 'list')->create();
        $notification = new TaskAssigned($task, $workspace->owner);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
        ])->assertCreated();

        $this->assertSame([], $notification->via($member));

        $mute = $member->notificationMutes()->firstOrFail();

        $this->deleteJson('/api/v1/notifications/mutes/'.$mute->id)->assertNoContent();

        $this->assertSame(['database', 'mail', 'broadcast'], $notification->via($member));
    }

    public function test_muting_a_board_only_suppresses_that_board(): void
    {
        [$workspace, $mutedBoard, , $member] = $this->workspaceContext();
        $otherBoard = Board::factory()->for($workspace)->create();

        $mutedTask = Task::factory()
            ->for(BoardList::factory()->for($mutedBoard), 'list')
            ->create();
        $otherTask = Task::factory()
            ->for(BoardList::factory()->for($otherBoard), 'list')
            ->create();

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'board',
            'target_id' => $mutedBoard->id,
        ])->assertCreated();

        $this->assertSame(
            [],
            (new TaskAssigned($mutedTask, $workspace->owner))->via($member),
        );
        $this->assertSame(
            ['database', 'mail', 'broadcast'],
            (new TaskAssigned($otherTask, $workspace->owner))->via($member),
        );
    }

    public function test_user_cannot_mute_a_workspace_they_do_not_belong_to(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('notification_mutes', 0);
    }

    public function test_user_cannot_mute_a_board_they_do_not_belong_to(): void
    {
        $board = Board::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'board',
            'target_id' => $board->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('notification_mutes', 0);
    }

    public function test_user_cannot_remove_another_users_mute(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $mute = NotificationMute::create([
            'user_id' => $owner->id,
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);

        Sanctum::actingAs($other);

        $this->deleteJson('/api/v1/notifications/mutes/'.$mute->id)->assertNotFound();

        $this->assertDatabaseHas('notification_mutes', ['id' => $mute->id]);
    }

    public function test_user_can_remove_their_own_mute(): void
    {
        [$workspace, , , $member] = $this->workspaceContext();
        $mute = $member->notificationMutes()->create([
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/notifications/mutes/'.$mute->id)->assertNoContent();

        $this->assertDatabaseMissing('notification_mutes', ['id' => $mute->id]);
    }

    public function test_mutes_are_isolated_per_user(): void
    {
        [$workspace, , , $member] = $this->workspaceContext();
        $otherMember = User::factory()->create();

        $workspace->members()->attach($otherMember->getKey(), [
            'role' => WorkspaceRole::Member->value,
        ]);

        $member->notificationMutes()->create([
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);

        Sanctum::actingAs($otherMember);

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('mutes', []);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
        ])->assertCreated();

        $this->assertSame(
            2,
            NotificationMute::query()->where('muteable_id', $workspace->id)->count(),
        );
    }

    public function test_muting_a_board_that_does_not_exist_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'board',
            'target_id' => 999999,
        ])->assertNotFound();
    }

    public function test_mute_requires_a_valid_target(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/notifications/mutes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_type', 'target_id']);

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'team',
            'target_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_type');

        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'workspace',
            'target_id' => 999999,
        ])->assertNotFound();
    }

    public function test_guest_cannot_manage_notification_mutes(): void
    {
        $this->postJson('/api/v1/notifications/mutes', [
            'target_type' => 'workspace',
            'target_id' => 1,
        ])->assertUnauthorized();

        $this->deleteJson('/api/v1/notifications/mutes/1')->assertUnauthorized();
    }

    /**
     * @return array{Workspace, Board, BoardList, User}
     */
    private function workspaceContext(): array
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), [
            'role' => WorkspaceRole::Member->value,
        ]);

        return [$workspace, $board, $list, $member];
    }
}
