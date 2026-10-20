<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications_with_the_unread_count(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $actor = User::factory()->create();

        $first = $this->sendNotification($user, $task, $actor);
        $first->markAsRead();

        $this->travel(1)->seconds();

        $second = $this->sendNotification($user, $task, $actor);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.type', 'TaskAssigned')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('data.1.is_read', true);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->sendNotification($user, Task::factory()->create(), User::factory()->create());

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = $this->sendNotification($other, Task::factory()->create(), User::factory()->create());

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/'.$notification->id.'/read')->assertNotFound();
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $actor = User::factory()->create();

        $this->sendNotification($user, $task, $actor);
        $this->sendNotification($user, $task, $actor);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_guest_cannot_list_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    private function sendNotification(User $user, Task $task, User $actor): DatabaseNotification
    {
        $user->notifyNow(new TaskAssigned($task, $actor), ['database']);

        return $user->notifications()->latest('created_at')->firstOrFail();
    }
}
