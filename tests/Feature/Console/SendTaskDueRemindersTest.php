<?php

namespace Tests\Feature\Console;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskDueReminder;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendTaskDueRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_due_soon_and_overdue_tasks_only_once(): void
    {
        Notification::fake();

        [$workspace, $list] = $this->workspaceWithList();
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);

        $dueSoon = Task::factory()->for($list, 'list')->create([
            'assigned_to' => $assignee->id,
            'due_date' => today()->addDay(),
        ]);
        $overdue = Task::factory()->for($list, 'list')->create([
            'assigned_to' => $assignee->id,
            'due_date' => today()->subDay(),
        ]);
        $completed = Task::factory()->for($list, 'list')->completed()->create([
            'assigned_to' => $assignee->id,
            'due_date' => today()->subDay(),
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $assignee,
            TaskDueReminder::class,
            fn (TaskDueReminder $notification): bool => $notification->task->is($dueSoon)
                && $notification->scope === TaskDueReminder::DueSoon,
        );
        Notification::assertSentTo(
            $assignee,
            TaskDueReminder::class,
            fn (TaskDueReminder $notification): bool => $notification->task->is($overdue)
                && $notification->scope === TaskDueReminder::Overdue,
        );

        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $dueSoon->id,
            'type' => TaskDueReminder::DueSoon,
        ]);
        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $overdue->id,
            'type' => TaskDueReminder::Overdue,
        ]);
        $this->assertDatabaseMissing('task_reminders', ['task_id' => $completed->id]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertSentToTimes($assignee, TaskDueReminder::class, 2);
    }

    public function test_it_ignores_unassigned_tasks(): void
    {
        Notification::fake();

        [$workspace, $list] = $this->workspaceWithList();

        Task::factory()->for($list, 'list')->create([
            'assigned_to' => null,
            'due_date' => today()->subDay(),
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        $this->assertDatabaseCount('task_reminders', 0);
        Notification::assertNothingSent();
    }

    public function test_changing_the_due_date_resets_previous_reminders(): void
    {
        Notification::fake();

        [$workspace, $list] = $this->workspaceWithList();
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($list, 'list')->create([
            'assigned_to' => $assignee->id,
            'due_date' => today()->subDay(),
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();
        $this->assertDatabaseHas('task_reminders', ['task_id' => $task->id]);

        Sanctum::actingAs($workspace->owner);
        $this->patchJson('/api/v1/tasks/'.$task->id, [
            'due_date' => today()->addDays(3)->toDateString(),
        ])->assertOk();

        $this->assertDatabaseMissing('task_reminders', ['task_id' => $task->id]);
    }

    private function workspaceWithList(): array
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();

        return [$workspace, $list];
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
