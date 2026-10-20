<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;

class BoardListPolicy
{
    /**
     * Determine whether the user can create lists inside the board.
     */
    public function create(User $user, Board $board): bool
    {
        return $board->workspace->isAdmin($user);
    }

    /**
     * Determine whether the user can view the list.
     */
    public function view(User $user, BoardList $list): bool
    {
        return $list->board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can update the list.
     */
    public function update(User $user, BoardList $list): bool
    {
        return $list->board->workspace->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the list.
     */
    public function delete(User $user, BoardList $list): bool
    {
        return $list->board->workspace->isAdmin($user);
    }
}
