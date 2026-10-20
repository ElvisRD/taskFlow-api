<?php

namespace App\Http\Requests\BoardList;

use App\Models\BoardList;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [BoardList::class, $this->route('board')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
