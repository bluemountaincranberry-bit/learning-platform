<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:coached,mock'],
            'question_ids' => ['sometimes', 'array', 'max:30'],
            'question_ids.*' => ['integer', 'distinct'],
            'question_count' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'topic_id' => ['nullable', 'integer'],
            'focus' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['sometimes', 'in:any,beginner,intermediate,advanced'],
        ];
    }
}
