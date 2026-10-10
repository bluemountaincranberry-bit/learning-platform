<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:interview_topics,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
