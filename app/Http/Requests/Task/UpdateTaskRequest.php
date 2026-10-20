<?php

namespace App\Http\Requests\Task;

use App\Models\BoardList;
use App\Models\User;
use App\TaskPriority;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'board_list_id' => ['sometimes', 'integer', 'exists:board_lists,id'],
            'completed' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['board_list_id', 'assigned_to'])) {
                    return;
                }

                $task = $this->route('task');
                $destination = $this->input('board_list_id') !== null
                    ? BoardList::find($this->integer('board_list_id'))
                    : $task->list;

                if ($destination !== null && $destination->board_id !== $task->list->board_id) {
                    $validator->errors()->add('board_list_id', 'La lista destino debe pertenecer al mismo tablero.');
                }

                if ($this->input('assigned_to') === null || $destination === null) {
                    return;
                }

                $assignee = User::find($this->integer('assigned_to'));

                if ($assignee !== null && ! $destination->board->workspace->hasMember($assignee)) {
                    $validator->errors()->add('assigned_to', 'Solo puedes asignar tareas a miembros del workspace.');
                }
            },
        ];
    }
}
