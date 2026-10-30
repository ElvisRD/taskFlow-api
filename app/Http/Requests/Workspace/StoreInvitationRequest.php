<?php

namespace App\Http\Requests\Workspace;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\WorkspaceInvitationStatus;
use App\WorkspaceRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
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

                $email = $this->normalizedEmail();
                $workspace = $this->route('workspace');

                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();

                if ($user !== null && $workspace->hasMember($user)) {
                    $validator->errors()->add('email', 'El usuario ya pertenece a este workspace.');

                    return;
                }

                $hasPendingInvitation = WorkspaceInvitation::query()
                    ->where('workspace_id', $workspace->getKey())
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('status', WorkspaceInvitationStatus::Pending->value)
                    ->exists();

                if ($hasPendingInvitation) {
                    $validator->errors()->add('email', 'Ya existe una invitación pendiente para este correo. Puedes reenviarla.');
                }
            },
        ];
    }

    public function normalizedEmail(): string
    {
        return strtolower(trim((string) $this->input('email')));
    }
}
