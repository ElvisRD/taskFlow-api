<?php

namespace App\Notifications;

use App\Models\Comment;
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
use Illuminate\Support\Str;

class CommentReply extends Notification implements ShouldQueue
{
    use BroadcastsTaskNotification, IncludesTaskContext, Queueable, ResolvesNotificationChannels, SerializesModels;

    public function __construct(
        public Task $task,
        public Comment $comment,
        public User $actor,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::CommentReply;
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
            'type' => 'comment_reply',
            'task_id' => $this->task->getKey(),
            'comment_id' => $this->comment->getKey(),
            'parent_id' => $this->comment->parent_id,
            'title' => $this->task->title,
            'actor' => $this->actor->name,
            'excerpt' => Str::limit($this->comment->body, 120),
            ...$this->taskContext(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Respondieron tu comentario')
            ->line($this->actor->name.' respondió en "'.$this->task->title.'":')
            ->line(Str::limit($this->comment->body, 200));
    }
}
