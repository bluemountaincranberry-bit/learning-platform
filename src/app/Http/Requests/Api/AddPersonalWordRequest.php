<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddPersonalWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lemma' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'regex:/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/', 'max:8'],
        ];
    }
}
