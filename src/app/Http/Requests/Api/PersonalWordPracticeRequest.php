<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class PersonalWordPracticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'dimension' => ['required', 'string', 'in:recognition,recall,production,listening,speaking'],
            'correct' => ['required', 'boolean'],
            'hint_used' => ['sometimes', 'boolean'],
        ];
    }
}
