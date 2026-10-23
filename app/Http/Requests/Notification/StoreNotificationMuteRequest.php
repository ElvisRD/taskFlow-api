<?php

namespace App\Http\Requests\Notification;

use App\Models\NotificationMute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationMuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_type' => [
                'required',
                Rule::in([NotificationMute::WorkspaceTarget, NotificationMute::BoardTarget]),
            ],
            'target_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
