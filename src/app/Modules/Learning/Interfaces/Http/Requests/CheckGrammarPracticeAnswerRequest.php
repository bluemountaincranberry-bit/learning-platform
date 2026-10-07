<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckGrammarPracticeAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'given' => ['nullable', 'string', 'max:500'],
            'show_answer' => ['sometimes', 'boolean'],
        ];
    }
}
