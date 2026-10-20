<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'role' => $this->when(
                $this->resource->pivot !== null,
                fn () => $this->pivot->role->value,
            ),
            'members_count' => $this->whenCounted('members'),
            'boards_count' => $this->whenCounted('boards'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
