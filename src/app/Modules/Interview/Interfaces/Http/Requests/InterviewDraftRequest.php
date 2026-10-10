<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:question'],
            'payload.prompt_en' => ['required', 'string', 'max:2000'],
            'payload.prompt_ru' => ['nullable', 'string', 'max:2000'],
            'payload.topic_id' => ['nullable', 'integer'],
            'payload.tags' => ['sometimes', 'array'],
            'payload.tags.*' => ['string', 'max:80'],
        ];
    }
}
