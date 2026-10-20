<?php

namespace App\Http\Requests\BoardList;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReorderBoardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('board')) ?? false;
    }

    public function rules(): array
    {
        return [
            'lists' => ['present', 'array'],
            'lists.*' => ['integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('lists') || $validator->errors()->has('lists.*')) {
                    return;
                }

                $expected = $this->route('board')->lists()->pluck('id')->all();
                $given = array_map('intval', $this->input('lists', []));

                sort($expected);
                sort($given);

                if ($expected !== $given) {
                    $validator->errors()->add('lists', 'Debes enviar todas las listas del tablero exactamente una vez.');
                }
            },
        ];
    }
}
