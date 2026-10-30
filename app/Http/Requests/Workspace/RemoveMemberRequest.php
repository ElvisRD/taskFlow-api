<?php

namespace App\Http\Requests\Workspace;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RemoveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('workspace')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reassign_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('reassign_to') || $this->input('reassign_to') === null) {
                    return;
                }

                $workspace = $this->route('workspace');
                $member = $this->route('user');

                if ((int) $this->input('reassign_to') === (int) $member->getKey()) {
                    $validator->errors()->add('reassign_to', 'No puedes reasignar las tareas al miembro que estás eliminando.');

                    return;
                }

                $assignee = User::find($this->integer('reassign_to'));

                if ($assignee === null || ! $workspace->hasMember($assignee)) {
                    $validator->errors()->add('reassign_to', 'Solo puedes reasignar tareas a miembros del workspace.');
                }
            },
        ];
    }
}
