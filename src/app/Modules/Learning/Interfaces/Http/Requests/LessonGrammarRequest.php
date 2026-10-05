<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LessonGrammarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? ['required'] : ['sometimes', 'required'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'example' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'example_translation' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
