<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportGrammarPracticeExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(GrammarPracticeLevel::class)],
            'round_exercise_ids' => ['sometimes', 'array', 'max:30'],
            'round_exercise_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
