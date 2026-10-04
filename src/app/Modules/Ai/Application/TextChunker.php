<?php

namespace App\Modules\Ai\Application;

/**
 * Splits long text into whole-word chunks no longer than `$maxChars`, so
 * every part of a long document reaches the model instead of everything
 * past a limit being silently dropped. Text at or under the limit comes
 * back as a single chunk. Prefers to cut at a line break, then at a space.
 */
class TextChunker
{
    /**
     * @return array<int, string>
     */
    public function chunk(string $text, int $maxChars): array
    {
        $text = trim($text);

        if ($maxChars < 1 || mb_strlen($text) <= $maxChars) {
            return $text === '' ? [] : [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (mb_strlen($remaining) > $maxChars) {
            $slice = mb_substr($remaining, 0, $maxChars);
            $lineBreak = mb_strrpos($slice, "\n");
            $space = mb_strrpos($slice, ' ');
            // A line break is only used when it leaves a reasonably full chunk.
            $breakAt = ($lineBreak !== false && $lineBreak > $maxChars / 2) ? $lineBreak : $space;
            $cut = ($breakAt !== false && $breakAt > 0) ? $breakAt : $maxChars;

            $chunks[] = rtrim(mb_substr($remaining, 0, $cut));
            $remaining = ltrim(mb_substr($remaining, $cut));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }
}
