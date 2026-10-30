<?php

namespace App\Models;

use App\WorkspaceInvitationStatus;
use App\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'workspace_id',
    'invited_by',
    'accepted_by',
    'email',
    'role',
    'token',
    'status',
    'expires_at',
    'accepted_at',
    'revoked_at',
])]
#[Hidden(['token'])]
class WorkspaceInvitation extends Model
{
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->status === WorkspaceInvitationStatus::Pending
            && $this->expires_at->isPast();
    }

    public function effectiveStatus(): WorkspaceInvitationStatus
    {
        return $this->isExpired()
            ? WorkspaceInvitationStatus::Expired
            : $this->status;
    }

    public function isPending(): bool
    {
        return $this->effectiveStatus() === WorkspaceInvitationStatus::Pending;
    }

    public function markAccepted(User $user): void
    {
        $this->forceFill([
            'status' => WorkspaceInvitationStatus::Accepted,
            'accepted_by' => $user->getKey(),
            'accepted_at' => now(),
            'revoked_at' => null,
        ])->save();
    }

    public function markRevoked(): void
    {
        $this->forceFill([
            'status' => WorkspaceInvitationStatus::Revoked,
            'revoked_at' => now(),
        ])->save();
    }

    public function markExpired(): void
    {
        $this->forceFill(['status' => WorkspaceInvitationStatus::Expired])->save();
    }

    public function regenerateToken(): string
    {
        $token = self::generateToken();

        $this->forceFill([
            'token' => self::hashToken($token),
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('auth.invitations.expire', 10080)),
            'accepted_at' => null,
            'accepted_by' => null,
            'revoked_at' => null,
        ])->save();

        return $token;
    }

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'status' => WorkspaceInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
