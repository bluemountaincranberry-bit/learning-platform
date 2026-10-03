<?php

namespace App\Http\Requests\Api\Admin;

use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminLexemeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_id' => ['sometimes', 'integer', 'exists:grammar_rules,id'],
            'content_id' => ['sometimes', 'integer', 'exists:contents,id'],
            'language' => ['sometimes', 'string', 'max:8'],
            'status' => ['sometimes', 'string', Rule::in(Lexeme::STATUSES)],
            'level' => ['sometimes', 'nullable', 'string', 'max:4'],
            'part_of_speech' => ['sometimes', 'nullable', 'string', 'max:32'],
            'coverage_state' => ['sometimes', 'string', Rule::in(['needs_examples', 'needs_rules', 'needs_content', 'covered'])],
            'linked_content' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('linked_content')) {
            $this->merge([
                'linked_content' => filter_var($this->input('linked_content'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
