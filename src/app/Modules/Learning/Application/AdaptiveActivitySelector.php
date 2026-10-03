<?php

namespace App\Modules\Learning\Application;

class AdaptiveActivitySelector
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array{confidence?: array<string, int>, recent_error?: ?string, attempts?: int, has_example?: bool, has_translation?: bool}  $signals
     * @return array{activity: string, target_dimension: string, stage: string, difficulty: int, reason: string}
     */
    public function choose(?string $lexemeLevel, array $config, array $signals = []): array
    {
        $confidence = $signals['confidence'] ?? [];
        $weights = $config['activity_weights'] ?? LearningFlowDefaults::balanced()['activity_weights'];
        $recentError = $signals['recent_error'] ?? null;
        $hasExample = (bool) ($signals['has_example'] ?? false);
        $hasTranslation = (bool) ($signals['has_translation'] ?? false);
        $attempts = (int) ($signals['attempts'] ?? 0);

        $dimension = $this->dimensionForError($recentError)
            ?? collect(LearningFlowDefaults::DIMENSIONS)
                ->sortBy(fn (string $item): int => (int) ($confidence[$item] ?? 0) - (int) ($weights[$item] ?? 0))
                ->first();

        $dimension = $dimension ?: 'recognition';
        $activity = $this->activityForDimension($dimension, $hasExample, $hasTranslation);
        $stage = $this->stageFor($attempts, (int) ($confidence[$dimension] ?? 0));
        $level = $lexemeLevel ?? 'A1';
        $difficulty = (int) round(($config['difficulty_multipliers'][$level] ?? 1.0) * 50);

        $reason = $recentError !== null
            ? "Retrying after {$recentError}"
            : sprintf('%s is the weakest skill for this word', ucfirst($dimension));

        if ($activity === 'encounter') {
            $reason = 'Build context before recall';
        }

        return compact('activity', 'dimension', 'stage', 'difficulty', 'reason') + ['target_dimension' => $dimension];
    }

    private function activityForDimension(string $dimension, bool $hasExample, bool $hasTranslation): string
    {
        return match ($dimension) {
            'production' => $hasExample ? 'cloze' : ($hasTranslation ? 'recall' : 'recognition'),
            'listening' => 'listening',
            'speaking' => $hasExample ? 'shadowing' : ($hasTranslation ? 'recall' : 'recognition'),
            'recall' => $hasTranslation ? 'recall' : 'recognition',
            default => $hasTranslation ? 'quick-check' : ($hasExample ? 'encounter' : 'recognition'),
        };
    }

    private function stageFor(int $attempts, int $confidence): string
    {
        if ($attempts === 0) {
            return 'encounter';
        }
        if ($confidence < 35) {
            return 'recognition';
        }
        if ($confidence < 60) {
            return 'recall';
        }
        if ($confidence < 80) {
            return 'production';
        }

        return 'listening';
    }

    private function dimensionForError(?string $error): ?string
    {
        if ($error === null) {
            return null;
        }

        return match (true) {
            str_contains($error, 'hear'), str_contains($error, 'dictation') => 'listening',
            str_contains($error, 'pronunciation'), str_contains($error, 'speaking') => 'speaking',
            str_contains($error, 'production'), str_contains($error, 'form') => 'production',
            str_contains($error, 'meaning'), str_contains($error, 'recognition') => 'recognition',
            default => 'recall',
        };
    }
}
