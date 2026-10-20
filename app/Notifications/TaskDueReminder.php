<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Concerns\BroadcastsTaskNotification;
use App\Notifications\Concerns\IncludesTaskContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class TaskDueReminder extends Notification implements ShouldQueue
{
    use BroadcastsTaskNotification, IncludesTaskContext, Queueable, SerializesModels;

    public const DueSoon = 'due_soon';

    public const Overdue = 'overdue';

    public function __construct(
        public Task $task,
        public string $scope,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->notificationPayload();
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationPayload(): array
    {
        return [
            'type' => $this->scope === self::Overdue ? 'task_overdue' : 'task_due_soon',
            'task_id' => $this->task->getKey(),
            'title' => $this->task->title,
            'due_date' => $this->task->due_date?->toDateString(),
            'scope' => $this->scope,
            ...$this->taskContext(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->scope === self::Overdue ? 'Tienes una tarea vencida' : 'Tienes una tarea por vencer';

        $dueDate = $this->task->due_date?->toDateString() ?? 'sin fecha';

        return (new MailMessage)
            ->subject($subject)
            ->line('La tarea "'.$this->task->title.'" vence el '.$dueDate.'.');
    }
}
