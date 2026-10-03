<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\UserLexemeConfidence;

class LexemeConfidenceService
{
    /** @return array<string, int> */
    public function get(int $contentLexemeId, int $userId): array
    {
        $row = UserLexemeConfidence::query()->firstOrCreate(
            ['user_id' => $userId, 'content_lexeme_id' => $contentLexemeId],
            $this->defaults(),
        );

        return collect($this->defaults())->mapWithKeys(fn ($default, $key) => [$key => (int) $row->{$key}])->all();
    }

    /** @param array<string, int> $updates */
    public function record(int $contentLexemeId, int $userId, array $updates): UserLexemeConfidence
    {
        $values = [];
        foreach ($this->defaults() as $dimension => $default) {
            if (array_key_exists($dimension, $updates)) {
                $values[$dimension] = max(0, min(100, (int) $updates[$dimension]));
            }
        }

        return UserLexemeConfidence::query()->updateOrCreate(
            ['user_id' => $userId, 'content_lexeme_id' => $contentLexemeId],
            $values,
        );
    }

    public function recordOutcome(int $contentLexemeId, int $userId, string $dimension, bool $correct, bool $hintUsed = false): UserLexemeConfidence
    {
        if (! array_key_exists($dimension, $this->defaults())) {
            return UserLexemeConfidence::query()->firstOrCreate(
                ['user_id' => $userId, 'content_lexeme_id' => $contentLexemeId],
                $this->defaults(),
            );
        }

        $row = UserLexemeConfidence::query()->firstOrCreate(
            ['user_id' => $userId, 'content_lexeme_id' => $contentLexemeId],
            $this->defaults(),
        );
        $current = (int) $row->{$dimension};
        if ($current === 0) {
            $row->{$dimension} = $correct ? ($hintUsed ? 60 : 75) : 20;
        } else {
            $delta = $correct ? ($hintUsed ? 4 : 8) : -12;
            $row->{$dimension} = max(0, min(100, $current + $delta));
        }
        $row->save();

        return $row;
    }

    /** @return array<string, int> */
    private function defaults(): array
    {
        return ['recognition' => 0, 'recall' => 0, 'production' => 0, 'listening' => 0, 'speaking' => 0];
    }
}
