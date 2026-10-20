<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view the comments of the task.
     */
    public function viewAny(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can comment on the task.
     */
    public function create(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        $workspace = $comment->task->list->board->workspace;

        return $comment->user_id === $user->getKey() || $workspace->isAdmin($user);
    }
}
