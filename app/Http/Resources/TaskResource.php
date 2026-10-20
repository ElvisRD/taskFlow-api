<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dueDate = $this->due_date;

        return [
            'id' => $this->id,
            'board_list_id' => $this->board_list_id,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'priority' => $this->priority->value,
            'due_date' => $dueDate?->toDateString(),
            'completed_at' => $this->completed_at,
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $dueDate !== null && ! $this->isCompleted() && $dueDate->lt(today()),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
