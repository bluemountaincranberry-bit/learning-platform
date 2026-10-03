<?php

namespace App\Http\Requests\Api\Admin;

use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminGrammarRuleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var GrammarRule|null $rule */
        $rule = $this->route('rule');

        return [
            'topic_id' => ['sometimes', 'integer', 'exists:grammar_topics,id'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('grammar_rules', 'slug')->ignore($rule?->id)],
            'language' => ['sometimes', 'string', 'max:8'],
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(GrammarRule::STATUSES)],
            'level' => ['sometimes', 'nullable', 'string', 'max:4'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'body' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'lexeme_ids' => ['sometimes', 'array'],
            'lexeme_ids.*' => ['integer', 'exists:lexemes,id', 'distinct'],
            'examples' => ['sometimes', 'array'],
            'examples.*.language' => ['sometimes', 'string', 'max:8'],
            'examples.*.example' => ['required_with:examples', 'string'],
            'examples.*.translation' => ['sometimes', 'nullable', 'string'],
            'examples.*.is_primary' => ['sometimes', 'boolean'],
            'examples.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
