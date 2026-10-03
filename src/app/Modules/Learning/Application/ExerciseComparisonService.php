<?php

namespace App\Modules\Learning\Application;

use Illuminate\Support\Str;

class ExerciseComparisonService
{
    /** @return array{score: int, correct: bool, error_type: ?string, target_tokens: array<int, string>, answer_tokens: array<int, string>} */
    public function compare(string $target, string $answer): array
    {
        $targetTokens = $this->tokens($target);
        $answerTokens = $this->tokens($answer);
        if ($targetTokens === []) return ['score' => 0, 'correct' => false, 'error_type' => 'missing_target', 'target_tokens' => [], 'answer_tokens' => $answerTokens];
        $distance = $this->distance($targetTokens, $answerTokens);
        $score = max(0, (int) round(100 * (1 - $distance / max(count($targetTokens), count($answerTokens), 1))));
        return ['score' => $score, 'correct' => $score >= 90, 'error_type' => $score >= 90 ? null : ($answerTokens === [] ? 'missing_answer' : 'recognition_mismatch'), 'target_tokens' => $targetTokens, 'answer_tokens' => $answerTokens];
    }

    /** @return array<int, string> */
    private function tokens(string $text): array { return preg_split('/[^\p{L}\p{N}\']+/u', Str::lower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: []; }

    private function distance(array $a, array $b): int
    {
        $row = range(0, count($b));
        foreach ($a as $i => $value) {
            $next = [$i + 1];
            foreach ($b as $j => $other) $next[] = min($next[$j] + 1, $row[$j + 1] + 1, $row[$j] + ($value === $other ? 0 : 1));
            $row = $next;
        }
        return $row[count($b)];
    }
}
