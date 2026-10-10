<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:16000'],
            // Task 6.1: set only on the one message a query-param entry point
            // (AskAiButton.vue) injects context into — observational only,
            // see the add_context_columns_to_agent_messages_table migration.
            'context_type' => ['nullable', 'string', 'in:content,grammar,lexeme'],
            'context_ref_id' => ['nullable', 'integer'],
            'context_label' => ['nullable', 'string', 'max:255'],
            'voice_audio' => ['nullable', 'file', 'mimes:webm,mp4,m4a,ogg,wav,mpeg,mpga', 'max:10240'],
            'transcription_provider' => ['nullable', 'in:openai,local_whisper'],
            'transcription_language' => ['nullable', 'in:en,ru'],
            'voice_audio_keep_forever' => ['nullable', 'boolean'],
        ];
    }
}
