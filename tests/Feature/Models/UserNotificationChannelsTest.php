<?php

namespace Tests\Feature\Models;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_preferences_receives_every_channel(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();

        $this->assertSame(
            ['database', 'mail', 'broadcast'],
            $user->notificationChannels(NotificationType::TaskAssigned, $task),
        );
    }

    public function test_in_app_can_be_disabled_without_disabling_email(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();

        $this->preference($user, NotificationType::TaskAssigned, inApp: false, email: true);

        $this->assertSame(
            ['mail'],
            $user->notificationChannels(NotificationType::TaskAssigned, $task),
        );
    }

    public function test_email_can_be_disabled_without_disabling_in_app(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();

        $this->preference($user, NotificationType::TaskAssigned, inApp: true, email: false);

        $this->assertSame(
            ['database', 'broadcast'],
            $user->notificationChannels(NotificationType::TaskAssigned, $task),
        );
    }

    public function test_both_channels_can_be_disabled_for_a_type(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();

        $this->preference($user, NotificationType::TaskAssigned, inApp: false, email: false);

        $this->assertSame(
            [],
            $user->notificationChannels(NotificationType::TaskAssigned, $task),
        );
    }

    public function test_preferences_only_apply_to_their_notification_type(): void
    {
        $user = User::factory()->create();
        $task = $this->taskInWorkspace();

        $this->preference($user, NotificationType::TaskCommented, inApp: false, email: false);

        $this->assertSame(
            ['database', 'mail', 'broadcast'],
            $user->notificationChannels(NotificationType::TaskAssigned, $task),
        );
        $this->assertSame(
            [],
            $user->notificationChannels(NotificationType::TaskCommented, $task),
        );
    }

    public function test_mutes_are_isolated_per_user(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();
        $task = Task::factory()->for($list, 'list')->create();
        $mutedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $mutedUser->notificationMutes()->create([
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);

        $this->assertSame(
            [],
            $mutedUser->notificationChannels(NotificationType::TaskAssigned, $task),
        );
        $this->assertSame(
            ['database', 'mail', 'broadcast'],
            $otherUser->notificationChannels(NotificationType::TaskAssigned, $task),
        );
    }

    public function test_a_workspace_mute_overrides_enabled_preferences(): void
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();
        $task = Task::factory()->for($list, 'list')->create();
        $user = User::factory()->create();

        $this->preference($user, NotificationType::TaskAssigned, inApp: true, email: true);

        $user->notificationMutes()->create([
            'muteable_type' => Workspace::class,
            'muteable_id' => $workspace->id,
        ]);

        $this->assertSame(
            [],
            $user->notificationChannels(NotificationType::TaskAssigned, $task),
        );
    }

    private function preference(
        User $user,
        NotificationType $type,
        bool $inApp,
        bool $email,
    ): void {
        $user->notificationPreferences()->create([
            'type' => $type->value,
            'in_app' => $inApp,
            'email' => $email,
        ]);
    }

    private function taskInWorkspace(): Task
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();

        return Task::factory()->for($list, 'list')->create();
    }
}
