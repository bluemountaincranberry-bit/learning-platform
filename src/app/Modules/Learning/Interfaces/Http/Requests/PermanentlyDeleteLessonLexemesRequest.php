<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermanentlyDeleteLessonLexemesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function authorize(): bool
    {
        // The controller authorizes the lesson update policy after route binding.
        return true;
    }
}
