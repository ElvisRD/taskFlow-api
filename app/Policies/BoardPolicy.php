<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

class BoardPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function view(User $user, Board $board): bool
    {
        return $board->workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->isAdmin($user);
    }

    public function update(User $user, Board $board): bool
    {
        return $board->workspace->isAdmin($user);
    }

    public function delete(User $user, Board $board): bool
    {
        return $board->workspace->isAdmin($user);
    }
}
