<?php

namespace App\Models;

use App\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'in_app', 'email'])]
class NotificationPreference extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }
}
