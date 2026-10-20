<?php

namespace App\Http\Requests\BoardList;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('list')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
