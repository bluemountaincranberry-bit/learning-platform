<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ExerciseAttemptRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'content_id' => ['required', 'integer', 'exists:contents,id'],
            'content_lexeme_id' => ['nullable', 'integer', 'exists:content_lexemes,id'],
            'transcript_segment_id' => ['nullable', 'integer', 'exists:transcript_segments,id'],
            'exercise_type' => ['required', 'in:dictation,shadowing,speaking'],
            'target_text' => ['required', 'string', 'max:5000'],
            'user_text' => ['nullable', 'string', 'max:5000'],
            'hint_used' => ['sometimes', 'boolean'],
            'replay_count' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'audio' => ['nullable', 'file', 'mimes:webm,wav,mp3,m4a,ogg,mp4', 'max:25600'],
        ];
    }
}
