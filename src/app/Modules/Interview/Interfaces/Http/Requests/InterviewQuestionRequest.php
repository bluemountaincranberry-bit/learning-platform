<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $requiredOnCreate = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'topic_id' => ['nullable', 'integer', 'exists:interview_topics,id'],
            'prompt_en' => [$requiredOnCreate, 'string', 'max:5000'],
            'prompt_ru' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'preparation_state' => ['sometimes', 'in:unpracticed,needs_practice,confident'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'answers' => ['sometimes', 'array'],
            'answers.short' => ['sometimes', 'array'],
            'answers.full' => ['sometimes', 'array'],
            'answers.*.en' => ['nullable', 'string', 'max:12000'],
            'answers.*.ru' => ['nullable', 'string', 'max:12000'],
        ];
    }
}
