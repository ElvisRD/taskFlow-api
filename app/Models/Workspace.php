<?php

namespace App\Models;

use App\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'slug', 'description'])]
class Workspace extends Model
{
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->getKey();
    }

    public function hasMember(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->members()->whereKey($user->getKey())->exists();
    }

    public function isAdmin(User $user): bool
    {
        return match ($this->roleOf($user)) {
            WorkspaceRole::Admin => true,
            WorkspaceRole::Member, null => false,
        };
    }

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
