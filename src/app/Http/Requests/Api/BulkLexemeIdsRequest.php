<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared shape for the two bulk lexeme-action endpoints (task 7.5):
 * bulk-mark-learned and bulk-start-learning. Both accept the same
 * `ids: ContentLexeme.id[]` payload — the single-item endpoints
 * (mark-learned/unmark-learned/start-learning) already key off this same
 * id space via route-model binding, this just validates a batch of them.
 */
class BulkLexemeIdsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:content_lexemes,id', 'distinct'],
        ];
    }
}
