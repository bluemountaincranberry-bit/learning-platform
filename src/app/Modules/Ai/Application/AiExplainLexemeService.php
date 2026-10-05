<?php

namespace App\Modules\Ai\Application;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Capabilities\ContextSentenceGenerationService;
use App\Modules\Ai\Application\Capabilities\LexemeExplanationService;
use App\Modules\Ai\Application\Capabilities\LexemeMetadataSuggestionService;
use App\Modules\Ai\Application\Capabilities\LexemeTranslationService;
use App\Modules\Ai\Application\Support\LexemeCandidateFields;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Content\Application\Data\LexemeMetadataOptions;

class AiExplainLexemeService
{
    public function __construct(
        private readonly AiClientInterface $client,
        private readonly AiJsonClient $jsonClient,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly TracedLlmCall $tracedCall,
    ) {}

    /**
     * @throws AiClientException
     */
    public function explain(string $lexemeText, ?string $language = null, ?int $contentLexemeId = null, bool $refresh = false): string
    {
        return (new LexemeExplanationService($this->client, $this->promptRegistry, $this->tracedCall))
            ->explain($lexemeText, $language, $contentLexemeId, $refresh);
    }

    /**
     * On-demand single example sentence for a ContentLexeme's context-practice
     * card (ContextSentenceCard's "Generate a new sentence") — used when the
     * word has no usable stored example, or the learner wants a different
     * one. Same synchronous, single-call shape as explain()/suggestMetadata()
     * above, not the async Horizon content-analysis pipeline. Deliberately
     * topic-steered by the content's own title so the sentence still feels
     * connected to what the learner is studying, not a generic one; kept to
     * exactly one sentence to stay cheap.
     *
     * @return array{sentence: string, target_form: string, translation: string, distractors: list<string>}
     *
     * @throws AiClientException
     */
    public function generateContextSentence(string $lexemeText, string $targetLanguage, string $nativeLanguage, ?string $topic = null): array
    {
        return (new ContextSentenceGenerationService($this->jsonClient, $this->promptRegistry, $this->tracedCall))
            ->generate($lexemeText, $targetLanguage, $nativeLanguage, $topic)
            ->toArray();
    }

    /**
     * On-demand single-word/phrase translation for a lexeme created from a
     * learner tapping an untracked transcript word (TranscriptController::
     * createLexeme) — the AI candidate pipeline already carries a translation
     * with each extracted word, but an ad-hoc click has none yet, so this
     * fills that one gap with the same synchronous, single-call shape as
     * explain()/suggestMetadata() above.
     *
     * @throws AiClientException
     */
    public function translate(string $lexemeText, string $targetLanguage, string $nativeLanguage): string
    {
        return (new LexemeTranslationService($this->jsonClient, $this->promptRegistry, $this->tracedCall))
            ->translate($lexemeText, $targetLanguage, $nativeLanguage);
    }

    /**
     * @throws AiClientException
     */
    public function suggestLevel(string $lexemeText): string
    {
        $rendered = $this->promptRegistry->resolve(
            'ai_suggest_level',
            ['lexeme' => $lexemeText],
            fn () => [
                'system' => 'You are a language assessment expert. Given a word or phrase, suggest its CEFR level. Reply with exactly one of: A1, A2, B1, B2, C1, C2. No other text.',
                'user' => "Word/phrase: \"{$lexemeText}\". Suggest CEFR level.",
            ]
        );

        $response = $this->tracedCall->complete(
            $this->client,
            TraceContext::newTrace(),
            'explain_lexeme.suggest_level',
            ['feature' => 'explain_lexeme'],
            $rendered->system,
            $rendered->user,
            $rendered->model,
        );

        return trim($response);
    }

    /**
     * Task 9.7: one LLM call suggesting both CEFR level and part of speech
     * for a brand-new canonical lexeme — SuggestLexemeLevelJob's single
     * call, extended rather than duplicated into a second job/call.
     * Distinct from suggestLevel() above (kept as-is: still used standalone
     * by the admin's "Suggest CEFR level" action). The category list here is
     * illustrative prompt text only, not the source of truth — the caller
     * validates the response against the real enum
     * (`Lexeme::PARTS_OF_SPEECH`), so drift between this list and that
     * enum can only ever cause a rejected suggestion, never bad data stored.
     *
     * @return array{level: string|null, part_of_speech: string|null}
     *
     * @throws AiClientException
     */
    public function suggestMetadata(string $lexemeText): array
    {
        return (new LexemeMetadataSuggestionService($this->jsonClient, $this->promptRegistry, $this->tracedCall))
            ->suggest($lexemeText)
            ->toArray();
    }

    /**
     * Task 10.6: single-item equivalent of AiContentAnalysisService's
     * per-candidate extraction, for a word/phrase a learner manually
     * selected in the transcript (TranscriptController::createLexeme) —
     * same field vocabulary (lemma, part_of_speech, sense, grammar,
     * examples, level) so TranscriptLexemeLookupService can apply the
     * result through the exact same AiCandidateApplyService path as a
     * batch-extracted candidate, instead of a separate, thinner code path.
     * `sentenceContext` grounds the one required example and helps
     * disambiguate `sense` for a polysemous lemma.
     *
     * @return array{lemma: string, part_of_speech: ?string, sense: ?string, translation: string, level: ?string, grammar_features: ?array<string, string|bool>, example: string, example_translation: ?string}
     *
     * @throws AiClientException
     */
    public function analyzeForManualAdd(string $text, string $sentenceContext, string $sourceLanguage, string $translationLanguage): array
    {
        $partsOfSpeech = implode('|', array_keys(LexemeMetadataOptions::partsOfSpeech()));

        $rendered = $this->promptRegistry->resolve(
            'ai_analyze_manual_lexeme',
            ['text' => $text, 'sentence' => $sentenceContext, 'source_language' => $sourceLanguage, 'translation_language' => $translationLanguage],
            fn () => [
                'system' => 'You are a lexicographer helping a language learner who just selected a word or phrase '
                    ."while reading/watching content in \"{$sourceLanguage}\". Given the selected text and the "
                    .'sentence it came from, identify: its base dictionary/lemma form (field `lemma`, e.g. "run" '
                    .'for "ran" — for a multi-word selection, `lemma` is normally the same as the selected text, '
                    ."just normalized to its canonical form); its part of speech (field `part_of_speech`, one of {$partsOfSpeech}); "
                    .'if this lemma commonly has more than one distinct, unrelated meaning, a short `sense` gloss '
                    .'identifying which meaning is used in this specific sentence (omit `sense` when the lemma '
                    .'essentially has one common meaning); a translation of the selected text (in the sense it has '
                    ."in this sentence) into \"{$translationLanguage}\"; if the selected text is a single inflected "
                    .'word, a `grammar` object for this specific occurrence with only the keys that actually apply '
                    .'(`tense`, `number`, `person`, `degree`, `case`, `aspect`, `is_irregular`) — omit `grammar` '
                    .'entirely for multi-word selections or when the selected text already equals the lemma; a '
                    .'CEFR level estimate (A1-C2) for the word/phrase itself; and a translation of the given '
                    ."sentence itself into \"{$translationLanguage}\" (field `example_translation`).",
                'user' => "Selected text: \"{$text}\"\nSentence: \"{$sentenceContext}\"",
            ]
        );

        $schema = [
            'lemma' => 'string',
            'part_of_speech' => $partsOfSpeech,
            'sense' => 'string, optional',
            'translation' => 'string',
            'grammar' => ['tense' => 'string, optional', 'number' => 'string, optional', 'person' => 'string, optional', 'degree' => 'string, optional', 'case' => 'string, optional', 'aspect' => 'string, optional', 'is_irregular' => 'boolean, optional'],
            'level' => 'A1|A2|B1|B2|C1|C2',
            'example_translation' => 'string, translation of the given sentence',
        ];

        $result = $this->tracedCall->completeJson(
            $this->jsonClient,
            TraceContext::newTrace(),
            'explain_lexeme.analyze_manual_lexeme',
            ['feature' => 'explain_lexeme'],
            $rendered->system,
            $rendered->user,
            $schema,
            $rendered->model,
        );

        $lemma = is_string($result['lemma'] ?? null) && trim($result['lemma']) !== '' ? trim($result['lemma']) : $text;
        $translation = is_string($result['translation'] ?? null) ? trim($result['translation']) : '';
        $level = is_string($result['level'] ?? null) && in_array($result['level'], LexemeMetadataOptions::cefrLevels(), true) ? $result['level'] : null;
        $sense = is_string($result['sense'] ?? null) && trim($result['sense']) !== '' ? trim($result['sense']) : null;

        return [
            'lemma' => $lemma,
            'part_of_speech' => LexemeCandidateFields::parsePartOfSpeech($result['part_of_speech'] ?? null),
            'sense' => $sense,
            'translation' => $translation,
            'level' => $level,
            'grammar_features' => LexemeCandidateFields::parseGrammarFeatures($result['grammar'] ?? null),
            'example' => trim($sentenceContext) !== '' ? trim($sentenceContext) : $text,
            'example_translation' => is_string($result['example_translation'] ?? null) && trim($result['example_translation']) !== ''
                ? trim($result['example_translation'])
                : null,
        ];
    }
}
