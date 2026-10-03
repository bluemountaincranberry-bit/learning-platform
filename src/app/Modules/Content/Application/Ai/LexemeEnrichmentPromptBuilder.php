<?php

namespace App\Modules\Content\Application\Ai;

use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeAssociation;
use App\Contracts\Ai\AiEditablePrompt;
use App\Contracts\Ai\PromptRegistryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Prompt for the admin-triggered "AI: Enrich" action on a canonical Lexeme,
 * and (task 10.4) the automatic EnrichLexemeAssociationsJob fired for every
 * newly created lexeme — proposes typed related words (not just synonyms),
 * extra example sentences, and a translation. Nothing gets written directly
 * from this prompt (AiFieldEditService never saves); callers decide what to
 * persist before anything reaches LexemeAssociation/LexemeExample/
 * LexemeTranslation.
 */
class LexemeEnrichmentPromptBuilder implements AiEditablePrompt
{
    public function __construct(private readonly PromptRegistryInterface $promptRegistry) {}

    public function buildPrompt(Model $subject, ?string $instruction): array
    {
        /** @var Lexeme $subject */
        $language = $subject->language ?? 'en';
        $translationLanguage = config('ai.analysis.translation_language', 'ru');

        $existingExamples = $subject->examples()->limit(3)->pluck('example')->filter()->implode(' | ');
        $existingRelated = $subject->associations()
            ->with('relatedLexeme')
            ->get()
            ->map(fn ($a) => $a->relatedLexeme?->lemma)
            ->filter()
            ->implode(', ');

        $relationTypes = implode('|', LexemeAssociation::TYPES);

        $rendered = $this->promptRegistry->resolve(
            'field_edit_lexeme_enrichment_system_prompt',
            [
                'lemma' => $subject->lemma,
                'part_of_speech' => $subject->part_of_speech ?? '',
                'language' => $language,
                'translation_language' => $translationLanguage,
                'existing_related' => $existingRelated,
                'existing_examples' => $existingExamples,
            ],
            function () use ($subject, $language, $translationLanguage, $existingRelated, $existingExamples, $relationTypes) {
                $system = 'You are a lexicographer helping build a vocabulary catalog for language learners. '
                    ."The word/phrase is \"{$subject->lemma}\""
                    .($subject->part_of_speech ? " ({$subject->part_of_speech})" : '')
                    ." in \"{$language}\".\n\n"
                    .'Propose a short list of genuinely useful related words, each tagged with the specific '
                    ."kind of relation it is ({$relationTypes} — pick the most accurate one, don't default "
                    .'everything to "synonym": near-synonyms that are NOT interchangeable, antonyms, '
                    .'derivationally related words from the same root, phrasal-verb forms of this exact verb, '
                    .'common collocations, grammatically related forms, thematically related vocabulary, and '
                    .'same-spelling/different-meaning words are all more informative than a generic "related" '
                    .'tag when they genuinely apply); '
                    ."1-3 new natural example sentences in \"{$language}\" with their translation into \"{$translationLanguage}\", "
                    ."and a single concise translation of the word itself into \"{$translationLanguage}\".";

                if ($existingRelated !== '') {
                    $system .= "\n\nAlready-catalogued related words, do not repeat: {$existingRelated}.";
                }
                if ($existingExamples !== '') {
                    $system .= "\n\nExisting examples for this word, for context and to avoid duplicates: {$existingExamples}";
                }

                return ['system' => $system, 'user' => ''];
            }
        );

        return [
            'system' => $rendered->system,
            'user' => $instruction ?: 'Propose related words, example sentences, and a translation for this word.',
            'schema' => [
                'related' => "array of {lemma: string, type: {$relationTypes}, gloss: string} — most useful first",
                'examples' => 'array of {example: string, translation: string}',
                'translation' => "string, a concise translation into \"{$translationLanguage}\"",
            ],
        ];
    }
}
