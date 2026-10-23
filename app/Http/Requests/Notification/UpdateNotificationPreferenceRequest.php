<?php

namespace App\Http\Requests\Notification;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'in_app' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['in_app', 'email'])) {
                    $validator->errors()->add('in_app', 'Indica al menos un canal de notificación.');
                }
            },
        ];
    }
}
