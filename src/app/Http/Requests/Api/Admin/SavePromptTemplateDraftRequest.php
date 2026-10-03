<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SavePromptTemplateDraftRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'system_template' => ['required', 'string'],
            // Agent prompts intentionally have no separate user template;
            // keep the wire field present while allowing an empty value.
            'user_template' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
