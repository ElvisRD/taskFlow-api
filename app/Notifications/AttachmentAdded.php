<?php

namespace App\Notifications;

use App\Models\Attachment;
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

class AttachmentAdded extends Notification implements ShouldQueue
{
    use BroadcastsTaskNotification, IncludesTaskContext, Queueable, ResolvesNotificationChannels, SerializesModels;

    public function __construct(
        public Task $task,
        public Attachment $attachment,
        public User $actor,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::AttachmentAdded;
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
            'type' => 'attachment_added',
            'task_id' => $this->task->getKey(),
            'attachment_id' => $this->attachment->getKey(),
            'attachment_name' => $this->attachment->name,
            'mime_type' => $this->attachment->mime_type,
            'title' => $this->task->title,
            'actor' => $this->actor->name,
            ...$this->taskContext(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nuevo archivo adjunto en una tarea')
            ->line($this->actor->name.' adjuntó "'.$this->attachment->name.'" a la tarea "'.$this->task->title.'".');
    }
}
