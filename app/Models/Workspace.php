<?php

namespace App\Models;

use App\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'slug', 'description'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * Get the user that owns the workspace.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the boards that belong to the workspace.
     *
     * @return HasMany<Board, $this>
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    /**
     * Get the users that are members of the workspace.
     *
     * @return BelongsToMany<User, $this, Membership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Determine whether the user owns the workspace.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user is the owner or a member of the workspace.
     */
    public function hasMember(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine whether the user can manage the workspace content and members.
     */
    public function isAdmin(User $user): bool
    {
        return match ($this->roleOf($user)) {
            WorkspaceRole::Admin => true,
            WorkspaceRole::Member, null => false,
        };
    }

    /**
     * Get the role the user has inside the workspace.
     */
    public function roleOf(User $user): ?WorkspaceRole
    {
        if ($this->isOwnedBy($user)) {
            return WorkspaceRole::Admin;
        }

        $membership = $this->members()
            ->whereKey($user->getKey())
            ->first();

        return $membership?->pivot->role;
    }
}
