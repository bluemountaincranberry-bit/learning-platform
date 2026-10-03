<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TestRunPromptRequest extends FormRequest
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
            'system_template' => ['required', 'string'],
            'user_template' => ['required', 'string'],
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['string'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
