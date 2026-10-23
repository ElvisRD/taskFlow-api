<?php

namespace App\Http\Resources;

use App\Models\Board;
use App\Models\NotificationMute;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationMuteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->muteable_type === Workspace::class
                ? NotificationMute::WorkspaceTarget
                : NotificationMute::BoardTarget,
            'target_id' => $this->muteable_id,
            'workspace_id' => $this->when(
                $this->relationLoaded('muteable') && $this->muteable instanceof Board,
                fn (): int => $this->muteable->workspace_id,
            ),
            'name' => $this->whenLoaded('muteable', fn (): ?string => $this->muteable?->name),
            'created_at' => $this->created_at,
        ];
    }
}
