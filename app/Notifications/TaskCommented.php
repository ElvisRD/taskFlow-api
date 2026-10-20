<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsTaskNotification;
use App\Notifications\Concerns\IncludesTaskContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TaskCommented extends Notification implements ShouldQueue
{
    use BroadcastsTaskNotification, IncludesTaskContext, Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public Comment $comment,
        public User $actor,
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
            'type' => 'comment_added',
            'task_id' => $this->task->getKey(),
            'comment_id' => $this->comment->getKey(),
            'title' => $this->task->title,
            'actor' => $this->actor->name,
            'excerpt' => Str::limit($this->comment->body, 120),
            ...$this->taskContext(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nuevo comentario en una tarea')
            ->line($this->actor->name.' comentó en "'.$this->task->title.'":')
            ->line(Str::limit($this->comment->body, 200));
    }
}
