<?php

namespace App\Models;

use App\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(name: 'user_workspace', incrementing: true)]
class Membership extends Pivot
{
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
        ];
    }
}
