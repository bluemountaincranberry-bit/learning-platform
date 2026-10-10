<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GrammarRuleEditorProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instruction' => ['required', 'string', 'min:2', 'max:1500'],
            'conversation' => ['present', 'array', 'max:12'],
            'conversation.*.role' => ['required', 'in:user,assistant'],
            'conversation.*.content' => ['required', 'string', 'max:2000'],
            'draft' => ['required', 'array'],
            'draft.title' => ['required', 'string', 'max:255'],
            'draft.summary' => ['nullable', 'string', 'max:2000'],
            'draft.body' => ['nullable', 'string', 'max:12000'],
            'draft.examples' => ['present', 'array', 'max:20'],
            'draft.examples.*.id' => ['sometimes', 'integer', 'distinct'],
            'draft.examples.*.language' => ['required', 'string', 'max:8'],
            'draft.examples.*.example' => ['required', 'string', 'max:500'],
            'draft.examples.*.translation' => ['nullable', 'string', 'max:500'],
            'draft.examples.*.is_primary' => ['sometimes', 'boolean'],
            'draft.examples.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
