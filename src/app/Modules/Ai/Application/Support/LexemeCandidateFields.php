<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Content\Application\Data\LexemeMetadataOptions;

/**
 * Shared field-parsing rules for a single AI-proposed lexeme candidate —
 * used both by the batch AiContentAnalysisService (one call, many items)
 * and AiExplainLexemeService::analyzeForManualAdd() (one call, one item,
 * task 10.6), so a validation rule only ever needs fixing in one place.
 */
final class LexemeCandidateFields
{
    public static function parsePartOfSpeech(mixed $value): ?string
    {
        return is_string($value) && array_key_exists($value, LexemeMetadataOptions::partsOfSpeech()) ? $value : null;
    }

    /**
     * Grammar tags describing one specific occurrence (e.g. `{"tense":
     * "past"}` for "ran"), not a property of the lemma itself. Only the
     * known keys survive; the model is asked to omit inapplicable ones
     * rather than guess, but this defensively drops anything unexpected/
     * mistyped instead of persisting garbage.
     *
     * @return array<string, string|bool>|null
     */
    public static function parseGrammarFeatures(mixed $rawGrammar): ?array
    {
        if (! is_array($rawGrammar)) {
            return null;
        }

        $allowedKeys = ['tense', 'number', 'person', 'degree', 'case', 'aspect', 'is_irregular'];
        $features = [];

        foreach ($allowedKeys as $key) {
            $value = $rawGrammar[$key] ?? null;

            if ($key === 'is_irregular' && is_bool($value)) {
                $features[$key] = $value;
            } elseif ($key !== 'is_irregular' && is_string($value) && trim($value) !== '') {
                $features[$key] = trim($value);
            }
        }

        return $features === [] ? null : $features;
    }
}
