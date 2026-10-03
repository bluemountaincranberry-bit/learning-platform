<?php

namespace App\Http\Requests\Api;

use App\Modules\Content\Domain\Models\Content;
use App\Contracts\Ai\AiAnalysisRunConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiProfileUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'ui_language' => ['sometimes', 'required', 'string', 'size:2'],
            'translation_language' => ['sometimes', 'nullable', 'string', 'max:8'],
            'daily_goal' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'current_level' => ['sometimes', 'nullable', Rule::in(Content::CEFR_LEVELS)],
            'learning_goal' => ['sometimes', 'nullable', Rule::in(['conversation', 'work', 'travel', 'exam', 'general'])],
            'ai_extraction_thoroughness' => ['sometimes', 'nullable', Rule::in(AiAnalysisRunConfig::THOROUGHNESS_LEVELS)],
        ];
    }
}
