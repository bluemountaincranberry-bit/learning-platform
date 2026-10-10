<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'career_goal' => ['nullable', 'string', 'max:500'],
            'skills' => ['sometimes', 'array'], 'skills.*' => ['string', 'max:120'],
            'experience_level' => ['nullable', 'string', 'max:120'],
            'projects' => ['sometimes', 'array'], 'projects.*' => ['string', 'max:1000'],
            'experience_stories' => ['sometimes', 'array'], 'experience_stories.*' => ['string', 'max:4000'],
            'milestones' => ['sometimes', 'array', 'max:50'],
            'milestones.*.id' => ['sometimes', 'integer'],
            'milestones.*.title' => ['required', 'string', 'max:250'],
            'milestones.*.target_date' => ['nullable', 'date'],
        ];
    }
}
