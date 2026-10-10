<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'voice_audio' => ['nullable', 'file', 'mimes:webm,mp4,m4a,ogg,wav,mpeg,mpga', 'max:10240'],
            'voice_audio_keep_forever' => ['nullable', 'boolean'],
            'transcription_provider' => ['nullable', 'in:openai,local_whisper'],
            'transcription_language' => ['nullable', 'in:en,ru'],
        ];
    }
}
