<?php

namespace App\Http\Requests\Api\Admin;

use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminGrammarTopicStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('grammar_topics', 'slug')],
            'language' => ['sometimes', 'string', 'max:8'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(GrammarTopic::STATUSES)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
