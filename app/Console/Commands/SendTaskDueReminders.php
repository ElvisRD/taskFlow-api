<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueReminder;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendTaskDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders';

    protected $description = 'Notify assignees about tasks due soon or overdue';

    public function handle(): int
    {
        $this->remind(TaskDueReminder::DueSoon, fn (Builder $query) => $query->whereDate('due_date', today()->addDay()));
        $this->remind(TaskDueReminder::Overdue, fn (Builder $query) => $query->whereDate('due_date', '<', today()));

        return self::SUCCESS;
    }

    private function remind(string $scope, Closure $constraint): void
    {
        Task::query()
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereNotNull('assigned_to')
            ->tap($constraint)
            ->with('assignee')
            ->whereDoesntHave('reminders', fn (Builder $query) => $query->where('type', $scope))
            ->chunkById(100, function ($tasks) use ($scope): void {
                foreach ($tasks as $task) {
                    $task->assignee?->notify(new TaskDueReminder($task, $scope));

                    $task->reminders()->create([
                        'type' => $scope,
                        'sent_at' => now(),
                    ]);
                }
            });
    }
}
