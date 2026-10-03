<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendLessonMessageRequest extends FormRequest
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
            // Either content or an attachment must be present — checked in
            // the controller (same as ContentAgentChat::send()) rather than
            // here, since "at least one of two optional fields" doesn't map
            // cleanly onto a single field's rule list.
            'content' => ['nullable', 'string', 'max:16000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:'.(int) config('ai.agent.max_upload_kb', 10240)],
        ];
    }
}
