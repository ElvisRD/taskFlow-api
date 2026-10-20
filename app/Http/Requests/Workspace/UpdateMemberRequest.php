<?php

namespace App\Http\Requests\Workspace;

use App\WorkspaceRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('workspace')) ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('workspace')->isOwnedBy($this->route('user'))) {
                    $validator->errors()->add('role', 'El propietario del workspace siempre es administrador.');
                }
            },
        ];
    }
}
