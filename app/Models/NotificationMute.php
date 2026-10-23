<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'muteable_type', 'muteable_id'])]
class NotificationMute extends Model
{
    public const WorkspaceTarget = 'workspace';

    public const BoardTarget = 'board';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function muteable(): MorphTo
    {
        return $this->morphTo();
    }
}
