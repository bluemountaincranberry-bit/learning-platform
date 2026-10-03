<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LearningFlowPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learning_flow_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:learning_flow_profiles,id'],
            'session_minutes' => ['sometimes', 'nullable', 'integer', 'between:5,60'],
            'daily_new_words' => ['sometimes', 'nullable', 'integer', 'between:1,30'],
            'listening_weight' => ['sometimes', 'nullable', 'integer', 'between:0,50'],
            'speaking_weight' => ['sometimes', 'nullable', 'integer', 'between:0,50'],
            'hint_mode' => ['sometimes', 'nullable', 'in:guided,balanced,challenge'],
            'difficulty_preference' => ['sometimes', 'nullable', 'in:easier,balanced,harder'],
        ];
    }
}
