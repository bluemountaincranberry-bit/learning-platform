<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportYoutubeTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>|string> */
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
            'segments' => ['required', 'array', 'min:1', 'max:10000'],
            'segments.*.start_ms' => ['required', 'integer', 'min:0'],
            'segments.*.end_ms' => ['nullable', 'integer', 'min:0'],
            'segments.*.text' => ['required', 'string', 'max:5000'],
        ];
    }
}
