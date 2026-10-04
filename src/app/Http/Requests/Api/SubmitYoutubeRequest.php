<?php

namespace App\Http\Requests\Api;

use App\Modules\Content\Rules\NewYoutubeVideo;
use Illuminate\Foundation\Http\FormRequest;

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
                'max:255',
                new NewYoutubeVideo,
            ],
            'language' => ['required', 'string', 'size:2'],
            'level' => ['nullable', 'string', 'max:4'],
        ];
    }
}
