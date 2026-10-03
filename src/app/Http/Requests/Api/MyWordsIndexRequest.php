<?php

namespace App\Http\Requests\Api;

use App\Modules\Content\Domain\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MyWordsIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in(['all', 'in_learning', 'known', 'new'])],
            'language' => ['sometimes', 'string', 'max:10'],
            'level' => ['sometimes', 'string', Rule::in(Content::CEFR_LEVELS)],
            'content_id' => ['sometimes', 'integer', 'exists:contents,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
