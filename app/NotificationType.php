<?php

namespace App;

enum NotificationType: string
{
    case TaskAssigned = 'task_assigned';
    case TaskCommented = 'comment_added';
    case CommentReply = 'comment_reply';
    case TaskDueSoon = 'task_due_soon';
    case TaskDueOverdue = 'task_overdue';
    case AttachmentAdded = 'attachment_added';

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Tarea asignada',
            self::TaskCommented => 'Nuevo comentario',
            self::CommentReply => 'Respuesta a tu comentario',
            self::TaskDueSoon => 'Tarea por vencer',
            self::TaskDueOverdue => 'Tarea vencida',
            self::AttachmentAdded => 'Nuevo adjunto',
        };
    }
}
