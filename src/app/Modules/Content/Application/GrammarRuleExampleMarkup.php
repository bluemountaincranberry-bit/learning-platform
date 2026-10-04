<?php

namespace App\Modules\Content\Application;

/**
 * Parses an example sentence whose grammar form is wrapped in `**…**`
 * ("She **doesn't like** tea.") into plain text plus [start, end) spans
 * in characters (code points). The SPA slices with Array.from(text), which
 * counts code points too, so offsets agree for any script.
 */
final class GrammarRuleExampleMarkup
{
    private const MARKER = '**';

    /**
     * @return array{text: string, spans: list<array{0: int, 1: int}>}|null null when nothing is marked or a marker is unclosed
     */
    public static function parse(string $marked): ?array
    {
        $parts = explode(self::MARKER, trim($marked));

        // An even number of parts means an odd number of markers: unclosed.
        if (count($parts) < 3 || count($parts) % 2 === 0) {
            return null;
        }

        $text = '';
        $spans = [];

        foreach ($parts as $index => $part) {
            $isTarget = $index % 2 === 1;

            if ($isTarget) {
                if (trim($part) === '') {
                    return null;
                }
                $start = mb_strlen($text);
                $spans[] = [$start, $start + mb_strlen($part)];
            }

            $text .= $part;
        }

        return ['text' => $text, 'spans' => $spans];
    }

    /** Text that must stay plain (mistake, translation): markers removed. */
    public static function strip(string $text): string
    {
        return trim(str_replace(self::MARKER, '', $text));
    }

    /** Key for "same sentence": case, punctuation and spacing do not matter. */
    public static function dedupKey(string $text): string
    {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", $text));
        $text = (string) preg_replace("/[^\p{L}\p{N}\s']+/u", '', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
