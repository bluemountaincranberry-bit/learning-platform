<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeOutcome;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteGrammarPracticeRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(GrammarPracticeLevel::class)],
            'content_id' => ['nullable', 'integer', 'exists:contents,id'],
            'replay' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.exercise_id' => ['required', 'integer'],
            'items.*.outcome' => ['nullable', Rule::enum(GrammarPracticeOutcome::class)],
            'items.*.ms' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
