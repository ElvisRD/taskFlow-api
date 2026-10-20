<?php

namespace App\Http\Requests\Board;

use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Board::class, $this->route('workspace')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
