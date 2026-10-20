<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    public function create(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $attachment->task->list->board->workspace->hasMember($user);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $workspace = $attachment->task->list->board->workspace;

        return $attachment->user_id === $user->getKey() || $workspace->isAdmin($user);
    }
}
