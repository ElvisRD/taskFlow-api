<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine whether the user can list the attachments of the task.
     */
    public function viewAny(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can upload attachments to the task.
     */
    public function create(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can download the attachment.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        return $attachment->task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        $workspace = $attachment->task->list->board->workspace;

        return $attachment->user_id === $user->getKey() || $workspace->isAdmin($user);
    }
}
