<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeLevel;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartGrammarPracticeRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(GrammarPracticeLevel::class)],
            'count' => ['required', 'integer', Rule::in(GrammarPracticeService::COUNTS)],
            'exercise_ids' => ['sometimes', 'array', 'min:1', 'max:15'],
            'exercise_ids.*' => ['integer'],
        ];
    }
}
