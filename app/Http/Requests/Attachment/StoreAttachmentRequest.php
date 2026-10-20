<?php

namespace App\Http\Requests\Attachment;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Attachment::class, $this->route('task')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,png,jpg,jpeg,gif,webp,zip',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'El archivo no puede superar los 10 MB.',
            'file.mimes' => 'El tipo de archivo no está permitido.',
        ];
    }
}
