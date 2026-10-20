<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    public function create(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    public function delete(User $user, Comment $comment): bool
    {
        $workspace = $comment->task->list->board->workspace;

        return $comment->user_id === $user->getKey() || $workspace->isAdmin($user);
    }
}
