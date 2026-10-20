<?php

namespace App\Http\Requests\Board;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReorderBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workspace')) ?? false;
    }

    public function rules(): array
    {
        return [
            'boards' => ['present', 'array'],
            'boards.*' => ['integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('boards') || $validator->errors()->has('boards.*')) {
                    return;
                }

                $expected = $this->route('workspace')->boards()->pluck('id')->all();
                $given = array_map('intval', $this->input('boards', []));

                sort($expected);
                sort($given);

                if ($expected !== $given) {
                    $validator->errors()->add('boards', 'Debes enviar todos los tableros del workspace exactamente una vez.');
                }
            },
        ];
    }
}
