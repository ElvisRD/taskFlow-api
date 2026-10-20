<?php

namespace App\Http\Requests\Comment;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Comment::class, $this->route('task')]) ?? false;
    }

    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where(
                    fn ($query) => $query
                        ->where('task_id', $task->getKey())
                        ->whereNull('parent_id'),
                ),
            ],
        ];
    }
}
