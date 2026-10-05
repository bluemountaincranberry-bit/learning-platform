<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LessonFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lesson_date' => ['sometimes', 'nullable', 'date'],
            'teacher' => ['sometimes', 'nullable', 'string', 'max:255'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'max:8', 'regex:/^[a-z]{2,3}(?:-[A-Z]{2})?$/'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'homework' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
