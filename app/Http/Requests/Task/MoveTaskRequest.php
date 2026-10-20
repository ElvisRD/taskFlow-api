<?php

namespace App\Http\Requests\Task;

use App\Models\BoardList;
use App\Models\Task;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }

    public function rules(): array
    {
        return [
            'board_list_id' => ['required', 'integer', 'exists:board_lists,id'],
            'before_task_id' => ['nullable', 'integer', 'prohibits:after_task_id', Rule::exists('tasks', 'id')],
            'after_task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('board_list_id')) {
                    return;
                }

                $task = $this->route('task');
                $destination = BoardList::find($this->integer('board_list_id'));

                if ($destination === null) {
                    return;
                }

                if ($destination->board_id !== $task->list->board_id) {
                    $validator->errors()->add('board_list_id', 'La lista destino debe pertenecer al mismo tablero.');

                    return;
                }

                foreach (['before_task_id', 'after_task_id'] as $field) {
                    if (! $this->filled($field)) {
                        continue;
                    }

                    $referenceId = $this->integer($field);

                    if ($referenceId === $task->getKey()) {
                        $validator->errors()->add($field, 'No puedes usar la tarea como referencia de sí misma.');

                        continue;
                    }

                    $belongsToDestination = Task::query()
                        ->whereKey($referenceId)
                        ->where('board_list_id', $destination->getKey())
                        ->exists();

                    if (! $belongsToDestination) {
                        $validator->errors()->add($field, 'La tarea de referencia debe pertenecer a la lista destino.');
                    }
                }
            },
        ];
    }
}
