<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SelfCheckSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content_id' => ['required', 'integer', 'exists:contents,id'],
            'operation_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.content_lexeme_id' => ['required', 'integer'],
            'answers.*.known' => ['required', 'boolean'],
            'answers.*.hint_used' => ['sometimes', 'boolean'],
            'answers.*.error_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'answers.*.exercise_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'answers.*.transcript_segment_id' => ['sometimes', 'nullable', 'integer', 'exists:transcript_segments,id'],
            'answers.*.retry_id' => ['sometimes', 'nullable', 'integer', 'exists:learning_retries,id'],
            'answers.*.exercise_type' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
