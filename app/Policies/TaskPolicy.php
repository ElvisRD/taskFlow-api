<?php

namespace App\Policies;

use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function create(User $user, BoardList $list): bool
    {
        return $list->board->workspace->hasMember($user);
    }

    public function view(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->list->board->workspace->hasMember($user);
    }

    public function delete(User $user, Task $task): bool
    {
        $workspace = $task->list->board->workspace;

        return $task->created_by === $user->getKey() || $workspace->isAdmin($user);
    }
}
