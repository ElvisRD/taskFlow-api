<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->isAdmin($user);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->isAdmin($user);
    }
}
