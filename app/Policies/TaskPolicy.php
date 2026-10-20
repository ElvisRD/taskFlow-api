<?php

namespace App\Policies;

use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can create tasks inside the list.
     */
    public function create(User $user, BoardList $list): bool
    {
        return $list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        $workspace = $task->list->board->workspace;

        return $task->created_by === $user->getKey() || $workspace->isAdmin($user);
    }
}
