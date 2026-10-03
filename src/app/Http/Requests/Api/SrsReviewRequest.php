<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SrsReviewRequest extends FormRequest
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
            'card_id' => ['required', 'integer', 'exists:srs_cards,id'],
            'grade' => ['required', 'integer', 'between:1,5'],
            'content_lexeme_id' => ['sometimes', 'nullable', 'integer', 'exists:content_lexemes,id'],
            'transcript_segment_id' => ['sometimes', 'nullable', 'integer', 'exists:transcript_segments,id'],
            'exercise_type' => ['sometimes', 'string', 'max:32'],
            'error_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hint_used' => ['sometimes', 'boolean'],
            'answer_metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
