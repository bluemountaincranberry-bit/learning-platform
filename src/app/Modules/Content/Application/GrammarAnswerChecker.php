<?php

namespace App\Modules\Content\Application;

/**
 * Deterministic grading for typed grammar answers (fill the gap, transform,
 * fix, build): no AI at check time, so practice keeps working with the AI
 * provider down. Two strings match when they are equal after normalization
 * (case, outer/repeated spaces, commas, final punctuation, curly quotes) and
 * contraction expansion, so "haven't" = "have not" and "she's" = "she has" /
 * "she is". Ambiguous contractions ('s, 'd) expand to every reading, which
 * keeps a correct contracted answer from being marked wrong.
 */
final class GrammarAnswerChecker
{
    /** Hard cap on contraction readings per string, against pathological input. */
    private const MAX_VARIANTS = 64;

    /** @var array<string, list<string>> */
    private const WHOLE_WORDS = [
        "won't" => ['will not'],
        "can't" => ['can not'],
        'cannot' => ['can not'],
        "shan't" => ['shall not'],
    ];

    /** @var array<string, list<string>> */
    private const SUFFIXES = [
        "n't" => ['not'],
        "'ve" => ['have'],
        "'re" => ['are'],
        "'m" => ['am'],
        "'ll" => ['will'],
        "'d" => ['had', 'would'],
        "'s" => ['is', 'has'],
    ];

    /** @param  list<string>  $acceptedAnswers */
    public function typedMatches(string $given, string $answer, array $acceptedAnswers): bool
    {
        $givenVariants = $this->variants($given);
        if ($givenVariants === []) {
            return false;
        }

        foreach ([$answer, ...$acceptedAnswers] as $expected) {
            if (array_intersect($givenVariants, $this->variants($expected)) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when any reading of the answer appears as whole words inside the
     * hint. Used to drop generated hints that would give the answer away
     * (VIK-32: a hint never contains the answer).
     */
    public function hintRevealsAnswer(string $hint, string $answer): bool
    {
        $answerVariants = $this->variants($answer);
        if ($answerVariants === []) {
            return false;
        }

        foreach ($this->variants($hint, keepFinalPunctuation: true) as $hintVariant) {
            foreach ($answerVariants as $answerVariant) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($answerVariant, '/').'(?![\p{L}\p{N}])/u';
                if (preg_match($pattern, $hintVariant) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    public function normalize(string $value, bool $keepFinalPunctuation = false): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['’', '‘', '`', '´'], "'", $value);
        $value = str_replace(',', ' ', $value);
        if (! $keepFinalPunctuation) {
            $value = preg_replace('/[\s.!?;:…]+$/u', '', $value) ?? $value;
        }
        $value = preg_replace('/\s+([.!?;:])/u', '$1', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** @return list<string> */
    private function variants(string $value, bool $keepFinalPunctuation = false): array
    {
        $normalized = $this->normalize($value, $keepFinalPunctuation);
        if ($normalized === '') {
            return [];
        }

        $variants = [''];
        foreach (explode(' ', $normalized) as $token) {
            $readings = $this->tokenReadings($token);
            $next = [];
            foreach ($variants as $prefix) {
                foreach ($readings as $reading) {
                    $next[] = $prefix === '' ? $reading : $prefix.' '.$reading;
                }
            }
            $variants = array_slice(array_values(array_unique($next)), 0, self::MAX_VARIANTS);
        }

        return $variants;
    }

    /** @return list<string> */
    private function tokenReadings(string $token): array
    {
        // Split trailing punctuation off so "can't." still expands.
        preg_match('/^(.*?)([.!?;:]*)$/u', $token, $parts);
        $word = $parts[1] ?? $token;
        $tail = $parts[2] ?? '';

        if (isset(self::WHOLE_WORDS[$word])) {
            return array_map(fn (string $r): string => $r.$tail, self::WHOLE_WORDS[$word]);
        }

        foreach (self::SUFFIXES as $suffix => $expansions) {
            if (! str_ends_with($word, $suffix) || mb_strlen($word) <= mb_strlen($suffix)) {
                continue;
            }

            $stem = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
            $readings = array_map(fn (string $e): string => $stem.' '.$e.$tail, $expansions);

            // 's is also the possessive ("Tom's car"), so keep the literal form.
            return $suffix === "'s" ? [...$readings, $token] : $readings;
        }

        return [$token];
    }
}
