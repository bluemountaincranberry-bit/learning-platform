<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Task 9.3: accept pending AI candidates on a content's latest analysis run.
 * Authorization (content.created_by === auth()->id()) is a policy check in
 * the controller, not here — this class only validates shape.
 */
class AcceptAiSuggestionsRequest extends FormRequest
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
            'accept_all' => ['sometimes', 'boolean'],
            'lexeme_candidate_ids' => ['sometimes', 'array'],
            'lexeme_candidate_ids.*' => ['integer'],
            'grammar_candidate_ids' => ['sometimes', 'array'],
            'grammar_candidate_ids.*' => ['integer'],
        ];
    }
}
