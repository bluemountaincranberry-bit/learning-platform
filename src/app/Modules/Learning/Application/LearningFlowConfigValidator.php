<?php

namespace App\Modules\Learning\Application;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LearningFlowConfigValidator
{
    /** @param array<string, mixed> $config */
    public function validate(array $config): array
    {
        $config = array_replace_recursive(LearningFlowDefaults::balanced(), $config);
        $validated = Validator::make($config, [
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => ['string', 'in:'.implode(',', LearningFlowDefaults::ACTIVITIES)],
            'activity_weights' => ['required', 'array'],
            'activity_weights.*' => ['integer', 'min:0', 'max:100'],
            'target_success_rate' => ['required', 'numeric', 'between:0.5,0.99'],
            'retry_after_cards' => ['required', 'integer', 'between:1,10'],
            'max_same_lexeme_per_session' => ['required', 'integer', 'between:1,3'],
            'session_minutes' => ['required', 'integer', 'between:5,120'],
            'daily_new_words' => ['required', 'integer', 'between:1,100'],
            'points' => ['required', 'array'],
            'points.*' => ['integer', 'min:0', 'max:1000'],
        ])->validate();

        if (collect($validated['stages'])->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['stages' => 'A learning stage may only appear once in a flow.']);
        }

        return $validated;
    }
}
