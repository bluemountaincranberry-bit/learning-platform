<?php

namespace App\Http\Requests\Api\Admin;

use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminLexemeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Lexeme|null $lexeme */
        $lexeme = $this->route('lexeme');

        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('lexemes', 'slug')->ignore($lexeme?->id)],
            'language' => ['sometimes', 'string', 'max:8'],
            'lemma' => ['sometimes', 'string', 'max:255'],
            'normalized_lemma' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('lexemes', 'normalized_lemma')
                    ->ignore($lexeme?->id)
                    ->where(fn ($query) => $query->where('language', $this->input('language', $lexeme?->language ?? 'en'))),
            ],
            'part_of_speech' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'string', Rule::in(Lexeme::STATUSES)],
            'level' => ['sometimes', 'nullable', 'string', 'max:4'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'rule_ids' => ['sometimes', 'array'],
            'rule_ids.*' => ['integer', 'exists:grammar_rules,id', 'distinct'],
            'examples' => ['sometimes', 'array'],
            'examples.*.language' => ['sometimes', 'string', 'max:8'],
            'examples.*.example' => ['required_with:examples', 'string'],
            'examples.*.translation' => ['sometimes', 'nullable', 'string'],
            'examples.*.is_primary' => ['sometimes', 'boolean'],
            'examples.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'associations' => ['sometimes', 'array'],
            'associations.*.related_lexeme_id' => ['required_with:associations', 'integer', 'exists:lexemes,id', 'distinct'],
            'associations.*.type' => ['sometimes', 'string', 'max:32'],
            'associations.*.note' => ['sometimes', 'nullable', 'string'],
            'associations.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('lemma') || $this->filled('normalized_lemma')) {
            $this->merge([
                'normalized_lemma' => strtolower((string) ($this->input('normalized_lemma') ?: $this->input('lemma'))),
            ]);
        }
    }
}
