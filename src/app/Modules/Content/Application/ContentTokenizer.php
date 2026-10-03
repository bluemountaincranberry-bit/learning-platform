<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentTokenizerInterface;

class ContentTokenizer implements ContentTokenizerInterface
{
    public const MAX_TOKENS = 5000;

    public function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $chunks = preg_split('/[^\p{L}\p{N}\']+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $filtered = array_filter($chunks, fn ($token) => mb_strlen($token) >= 1);
        $unique = [];
        $result = [];

        foreach ($filtered as $token) {
            if (isset($unique[$token])) {
                continue;
            }

            $unique[$token] = true;
            $result[] = $token;

            if (count($result) >= self::MAX_TOKENS) {
                break;
            }
        }

        return $result;
    }

    public function describe(): string
    {
        return 'Lowercases input, splits on non-letter/digit/apostrophe characters, removes duplicates, '
            .'and returns at most '.self::MAX_TOKENS.' tokens.';
    }
}
