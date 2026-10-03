<?php

namespace App\Modules\Ai\Application;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiAnalysisRunConfig;
use App\Modules\Ai\Application\Support\LexemeCandidateFields;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\ContentAnalysisCapability;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\CandidateAnalysisStoreInterface;
use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;
use App\Modules\Content\Application\Contracts\ContentTokenizerInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleTitleReaderInterface;
use App\Modules\Content\Application\Data\GrammarRuleStructureInstructions;
use App\Modules\Content\Application\Data\LexemeMetadataOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiContentAnalysisService implements ContentAnalysisCapability
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly ContentTokenizerInterface $tokenizer,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
        ?CandidateAnalysisStoreInterface $candidateStore = null,
        ?GrammarRuleTitleReaderInterface $grammarRuleTitles = null,
        ?ContentAnalysisSourceReaderInterface $contentSources = null,
    ) {
        $this->grammarRuleTitles = $grammarRuleTitles ?? app(GrammarRuleTitleReaderInterface::class);
        $this->candidateStore = $candidateStore ?? app(CandidateAnalysisStoreInterface::class);
        $this->contentSources = $contentSources ?? app(ContentAnalysisSourceReaderInterface::class);
    }

    private readonly GrammarRuleTitleReaderInterface $grammarRuleTitles;

    private readonly CandidateAnalysisStoreInterface $candidateStore;

    private readonly ContentAnalysisSourceReaderInterface $contentSources;

    /**
     * @throws AiClientException
     */
    public function analyze(AiAnalysisRun $run): void
    {
        $content = $this->contentSources->get((int) $run->content_id);
        $transcript = trim((string) ($content?->sourceText ?? ''));

        if ($transcript === '') {
            throw new AiClientException('Content has no transcript to analyze.');
        }

        $config = AiAnalysisRunConfig::fromArray($run->config ?? []);
        $translationLanguage = $config->translationLanguage ?? config('ai.analysis.translation_language', 'ru');
        $sourceLanguage = $content->language ?? 'en';

        $excludeGrammarTitles = $config->excludeGrammarRuleIds === []
            ? []
            : $this->grammarRuleTitles->titlesForIds($config->excludeGrammarRuleIds);

        $rendered = $this->promptRegistry->resolve(
            'content_analysis_system_prompt',
            [
                'source_language' => $sourceLanguage,
                'translation_language' => $translationLanguage,
                'structure_instructions' => GrammarRuleStructureInstructions::text(),
                'thorough' => $config->thoroughness === AiAnalysisRunConfig::THOROUGHNESS_THOROUGH ? 'yes' : '',
                'target_level' => $config->targetLevel ?? '',
                'exclude_words' => implode(', ', $config->excludeWords),
                'exclude_grammar_titles' => implode(', ', $excludeGrammarTitles),
                'extra_instructions' => $config->extraInstructions ?? '',
            ],
            fn () => ['system' => $this->buildSystemPrompt($sourceLanguage, $translationLanguage, $config, $excludeGrammarTitles), 'user' => '']
        );
        // The code prompt remains the safety/product baseline. A DB prompt
        // override supplements it instead of replacing its language and
        // structured-output requirements.
        $systemPrompt = $this->buildSystemPrompt($sourceLanguage, $translationLanguage, $config, $excludeGrammarTitles)
            .($rendered->isOverride ? "\n\nAdditional active prompt instructions:\n{$rendered->system}" : '')
            ."\n\n"
            .'Mandatory rules for this run: every lexeme translation and every example translation '
            ."must be written in {$translationLanguage}. Return only words/phrases that occur in the "
            .'transcript, include a CEFR level (A1-C2), a numeric confidence (0-1), and use the '
            .'requested JSON schema exactly.';
        $chunks = $this->chunkTranscript($transcript);

        if (count($chunks) > 1) {
            Log::info('AiContentAnalysisService: transcript split into chunks for analysis', [
                'content_id' => $content->id,
                'run_id' => $run->id,
                'transcript_length' => mb_strlen($transcript),
                'chunk_count' => count($chunks),
            ]);
        }

        $trace = TraceContext::newTrace();

        $lexemes = [];
        $grammar = [];
        foreach ($chunks as $chunkIndex => $chunk) {
            $result = $this->completeJsonTraced($trace, $run, $chunkIndex, $systemPrompt, $chunk, $rendered->model);
            $lexemes = array_merge($lexemes, is_array($result['lexemes'] ?? null) ? $result['lexemes'] : []);
            $grammar = array_merge($grammar, is_array($result['grammar'] ?? null) ? $result['grammar'] : []);
        }

        if ($lexemes === [] && $grammar === []) {
            throw new AiClientException('AI analysis returned no lexemes or grammar candidates.');
        }

        // The same word/phrase or grammar title can turn up in more than one
        // chunk of a long transcript — keep only the first occurrence's data
        // rather than creating duplicate candidate rows for it.
        foreach ($this->dedupeByField($lexemes, 'text') as $item) {
            $this->createLexemeCandidate($run, $item, $transcript);
        }

        foreach ($this->dedupeByField($grammar, 'title') as $item) {
            $this->createGrammarCandidate($run, $item);
        }

        $coverage = $this->calculateCoverage($run, $transcript);
        $run->update([
            'coverage_pct' => $coverage['pct'],
            'uncovered_words' => $coverage['uncovered'],
        ]);

        $this->maybeRetryForCoverage($run, $config, $coverage['pct']);
    }

    /**
     * Wraps one `completeJson()` call in an `llm_call` span via
     * `TracedLlmCall` (task: close the usage/cost tracking gap for this
     * service — previously the only one of the "classic" direct-call AI
     * services entirely invisible to `agent_trace_spans`, unlike the
     * agent-loop framework). Every chunk of one `analyze()` run shares
     * `$trace`'s trace_id as sibling root spans (parent_span_id null) —
     * there is no enclosing `agent_turn` span here, this isn't the
     * agent-loop framework, so no `agent_traces` summary row is produced;
     * per-call cost is still fully queryable in `agent_trace_spans` by
     * trace_id or by `metadata->run_id`.
     *
     * @return array<string, mixed>
     */
    private function completeJsonTraced(TraceContext $trace, AiAnalysisRun $run, int $chunkIndex, string $systemPrompt, string $userPrompt, ?string $model = null): array
    {
        return $this->tracedCall->completeJson(
            $this->client,
            $trace,
            'content_analysis.completeJson',
            ['feature' => 'content_analysis', 'run_id' => $run->id, 'chunk_index' => $chunkIndex],
            $systemPrompt,
            $userPrompt,
            $this->responseSchema(),
            $model,
        );
    }

    /**
     * Task 9.1: fraction of the transcript's distinct tokenizer-normalized
     * words that appear — as a whole word, or as part of a multi-word
     * candidate's text — in at least one lexeme candidate from this run.
     * `ContentTokenizer` is used purely as a text-analysis utility here, not
     * as a source of displayed words (that stays ProcessContentJob's job)
     * and it never creates any `ContentLexeme` rows.
     *
     * The uncovered word list itself is persisted (not just the aggregate
     * percentage) so task 9.5 can label the exact leftover tokenizer words
     * in the UI, reusing this same definition of "covered" rather than a
     * separately-invented heuristic.
     *
     * @return array{pct: float, uncovered: array<int, string>}
     */
    private function calculateCoverage(AiAnalysisRun $run, string $transcript): array
    {
        $tokens = $this->tokenizer->tokenize($transcript);

        if ($tokens === []) {
            return ['pct' => 100.0, 'uncovered' => []];
        }

        $coveredWords = [];
        foreach ($this->candidateStore->normalizedLexemeTexts((int) $run->id) as $normalizedText) {
            foreach (preg_split('/[^\p{L}\p{N}\']+/u', (string) $normalizedText, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $coveredWords[$word] = true;
            }
        }

        $uncovered = array_values(array_filter($tokens, fn (string $token): bool => ! isset($coveredWords[$token])));
        $coveredCount = count($tokens) - count($uncovered);

        return [
            'pct' => round(100 * $coveredCount / count($tokens), 1),
            'uncovered' => $uncovered,
        ];
    }

    /**
     * Auto-retries a low-coverage `focused` run exactly once with
     * `thoroughness=thorough` (task 9.1). Guarded on two independent fronts
     * (either alone would suffice, kept both for robustness): the run's own
     * `retried_for_coverage` flag, and the fact that a `thorough` run never
     * re-enters this branch. A coverage-triggered retry must never itself
     * trigger another retry.
     */
    private function maybeRetryForCoverage(AiAnalysisRun $run, AiAnalysisRunConfig $config, float $coveragePct): void
    {
        $minCoveragePct = ((float) config('ai.analysis.min_coverage_pct', 0.7)) * 100;

        if ($coveragePct >= $minCoveragePct) {
            return;
        }

        if ($config->thoroughness === AiAnalysisRunConfig::THOROUGHNESS_THOROUGH) {
            return;
        }

        if ($run->retried_for_coverage) {
            return;
        }

        Log::info('AiContentAnalysisService: coverage below threshold, retrying once with thoroughness=thorough', [
            'run_id' => $run->id,
            'coverage_pct' => $coveragePct,
            'min_coverage_pct' => $minCoveragePct,
        ]);

        $retryConfig = new AiAnalysisRunConfig(
            targetLevel: $config->targetLevel,
            excludeWords: $config->excludeWords,
            excludeGrammarRuleIds: $config->excludeGrammarRuleIds,
            extraInstructions: $config->extraInstructions,
            thoroughness: AiAnalysisRunConfig::THOROUGHNESS_THOROUGH,
            translationLanguage: $config->translationLanguage,
        );

        // Nobody has reviewed this pass's candidates yet (analyze() just
        // produced them) — the thorough retry replaces them with a fresh,
        // more complete extraction rather than appending to a known-incomplete
        // one, so the reviewer never sees a stale duplicate set.
        $this->candidateStore->deleteForRun((int) $run->id);

        $run->update([
            'config' => $retryConfig->toArray(),
            'retried_for_coverage' => true,
        ]);

        $this->analyze($run->fresh());
    }

    /**
     * Splits the transcript into whole-word chunks no longer than
     * `ai.analysis.max_transcript_chars`, so long videos get every chunk
     * analyzed instead of having everything past the limit silently
     * dropped. A transcript at or under the limit is returned as its own
     * single chunk — the common case, unchanged from before chunking.
     *
     * @return array<int, string>
     */
    private function chunkTranscript(string $transcript): array
    {
        $maxChars = (int) config('ai.analysis.max_transcript_chars', 8000);

        if (mb_strlen($transcript) <= $maxChars) {
            return [$transcript];
        }

        $chunks = [];
        $remaining = $transcript;

        while (mb_strlen($remaining) > $maxChars) {
            $slice = mb_substr($remaining, 0, $maxChars);
            $breakAt = mb_strrpos($slice, ' ');
            $cut = $breakAt !== false ? $breakAt : $maxChars;

            $chunks[] = mb_substr($remaining, 0, $cut);
            $remaining = ltrim(mb_substr($remaining, $cut));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function dedupeByField(array $items, string $field): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item[$field] ?? null)) {
                $result[] = $item;

                continue;
            }

            $key = Str::lower(trim($item[$field]));
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $excludeGrammarTitles
     */
    private function buildSystemPrompt(string $sourceLanguage, string $translationLanguage, AiAnalysisRunConfig $config, array $excludeGrammarTitles = []): string
    {
        $prompt = "You are a language-learning content analyst. Given a transcript in \"{$sourceLanguage}\", extract two things:\n"
            .'1. Notable words and phrases worth learning: include single words, phrasal verbs, idioms, and collocations — not just isolated tokens.'
            ." For each, give: the exact inflected form as it actually occurs in the transcript (field `text`, e.g. \"ran\") *and separately* its base dictionary/lemma form (field `lemma`, e.g. \"run\" — the infinitive for verbs, singular for nouns, positive degree for adjectives/adverbs; for multi-word phrases/phrasal verbs/idioms/collocations `lemma` is normally the same as `text`, just also normalized to its canonical dictionary form); its part of speech (field `part_of_speech`, one of noun|verb|adjective|adverb|phrase|idiom|preposition|conjunction|pronoun|interjection|other — use `phrase`/`idiom` for the corresponding multi-word `type` rather than forcing a single-word category onto it); if this lemma commonly has more than one distinct, unrelated meaning (e.g. \"run\" = move quickly on foot vs. \"run\" = manage a business; \"bank\" = financial institution vs. \"bank\" = riverbank), a short `sense` gloss in English identifying *which* of those meanings this specific occurrence uses (e.g. \"move quickly on foot\") — omit `sense` entirely when the lemma essentially has one common meaning, don't invent a distinction that isn't real; when `text` is an inflected single-word form, a `grammar` object describing that specific occurrence with only the keys that actually apply (`tense`, `number`, `person`, `degree`, `case`, `aspect`, `is_irregular`) — omit keys that don't apply rather than guessing, and omit `grammar` entirely for phrases/idioms/collocations or when `text` already equals `lemma`; a short translation into \"{$translationLanguage}\"; 2-3 example sentences (field `examples`, each with its own translation into \"{$translationLanguage}\" and a `source` tag) — at least one example must be tagged `source: \"context\"` and be an actual sentence taken verbatim, or as close to verbatim as possible, from the transcript itself (never invent a transcript quote that isn't really there); any further examples can be natural sentences you write yourself, tagged `source: \"generated\"`; a CEFR level estimate (A1-C2) for the word/phrase itself; and a brief note on why it was picked (e.g. where/how it appears in the transcript).\n"
            .'2. Grammar constructions demonstrated in the text (e.g. tense usage, conditionals, passive voice, modal verbs). Each one is published to the shared learner catalog immediately and automatically, with no human review before it goes live — so only propose a construction the transcript actually demonstrates clearly, and be precise rather than exhaustive.'
            ." For each, give: a `title` using the standard, conventional ESL name for the construction (e.g. \"Present Perfect\", \"Passive Voice\", \"First Conditional\", \"Modal Verbs of Obligation\") — never an invented or overly specific variant of a name that already covers the same grammar point (e.g. don't title something \"Present Tense Usage\" when \"Present Simple\" is the standard term), since this exact title is what later videos' extracted grammar gets matched against to avoid creating a duplicate entry for the same construction;"
            ." a one-sentence summary, a structured body, one example sentence from the transcript with its translation into \"{$translationLanguage}\", and a brief note on why it was picked. "
            .GrammarRuleStructureInstructions::text()."\n"
            .($config->thoroughness === AiAnalysisRunConfig::THOROUGHNESS_THOROUGH
                ? 'Be exhaustive with the vocabulary list: extract as many useful words, phrasal verbs, idioms, collocations and short phrases as you can reasonably find in the transcript. Include ordinary content words (nouns, verbs, adjectives and adverbs) as well as advanced expressions; do not return only a small curated handful and do not artificially limit the list. For a transcript chunk longer than 500 characters, return at least 15 distinct lexeme items unless it genuinely contains fewer than 15 distinct content words; for a chunk around 1200–3000 characters, aim for roughly 20–40 items. Exclude only pure function words and truly trivial filler unless the learner level is A1 or unknown, in which case include common content words too because they may still be new to the learner.'
                : 'Keep both lists focused on what an intermediate learner would benefit from — do not list every word in the transcript.')
            .' Scale the size of the word list to the transcript itself — a longer or denser transcript should yield proportionally more items, not a fixed handful regardless of length.';

        if ($config->targetLevel !== null) {
            $prompt .= " Write the example sentences at a {$config->targetLevel} (CEFR) level of complexity — simple grammar and common vocabulary in the sentence itself, even when the target word or phrase is more advanced than that level. This is about the example sentence's complexity, not the CEFR level you assign to the word/phrase itself.";
            $prompt .= " The learner's overall level is {$config->targetLevel}: a lower-level learner finds more of this transcript's vocabulary genuinely new to them, so extract more of it for them; a higher-level learner already knows most everyday words, so stay more selective and only include what's genuinely non-trivial at that level.";
        } elseif ($config->thoroughness === AiAnalysisRunConfig::THOROUGHNESS_THOROUGH) {
            $prompt .= ' The learner level is unknown, so assume a beginner-to-intermediate learner for extraction breadth: include common content words that are useful in context, while still omitting articles, auxiliary verbs, basic pronouns and other pure function words unless they form part of a useful phrase.';
        }

        if ($config->excludeWords !== []) {
            $list = implode(', ', $config->excludeWords);
            $prompt .= " The learner already knows these words/phrases — do not include them: {$list}.";
        }

        if ($excludeGrammarTitles !== []) {
            $list = implode(', ', $excludeGrammarTitles);
            $prompt .= " These grammar constructions are already covered — do not propose them again: {$list}.";
        }

        if ($config->extraInstructions !== null) {
            $prompt .= " Additional instructions from the admin: {$config->extraInstructions}";
        }

        return $prompt;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'lexemes' => [
                [
                    'text' => 'string (exact form as it occurs in the transcript)',
                    'lemma' => 'string (base/dictionary form, e.g. "run" for "ran")',
                    'type' => 'word|phrase|phrasal_verb|idiom|collocation',
                    'part_of_speech' => implode('|', array_keys(LexemeMetadataOptions::partsOfSpeech())),
                    'sense' => 'string, optional (short gloss identifying which meaning of the lemma this is, only when the lemma has more than one common one)',
                    'translation' => 'string',
                    'grammar' => ['tense' => 'string, optional', 'number' => 'string, optional', 'person' => 'string, optional', 'degree' => 'string, optional', 'case' => 'string, optional', 'aspect' => 'string, optional', 'is_irregular' => 'boolean, optional'],
                    'examples' => [
                        ['text' => 'string', 'translation' => 'string', 'source' => 'context|generated'],
                    ],
                    'level' => 'A1|A2|B1|B2|C1|C2', 'note' => 'string', 'confidence' => 'number 0-1',
                ],
            ],
            'grammar' => [
                ['title' => 'string', 'summary' => 'string', 'body' => 'string (markdown)', 'example' => 'string', 'example_translation' => 'string', 'note' => 'string', 'confidence' => 'number 0-1'],
            ],
        ];
    }

    /**
     * @param  mixed  $item
     */
    private function createLexemeCandidate(AiAnalysisRun $run, $item, string $transcript): void
    {
        if (! is_array($item) || ! is_string($item['text'] ?? null) || trim($item['text']) === '') {
            return;
        }

        $text = trim($item['text']);
        // Falls back to `text` when the model omits `lemma` (old fixtures/
        // providers, or a phrase where they're naturally identical) — keeps
        // matching/canonical-lexeme creation working exactly as before this
        // field existed, just no longer collapsing "ran" onto its own lemma.
        $lemma = is_string($item['lemma'] ?? null) && trim($item['lemma']) !== ''
            ? trim($item['lemma'])
            : $text;
        // Task 10.2: requested at extraction time now, instead of only
        // appearing later via the async SuggestLexemeLevelJob — that job
        // stays a fallback for lexemes created without one.
        $partOfSpeech = LexemeCandidateFields::parsePartOfSpeech($item['part_of_speech'] ?? null);
        $level = is_string($item['level'] ?? null) && in_array($item['level'], LexemeMetadataOptions::cefrLevels(), true)
            ? $item['level']
            : null;
        // Task 10.3: distinguishes which meaning of a polysemous lemma this
        // occurrence uses — left null when the model omits it (the common,
        // single-meaning case), which keeps the resulting occurrence/
        // translation/example sense-less rather than forcing one.
        $sense = is_string($item['sense'] ?? null) && trim($item['sense']) !== ''
            ? trim($item['sense'])
            : null;
        $examples = $this->parseExamples($item['examples'] ?? null, $item);
        $primaryExample = $this->pickPrimaryExample($examples);

        $this->candidateStore->createLexeme((int) $run->id, [
            'text' => $text,
            'normalized_text' => Str::lower($text),
            'lemma' => $lemma,
            'normalized_lemma' => Str::lower($lemma),
            'grammar_features' => LexemeCandidateFields::parseGrammarFeatures($item['grammar'] ?? null),
            'type' => $item['type'] ?? null,
            'part_of_speech' => $partOfSpeech,
            'sense' => $sense,
            'level' => $level,
            'frequency' => $this->countOccurrences($transcript, $text),
            'translation' => is_string($item['translation'] ?? null) ? $item['translation'] : null,
            // Task 9.9: `examples` (JSON) is the source of truth going
            // forward — multiple examples, at least one grounded in the
            // actual transcript (source: "context"). The singular
            // example/example_translation columns are kept as a backward-
            // compat mirror of the primary example (Filament's
            // LexemeCandidatesRelationManager table and other simple readers
            // still use them directly), not dropped.
            'examples' => $examples,
            'example' => $primaryExample['text'] ?? null,
            'example_translation' => $primaryExample['translation'] ?? null,
            'note' => is_string($item['note'] ?? null) ? $item['note'] : null,
            'confidence' => is_numeric($item['confidence'] ?? null) ? (float) $item['confidence'] : null,
        ]);
    }

    /**
     * Parses the new `examples` array shape, falling back to the old
     * singular `example`/`example_translation` fields when `examples` is
     * missing or unusable — keeps every existing AiJsonClient mock/fixture
     * (and any real provider that momentarily reverts to the old shape)
     * producing a usable example instead of none.
     *
     * @param  mixed  $rawExamples
     * @param  array<string, mixed>  $item
     * @return array<int, array{text: string, translation: ?string, source: string}>
     */
    private function parseExamples($rawExamples, array $item): array
    {
        $examples = [];

        if (is_array($rawExamples)) {
            foreach ($rawExamples as $raw) {
                if (! is_array($raw) || ! is_string($raw['text'] ?? null) || trim($raw['text']) === '') {
                    continue;
                }

                $source = is_string($raw['source'] ?? null) && in_array($raw['source'], ['context', 'generated'], true)
                    ? $raw['source']
                    : 'generated';

                $examples[] = [
                    'text' => trim($raw['text']),
                    'translation' => is_string($raw['translation'] ?? null) ? $raw['translation'] : null,
                    'source' => $source,
                ];
            }
        }

        if ($examples === [] && is_string($item['example'] ?? null) && trim($item['example']) !== '') {
            $examples[] = [
                'text' => trim($item['example']),
                'translation' => is_string($item['example_translation'] ?? null) ? $item['example_translation'] : null,
                'source' => 'context',
            ];
        }

        return $examples;
    }

    /**
     * Prefers a context-sourced example (grounded in the actual transcript)
     * as the one mirrored onto the singular example/example_translation
     * columns; falls back to the first example otherwise.
     *
     * @param  array<int, array{text: string, translation: ?string, source: string}>  $examples
     * @return array{text: string, translation: ?string, source: string}|null
     */
    private function pickPrimaryExample(array $examples): ?array
    {
        if ($examples === []) {
            return null;
        }

        foreach ($examples as $example) {
            if ($example['source'] === 'context') {
                return $example;
            }
        }

        return $examples[0];
    }

    /**
     * Deterministic occurrence count, not an AI guess. Counts on the same
     * (possibly truncated) transcript text already used for extraction.
     */
    private function countOccurrences(string $transcript, string $text): int
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($text), '/').'(?![\p{L}\p{N}])/u';

        return preg_match_all($pattern, mb_strtolower($transcript)) ?: 0;
    }

    /**
     * @param  mixed  $item
     */
    private function createGrammarCandidate(AiAnalysisRun $run, $item): void
    {
        if (! is_array($item) || ! is_string($item['title'] ?? null) || trim($item['title']) === '') {
            return;
        }

        $this->candidateStore->createGrammar((int) $run->id, [
            'title' => trim($item['title']),
            'summary' => is_string($item['summary'] ?? null) ? $item['summary'] : null,
            'body' => is_string($item['body'] ?? null) ? $item['body'] : null,
            'example' => is_string($item['example'] ?? null) ? $item['example'] : null,
            'example_translation' => is_string($item['example_translation'] ?? null) ? $item['example_translation'] : null,
            'note' => is_string($item['note'] ?? null) ? $item['note'] : null,
            'confidence' => is_numeric($item['confidence'] ?? null) ? (float) $item['confidence'] : null,
        ]);
    }
}
