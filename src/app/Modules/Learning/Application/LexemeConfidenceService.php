<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\ContentLexemeReferenceReaderInterface;
use App\Modules\Learning\Domain\Models\UserLexemeConfidence;

class LexemeConfidenceService
{
    public function __construct(private readonly ContentLexemeReferenceReaderInterface $lexemes) {}

    /** @return array<string, int> */
    public function get(int $contentLexemeId, int $userId): array
    {
        $identity = $this->identityForOccurrence($contentLexemeId);
        $row = UserLexemeConfidence::query()->firstOrCreate(
            ['user_id' => $userId, 'lexeme_id' => $identity['lexeme_id']],
            ['content_lexeme_id' => $contentLexemeId, ...$this->defaults()],
        );

        return collect($this->defaults())->mapWithKeys(fn ($default, $key) => [$key => (int) $row->{$key}])->all();
    }

    /** @param array<string, int> $updates */
    public function record(int $contentLexemeId, int $userId, array $updates): UserLexemeConfidence
    {
        $identity = $this->identityForOccurrence($contentLexemeId);
        $values = [];
        foreach ($this->defaults() as $dimension => $default) {
            if (array_key_exists($dimension, $updates)) {
                $values[$dimension] = max(0, min(100, (int) $updates[$dimension]));
            }
        }

        return UserLexemeConfidence::query()->updateOrCreate(
            ['user_id' => $userId, 'lexeme_id' => $identity['lexeme_id']],
            ['content_lexeme_id' => $contentLexemeId, ...$values],
        );
    }

    public function recordOutcome(int $contentLexemeId, int $userId, string $dimension, bool $correct, bool $hintUsed = false): UserLexemeConfidence
    {
        $identity = $this->identityForOccurrence($contentLexemeId);
        $row = UserLexemeConfidence::query()->firstOrCreate(
            ['user_id' => $userId, 'lexeme_id' => $identity['lexeme_id']],
            ['content_lexeme_id' => $contentLexemeId, ...$this->defaults()],
        );

        return $this->applyOutcome($row, $dimension, $correct, $hintUsed);
    }

    public function recordCanonicalOutcome(int $lexemeId, int $userId, string $dimension, bool $correct, bool $hintUsed = false): UserLexemeConfidence
    {
        $row = UserLexemeConfidence::query()->firstOrCreate(
            ['user_id' => $userId, 'lexeme_id' => $lexemeId],
            ['content_lexeme_id' => null, ...$this->defaults()],
        );

        return $this->applyOutcome($row, $dimension, $correct, $hintUsed);
    }

    private function applyOutcome(UserLexemeConfidence $row, string $dimension, bool $correct, bool $hintUsed): UserLexemeConfidence
    {
        if (! array_key_exists($dimension, $this->defaults())) {
            return $row;
        }

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

    /** @return array{lexeme_id: int} */
    private function identityForOccurrence(int $contentLexemeId): array
    {
        return ['lexeme_id' => $this->lexemes->resolveCanonicalLexemeId($contentLexemeId)];
    }
}
