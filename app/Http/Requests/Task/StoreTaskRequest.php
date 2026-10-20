<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use App\Models\User;
use App\TaskPriority;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Task::class, $this->route('list')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('assigned_to') || $this->input('assigned_to') === null) {
                    return;
                }

                $assignee = User::find($this->integer('assigned_to'));
                $workspace = $this->route('list')->board->workspace;

                if ($assignee !== null && ! $workspace->hasMember($assignee)) {
                    $validator->errors()->add('assigned_to', 'Solo puedes asignar tareas a miembros del workspace.');
                }
            },
        ];
    }
}
