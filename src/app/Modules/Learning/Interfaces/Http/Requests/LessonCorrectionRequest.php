<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LessonCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? ['required'] : ['sometimes', 'required'];

        return [
            'original_text' => [...$required, 'string', 'max:10000'],
            'corrected_text' => [...$required, 'string', 'max:10000'],
            'explanation' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
