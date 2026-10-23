<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'in_app' => (bool) $this->in_app,
            'email' => (bool) $this->email,
        ];
    }
}
