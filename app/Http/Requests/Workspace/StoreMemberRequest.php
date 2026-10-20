<?php

namespace App\Http\Requests\Workspace;

use App\Models\User;
use App\WorkspaceRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('workspace')) ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $email = $this->string('email')->lower()->toString();
                $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

                if ($user === null) {
                    $validator->errors()->add('email', 'No existe un usuario registrado con ese correo.');

                    return;
                }

                if ($this->route('workspace')->hasMember($user)) {
                    $validator->errors()->add('email', 'El usuario ya pertenece a este workspace.');
                }
            },
        ];
    }
}
