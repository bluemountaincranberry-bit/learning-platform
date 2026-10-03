<?php

namespace App\Modules\Learning\Application;

final class LearningFlowDefaults
{
    public const ACTIVITIES = [
        'encounter', 'recognition', 'recall', 'production', 'listening', 'dictation', 'speaking', 'shadowing',
    ];

    public const DIMENSIONS = ['recognition', 'recall', 'production', 'listening', 'speaking'];

    /** @return array<string, mixed> */
    public static function balanced(): array
    {
        return [
            'stages' => ['encounter', 'recognition', 'recall', 'production', 'listening', 'speaking'],
            'activity_weights' => [
                'recognition' => 20, 'recall' => 30, 'production' => 25, 'listening' => 15, 'speaking' => 10,
            ],
            'target_success_rate' => 0.8,
            'retry_after_cards' => 3,
            'max_same_lexeme_per_session' => 2,
            'session_minutes' => 15,
            'daily_new_words' => 8,
            'allowed_user_overrides' => ['session_minutes', 'daily_new_words', 'listening_weight', 'speaking_weight', 'hint_mode', 'difficulty_preference'],
            'points' => [
                'encounter' => 1, 'recognition' => 5, 'recall' => 10, 'production' => 20,
                'listening' => 15, 'dictation' => 20, 'speaking' => 25, 'shadowing' => 25,
            ],
            'difficulty_multipliers' => ['A1' => 0.8, 'A2' => 1.0, 'B1' => 1.2, 'B2' => 1.5, 'C1' => 1.8, 'C2' => 2.2],
        ];
    }

    /** @return array<string, array{name: string, description: string, config: array<string, mixed>}> */
    public static function recommendedProfiles(): array
    {
        $balanced = self::balanced();

        return [
            'balanced' => ['name' => 'Balanced', 'description' => 'A balanced mix for everyday progress.', 'config' => $balanced],
            'listening-first' => ['name' => 'Listening first', 'description' => 'Prioritizes listening and dictation for real speech.', 'config' => array_replace_recursive($balanced, ['activity_weights' => ['recognition' => 15, 'recall' => 20, 'production' => 15, 'listening' => 35, 'speaking' => 15], 'stages' => ['encounter', 'listening', 'recognition', 'recall', 'dictation', 'production']])],
            'speaking-first' => ['name' => 'Speaking first', 'description' => 'Moves useful phrases into production and speaking sooner.', 'config' => array_replace_recursive($balanced, ['activity_weights' => ['recognition' => 10, 'recall' => 20, 'production' => 30, 'listening' => 15, 'speaking' => 25], 'stages' => ['encounter', 'recognition', 'recall', 'production', 'speaking', 'listening']])],
            'fast-vocabulary' => ['name' => 'Fast vocabulary', 'description' => 'Short sessions with quick recognition and recall.', 'config' => array_replace_recursive($balanced, ['activity_weights' => ['recognition' => 35, 'recall' => 35, 'production' => 15, 'listening' => 10, 'speaking' => 5], 'session_minutes' => 10, 'daily_new_words' => 12])],
            'deep-mastery' => ['name' => 'Deep mastery', 'description' => 'Slower progression with more context and production.', 'config' => array_replace_recursive($balanced, ['activity_weights' => ['recognition' => 10, 'recall' => 25, 'production' => 35, 'listening' => 15, 'speaking' => 15], 'session_minutes' => 25, 'daily_new_words' => 5, 'target_success_rate' => 0.85])],
        ];
    }
}
