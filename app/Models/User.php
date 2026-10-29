<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\NotificationType;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function notificationMutes(): HasMany
    {
        return $this->hasMany(NotificationMute::class);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function createApiToken(string $name): NewAccessToken
    {
        $expiration = (int) config('sanctum.expiration');

        return $this->createToken(
            $name,
            ['*'],
            $expiration > 0 ? now()->addMinutes($expiration) : null,
        );
    }

    /**
     * Resolve the delivery channels for a notification, honouring the
     * user's per-type preferences and workspace/board mutes.
     *
     * @return array<int, string>
     */
    public function notificationChannels(NotificationType $type, ?Task $task = null): array
    {
        if (! $this->exists) {
            return ['database', 'mail', 'broadcast'];
        }

        if ($task !== null && $this->mutesNotificationsFor($task)) {
            return [];
        }

        $preference = $this->notificationPreferences()
            ->where('type', $type->value)
            ->first();

        $inApp = $preference?->in_app ?? true;
        $email = $preference?->email ?? true;

        $channels = [];

        if ($inApp) {
            $channels[] = 'database';
        }

        if ($email) {
            $channels[] = 'mail';
        }

        if ($inApp) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    private function mutesNotificationsFor(Task $task): bool
    {
        $list = $task->list;

        if ($list === null) {
            return false;
        }

        $boardId = $list->board_id;
        $workspaceId = $list->board?->workspace_id;

        return $this->notificationMutes()
            ->where(function (Builder $query) use ($boardId, $workspaceId): void {
                if ($workspaceId !== null) {
                    $query->orWhere(function (Builder $query) use ($workspaceId): void {
                        $query->where('muteable_type', Workspace::class)
                            ->where('muteable_id', $workspaceId);
                    });
                }

                if ($boardId !== null) {
                    $query->orWhere(function (Builder $query) use ($boardId): void {
                        $query->where('muteable_type', Board::class)
                            ->where('muteable_id', $boardId);
                    });
                }
            })
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
