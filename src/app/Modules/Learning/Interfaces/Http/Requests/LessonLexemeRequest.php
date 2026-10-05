<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use Illuminate\Foundation\Http\FormRequest;

class LessonLexemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? ['required'] : ['sometimes', 'required'];

        return [
            'text' => [...$required, 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', LessonLexemeCandidate::TYPES)],
            'level' => ['sometimes', 'nullable', 'string', 'max:4'],
            'translation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'example' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'example_translation' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
