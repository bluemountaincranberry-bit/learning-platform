<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TrainingSelectedLexemesRequest extends FormRequest
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
            'lexeme_ids' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function lexemeIds(): array
    {
        return collect(explode(',', (string) $this->validated('lexeme_ids')))
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
