<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isDemoAccount();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['current_password', 'password'])) {
                    return;
                }

                $user = $this->user();

                if ($user->password === null) {
                    return;
                }

                if (! $this->filled('current_password')) {
                    $validator->errors()->add('current_password', 'Debes indicar tu contraseña actual.');

                    return;
                }

                if (! Hash::check($this->string('current_password')->toString(), $user->password)) {
                    $validator->errors()->add('current_password', 'La contraseña actual no es correcta.');
                }
            },
        ];
    }
}
