<?php

namespace App\Http\Requests\Api\Admin;

use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminGrammarTopicIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => ['sometimes', 'string', 'max:8'],
            'status' => ['sometimes', 'string', Rule::in(GrammarTopic::STATUSES)],
            'coverage_state' => ['sometimes', 'string', Rule::in(['empty', 'needs_coverage', 'covered'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:200'],
        ];
    }
}
