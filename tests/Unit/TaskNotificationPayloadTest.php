<?php

namespace Tests\Unit;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AttachmentAdded;
use App\Notifications\CommentReply;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskDueReminder;
use App\NotificationType;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskNotificationPayloadTest extends TestCase
{
    public function test_task_assigned_payload(): void
    {
        $task = $this->task(10, 'Revisar el PR');
        $actor = new User(['name' => 'Ana']);
        $notification = new TaskAssigned($task, $actor);

        $this->assertSame(['database', 'mail', 'broadcast'], $notification->via($actor));
        $this->assertSame([
            'type' => 'task_assigned',
            'task_id' => 10,
            'title' => 'Revisar el PR',
            'actor' => 'Ana',
        ], $notification->toDatabase($actor));
        $this->assertSame('Te asignaron una tarea', $notification->toMail($actor)->subject);
    }

    public function test_task_assigned_broadcast_payload_matches_the_web_client_shape(): void
    {
        $task = $this->task(10, 'Revisar el PR');
        $actor = new User(['name' => 'Ana']);
        $notification = new TaskAssigned($task, $actor);
        $notification->id = 'notification-1';

        $payload = $notification->toDatabase($actor);
        $broadcast = $notification->broadcastWith();

        $this->assertSame($payload, $notification->toBroadcast($actor));
        $this->assertSame('notification-1', $broadcast['id']);
        $this->assertSame('TaskAssigned', $broadcast['type']);
        $this->assertSame($payload, $broadcast['payload']);
        $this->assertNull($broadcast['read_at']);
        $this->assertFalse($broadcast['is_read']);
    }

    public function test_notification_types_match_their_preference_keys(): void
    {
        $task = $this->task(10, 'Tarea');
        $comment = new Comment(['body' => 'Comentario']);
        $attachment = new Attachment(['name' => 'reporte.pdf', 'mime_type' => 'application/pdf']);
        $actor = new User(['name' => 'Ana']);

        $this->assertSame(
            NotificationType::TaskAssigned,
            (new TaskAssigned($task, $actor))->notificationType(),
        );
        $this->assertSame(
            NotificationType::TaskCommented,
            (new TaskCommented($task, $comment, $actor))->notificationType(),
        );
        $this->assertSame(
            NotificationType::CommentReply,
            (new CommentReply($task, $comment, $actor))->notificationType(),
        );
        $this->assertSame(
            NotificationType::TaskDueSoon,
            (new TaskDueReminder($task, TaskDueReminder::DueSoon))->notificationType(),
        );
        $this->assertSame(
            NotificationType::TaskDueOverdue,
            (new TaskDueReminder($task, TaskDueReminder::Overdue))->notificationType(),
        );
        $this->assertSame(
            NotificationType::AttachmentAdded,
            (new AttachmentAdded($task, $attachment, $actor))->notificationType(),
        );
    }

    public function test_attachment_added_payload_and_subject(): void
    {
        $task = $this->task(10, 'Tarea');
        $attachment = new Attachment(['name' => 'reporte.pdf', 'mime_type' => 'application/pdf']);
        $attachment->setAttribute('id', 3);

        $notification = new AttachmentAdded($task, $attachment, new User(['name' => 'Ana']));
        $payload = $notification->toDatabase(new User);

        $this->assertSame('attachment_added', $payload['type']);
        $this->assertSame(10, $payload['task_id']);
        $this->assertSame(3, $payload['attachment_id']);
        $this->assertSame('reporte.pdf', $payload['attachment_name']);
        $this->assertSame('application/pdf', $payload['mime_type']);
        $this->assertSame('Ana', $payload['actor']);
        $this->assertSame('Nuevo archivo adjunto en una tarea', $notification->toMail(new User)->subject);
    }

    public function test_task_commented_payload_limits_the_excerpt(): void
    {
        $body = str_repeat('a', 130);
        $comment = new Comment(['body' => $body]);
        $comment->setAttribute('id', 7);

        $payload = (new TaskCommented($this->task(10, 'Tarea'), $comment, new User(['name' => 'Ana'])))
            ->toDatabase(new User);

        $this->assertSame('comment_added', $payload['type']);
        $this->assertSame(10, $payload['task_id']);
        $this->assertSame(7, $payload['comment_id']);
        $this->assertSame(Str::limit($body, 120), $payload['excerpt']);
        $this->assertSame('Ana', $payload['actor']);
    }

    public function test_comment_reply_payload_includes_the_parent_comment(): void
    {
        $comment = new Comment(['body' => 'Una respuesta', 'parent_id' => 3]);
        $comment->setAttribute('id', 8);

        $payload = (new CommentReply($this->task(10, 'Tarea'), $comment, new User(['name' => 'Ana'])))
            ->toDatabase(new User);

        $this->assertSame('comment_reply', $payload['type']);
        $this->assertSame(8, $payload['comment_id']);
        $this->assertSame(3, $payload['parent_id']);
    }

    public function test_due_soon_reminder_payload_and_subject(): void
    {
        $notification = new TaskDueReminder($this->task(10, 'Tarea', '2026-10-21'), TaskDueReminder::DueSoon);

        $payload = $notification->toDatabase(new User);

        $this->assertSame('task_due_soon', $payload['type']);
        $this->assertSame('2026-10-21', $payload['due_date']);
        $this->assertSame('due_soon', $payload['scope']);
        $this->assertSame('Tienes una tarea por vencer', $notification->toMail(new User)->subject);
    }

    public function test_overdue_reminder_payload_and_subject(): void
    {
        $notification = new TaskDueReminder($this->task(10, 'Tarea', '2026-10-19'), TaskDueReminder::Overdue);

        $payload = $notification->toDatabase(new User);

        $this->assertSame('task_overdue', $payload['type']);
        $this->assertSame('overdue', $payload['scope']);
        $this->assertSame('Tienes una tarea vencida', $notification->toMail(new User)->subject);
    }

    private function task(int $id, string $title, ?string $dueDate = null): Task
    {
        $task = new Task([
            'title' => $title,
            'due_date' => $dueDate,
        ]);
        $task->setAttribute('id', $id);

        return $task;
    }
}
