<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsTaskNotification;
use App\Notifications\Concerns\IncludesTaskContext;
use App\Notifications\Concerns\ResolvesNotificationChannels;
use App\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class TaskAssigned extends Notification implements ShouldQueue
{
    use BroadcastsTaskNotification, IncludesTaskContext, Queueable, ResolvesNotificationChannels, SerializesModels;

    public function __construct(
        public Task $task,
        public User $actor,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::TaskAssigned;
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
            'type' => 'task_assigned',
            'task_id' => $this->task->getKey(),
            'title' => $this->task->title,
            'actor' => $this->actor->name,
            ...$this->taskContext(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Te asignaron una tarea')
            ->line($this->actor->name.' te asignó la tarea "'.$this->task->title.'".');
    }
}
