<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitYoutubeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'source_url' => [
                'required',
                'url',
                'regex:/youtube\.com|youtu\.be/',
                Rule::unique('contents', 'source_url')->where(
                    fn ($query) => $query
                        ->where('created_by', $this->user()->id)
                        ->where('origin', 'user-submitted')
                ),
            ],
            'language' => ['required', 'string', 'size:2'],
            'level' => ['nullable', 'string', 'max:4'],
        ];
    }
}
