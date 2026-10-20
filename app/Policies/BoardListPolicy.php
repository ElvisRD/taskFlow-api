<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;

class BoardListPolicy
{
    public function create(User $user, Board $board): bool
    {
        return $board->workspace->isAdmin($user);
    }

    public function view(User $user, BoardList $list): bool
    {
        return $list->board->workspace->hasMember($user);
    }

    public function update(User $user, BoardList $list): bool
    {
        return $list->board->workspace->isAdmin($user);
    }

    public function delete(User $user, BoardList $list): bool
    {
        return $list->board->workspace->isAdmin($user);
    }
}
