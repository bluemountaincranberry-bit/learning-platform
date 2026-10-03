<?php

namespace App\Http\Requests\Api\Admin;

use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminGrammarRuleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_id' => ['sometimes', 'integer', 'exists:grammar_topics,id'],
            'content_id' => ['sometimes', 'integer', 'exists:contents,id'],
            'language' => ['sometimes', 'string', 'max:8'],
            'status' => ['sometimes', 'string', Rule::in(GrammarRule::STATUSES)],
            'level' => ['sometimes', 'nullable', 'string', 'max:4'],
            'coverage_state' => ['sometimes', 'string', Rule::in(['needs_examples', 'needs_lexemes', 'needs_content', 'covered'])],
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
