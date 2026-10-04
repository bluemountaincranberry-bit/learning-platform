<?php

namespace App\Http\Requests\Api;

use App\Modules\Content\Rules\NewYoutubeVideo;
use Illuminate\Foundation\Http\FormRequest;

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
                'max:255',
                new NewYoutubeVideo,
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
