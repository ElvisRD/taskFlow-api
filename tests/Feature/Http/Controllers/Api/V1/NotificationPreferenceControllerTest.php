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
use App\Notifications\TaskDueReminder;
use App\NotificationType;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_default_preferences_for_every_notification_type(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonCount(count(NotificationType::cases()), 'data')
            ->assertJsonPath('data.0.type', 'task_assigned')
            ->assertJsonPath('data.0.label', 'Tarea asignada')
            ->assertJsonPath('data.0.in_app', true)
            ->assertJsonPath('data.0.email', true)
            ->assertJsonPath('mutes', []);
    }

    public function test_user_can_disable_email_for_a_single_type(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', ['email' => false])
            ->assertOk()
            ->assertJsonPath('data.type', 'task_assigned')
            ->assertJsonPath('data.in_app', true)
            ->assertJsonPath('data.email', false);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'task_assigned',
            'in_app' => true,
            'email' => false,
        ]);
    }

    public function test_user_can_disable_a_notification_type_completely(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', [
            'in_app' => false,
            'email' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.in_app', false)
            ->assertJsonPath('data.email', false);
    }

    public function test_partial_updates_preserve_the_other_channel(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', ['in_app' => false])
            ->assertOk()
            ->assertJsonPath('data.in_app', false)
            ->assertJsonPath('data.email', true);

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', ['email' => false])
            ->assertOk()
            ->assertJsonPath('data.in_app', false)
            ->assertJsonPath('data.email', false);
    }

    public function test_preferences_are_isolated_per_notification_type(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', ['email' => false])
            ->assertOk();

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'task_assigned')
            ->assertJsonPath('data.0.email', false)
            ->assertJsonPath('data.1.type', 'comment_added')
            ->assertJsonPath('data.1.email', true);
    }

    public function test_preferences_include_muted_workspaces_and_boards(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();

        $workspace->members()->attach($user->getKey(), ['role' => WorkspaceRole::Member->value]);

        $user->notificationMutes()->create([
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);
        $user->notificationMutes()->create([
            'muteable_type' => Board::class,
            'muteable_id' => $board->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonCount(2, 'mutes')
            ->assertJsonFragment([
                'type' => 'workspace',
                'target_id' => $workspace->id,
                'name' => $workspace->name,
            ])
            ->assertJsonFragment([
                'type' => 'board',
                'target_id' => $board->id,
                'workspace_id' => $workspace->id,
                'name' => $board->name,
            ]);
    }

    public function test_preferences_require_at_least_one_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('in_app');
    }

    public function test_an_unknown_notification_type_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/notifications/preferences/unknown', ['email' => false])
            ->assertNotFound();
    }

    public function test_preferences_control_the_notification_channels(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();
        $notification = new TaskAssigned($task, User::factory()->create());

        $this->assertSame(['database', 'mail', 'broadcast'], $notification->via($user));

        $user->notificationPreferences()->create([
            'type' => NotificationType::TaskAssigned->value,
            'in_app' => true,
            'email' => false,
        ]);

        $this->assertSame(['database', 'broadcast'], $notification->via($user));

        $user->notificationPreferences()->updateOrCreate(
            ['type' => NotificationType::TaskAssigned->value],
            ['in_app' => false, 'email' => false],
        );

        $this->assertSame([], $notification->via($user));
    }

    public function test_comment_added_preferences_control_the_notification_channels(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();
        $comment = Comment::factory()->for($task)->create();
        $notification = new TaskCommented($task, $comment, User::factory()->create());

        $this->assertSame(['database', 'mail', 'broadcast'], $notification->via($user));

        $user->notificationPreferences()->create([
            'type' => NotificationType::TaskCommented->value,
            'in_app' => true,
            'email' => false,
        ]);

        $this->assertSame(['database', 'broadcast'], $notification->via($user));

        $user->notificationPreferences()->updateOrCreate(
            ['type' => NotificationType::TaskCommented->value],
            ['in_app' => false, 'email' => false],
        );

        $this->assertSame([], $notification->via($user));
    }

    public function test_comment_reply_preferences_control_the_notification_channels(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();
        $parent = Comment::factory()->for($task)->create();
        $reply = Comment::factory()->for($task)->create(['parent_id' => $parent->id]);
        $notification = new CommentReply($task, $reply, User::factory()->create());

        $this->assertSame(['database', 'mail', 'broadcast'], $notification->via($user));

        $user->notificationPreferences()->create([
            'type' => NotificationType::CommentReply->value,
            'in_app' => true,
            'email' => false,
        ]);

        $this->assertSame(['database', 'broadcast'], $notification->via($user));

        $user->notificationPreferences()->updateOrCreate(
            ['type' => NotificationType::CommentReply->value],
            ['in_app' => false, 'email' => false],
        );

        $this->assertSame([], $notification->via($user));
    }

    public function test_due_reminder_preferences_control_the_notification_channels_per_scope(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();
        $dueSoon = new TaskDueReminder($task, TaskDueReminder::DueSoon);
        $overdue = new TaskDueReminder($task, TaskDueReminder::Overdue);

        $this->assertSame(['database', 'mail', 'broadcast'], $dueSoon->via($user));
        $this->assertSame(['database', 'mail', 'broadcast'], $overdue->via($user));

        $user->notificationPreferences()->create([
            'type' => NotificationType::TaskDueSoon->value,
            'in_app' => true,
            'email' => false,
        ]);

        $this->assertSame(['database', 'broadcast'], $dueSoon->via($user));
        $this->assertSame(['database', 'mail', 'broadcast'], $overdue->via($user));

        $user->notificationPreferences()->create([
            'type' => NotificationType::TaskDueOverdue->value,
            'in_app' => false,
            'email' => false,
        ]);

        $this->assertSame([], $overdue->via($user));
        $this->assertSame(['database', 'broadcast'], $dueSoon->via($user));
    }

    public function test_guest_cannot_manage_notification_preferences(): void
    {
        $this->getJson('/api/v1/notifications/preferences')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/preferences/task_assigned', ['email' => false])
            ->assertUnauthorized();
    }

    public function test_preferences_are_isolated_per_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', ['email' => false])
            ->assertOk();

        Sanctum::actingAs($secondUser);

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'task_assigned')
            ->assertJsonPath('data.0.in_app', true)
            ->assertJsonPath('data.0.email', true);
    }

    public function test_preferences_do_not_expose_another_users_mutes(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $otherUser->notificationMutes()->create([
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('mutes', []);
    }

    public function test_channel_values_must_be_boolean(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/notifications/preferences/task_assigned', [
            'email' => 'not-a-boolean',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    private function taskInWorkspace(): Task
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();

        return Task::factory()->for($list, 'list')->create();
    }
}
