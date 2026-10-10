<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApplyGrammarRuleEditorDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:12000'],
            'examples' => ['required', 'array', 'min:1', 'max:20'],
            'examples.*.id' => ['sometimes', 'integer', 'distinct'],
            'examples.*.language' => ['required', 'string', 'max:8'],
            'examples.*.example' => ['required', 'string', 'max:500'],
            'examples.*.translation' => ['nullable', 'string', 'max:500'],
            'examples.*.is_primary' => ['sometimes', 'boolean'],
            'examples.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
