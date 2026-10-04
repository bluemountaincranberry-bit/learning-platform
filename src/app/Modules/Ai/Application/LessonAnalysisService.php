<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\AiErrorMessage;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\LessonAnalysisStoreInterface;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lesson-scoped analog of `AiContentAnalysisService`, deliberately kept
 * separate rather than generalizing that service to accept a Lesson —
 * `AiContentAnalysisService` also owns a coverage-triggered auto-retry built
 * for video transcripts (docs/architecture, task 9.1), which would mean
 * threading a Content-shaped abstraction through it. Long notes and PDFs are
 * analyzed in parts via the shared `TextChunker`, one traced call per part
 * (docs/architecture/lesson-analysis-coverage.md). Both services intentionally use the
 * same response JSON shape (see responseSchema()) so LessonLexemeCandidate/
 * LessonGrammarCandidate mirror ContentLexemeCandidate/ContentGrammarCandidate
 * column-for-column.
 *
 * Triggered by an explicit "Разобрать урок" action (LessonController::analyze()),
 * never automatically after a chat message — the whole accumulated
 * `Lesson::source_text` is re-analyzed on every call, relying on
 * LessonCandidateMatchingService's embedding dedup against *previous*
 * candidates to avoid piling up duplicates rather than diffing "what's new
 * since last time".
 */
class LessonAnalysisService
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly LessonAnalysisStoreInterface $lessons,
        private readonly TextChunker $chunker = new TextChunker,
    ) {}

    /**
     * @throws AiClientException
     */
    public function analyze(int $runId): void
    {
        $run = $this->lessons->getRun($runId);
        $text = trim($run?->sourceText ?? '');

        if ($text === '') {
            throw new AiClientException('This lesson has no notes yet to analyze.');
        }

        $translationLanguage = config('ai.analysis.translation_language', 'ru');
        $sourceLanguage = $run->language;

        $rendered = $this->promptRegistry->resolve(
            'lesson_analysis_system_prompt',
            ['source_language' => $sourceLanguage, 'translation_language' => $translationLanguage],
            fn () => ['system' => $this->buildSystemPrompt($sourceLanguage, $translationLanguage), 'user' => '']
        );

        // One trace per run; each part of a long document is its own traced,
        // rate-limited call so nothing past the model's attention is dropped.
        $trace = TraceContext::newTrace();
        $chunks = $this->chunker->chunk($text, (int) config('ai.analysis.lesson_chunk_chars', 4000));

        $lexemes = [];
        $grammar = [];

        foreach ($chunks as $index => $chunk) {
            $result = $this->tracedCall->completeJson(
                $this->client,
                $trace,
                'lesson_analysis.completeJson',
                ['feature' => 'lesson_analysis', 'run_id' => $run->id, 'chunk_index' => $index, 'chunk_count' => count($chunks)],
                $rendered->system,
                $chunk,
                $this->responseSchema(),
                $rendered->model,
            );

            $found = is_array($result['lexemes'] ?? null) ? $result['lexemes'] : [];
            array_push($lexemes, ...$found);
            array_push($lexemes, ...$this->bonusLexemes($trace, $run->id, $index, count($chunks), $chunk, $found, $sourceLanguage, $translationLanguage));
            array_push($grammar, ...(is_array($result['grammar'] ?? null) ? $result['grammar'] : []));
        }

        if ($lexemes === [] && $grammar === []) {
            throw new AiClientException('AI analysis found no vocabulary or grammar in these notes.');
        }

        $this->lessons->persistCandidates(
            $runId,
            array_values(array_filter(array_map($this->lexemeAttributes(...), $this->dedupeByField($lexemes, 'text')))),
            array_values(array_filter(array_map($this->grammarAttributes(...), $this->dedupeByField($grammar, 'title')))),
        );
    }

    /**
     * Optional second pass: a failure here is logged and ignored so the main
     * list of a long run is never thrown away over a bonus extra.
     *
     * @param  array<int, mixed>  $found
     * @return array<int, mixed>
     */
    private function bonusLexemes(TraceContext $trace, int $runId, int $index, int $count, string $chunk, array $found, string $sourceLanguage, string $translationLanguage): array
    {
        $already = collect($found)->map(fn ($i) => is_array($i) ? ($i['text'] ?? null) : null)->filter(fn ($t) => is_string($t) && $t !== '')->map(fn ($t) => '- '.$t)->implode("\n");

        try {
            $result = $this->tracedCall->completeJson(
                $this->client,
                $trace,
                'lesson_analysis.bonus.completeJson',
                ['feature' => 'lesson_analysis', 'pass' => 'bonus', 'run_id' => $runId, 'chunk_index' => $index, 'chunk_count' => $count],
                $this->buildBonusPrompt($sourceLanguage, $translationLanguage),
                $chunk."\n\nALREADY EXTRACTED (do not repeat):\n".($already !== '' ? $already : '(none)'),
                ['lexemes' => $this->responseSchema()['lexemes']],
                null,
            );
        } catch (Throwable $e) {
            Log::warning('LessonAnalysisService: bonus pass failed, keeping the main list', ['run_id' => $runId, 'chunk_index' => $index, 'message' => AiErrorMessage::safe($e)]);

            return [];
        }

        return is_array($result['lexemes'] ?? null) ? $result['lexemes'] : [];
    }

    private function buildSystemPrompt(string $sourceLanguage, string $translationLanguage): string
    {
        return "You are helping a language learner turn their own lesson material (handouts, word lists, notes from a tutoring session) into a study list. The material is written in or about \"{$sourceLanguage}\".\n"
            ."Extract two things.\n\n"
            ."1. VOCABULARY ITEMS: everything the learner is meant to memorize. This is NOT limited to single words. An item is any word, phrasal verb, idiom, collocation, fixed expression, discourse phrase, or ready-made sentence/phrase that the material presents for study, for example \"to cut down on sugar\", \"In my experience\", \"On balance, …\", \"You've got a point.\", \"I couldn't agree more\", \"Correct me if I'm wrong, but …\".\n"
            ."   - Treat every numbered, bulleted or line-separated entry of a word list as exactly one item. Return ONE item per entry, for ALL entries, from the first to the very last. A list of 50 entries must give about 50 items.\n"
            ."   - Never skip an entry because it looks simple, is a whole sentence, is a conversational phrase, or is a speaking template with a gap in brackets. Learners study those too.\n"
            ."   - In \"text\" keep the entry as written in the material (drop pronunciation in [brackets] and the list number). If the entry has a bracketed variant like \"to keep in shape / to stay fit\", keep it as one item.\n"
            ."   - If the material itself gives a translation or an example sentence for the entry, reuse it. Otherwise write a short translation into \"{$translationLanguage}\" and one natural example sentence with its translation. Also give a CEFR level estimate (A1-C2) and a brief note.\n"
            ."   - Free-form notes (not a list): extract the notable words and phrases that appear in them.\n"
            ."\n"
            ."Every item's \"text\" must be English text from the material (never a translation into \"{$translationLanguage}\"), and each item appears once.\n\n"
            ."2. GRAMMAR POINTS the material explains or clearly demonstrates (tense usage, conditionals, passive voice, modal verbs, linking structures, etc). For each: a short title, a one-sentence summary, one example sentence with its translation, a brief note. If the material has no grammar, return an empty grammar list rather than guessing.\n\n"
            .'Do not invent content that is not in the material. Before answering, check that nothing from the end of the text was left out.';
    }

    /**
     * Second, narrower pass over the same part: expressions hidden in the
     * example sentences and remarks that the material itself highlights or
     * glosses. Kept separate because one prompt asked to do both the full
     * list and these loses entries from the list (measured, VIK-70).
     */
    private function buildBonusPrompt(string $sourceLanguage, string $translationLanguage): string
    {
        return "You are helping a language learner who studies from their own lesson material, written in or about \"{$sourceLanguage}\".\n"
            ."The main entries of the material were already extracted (you get that list after the text). Your job is ONLY to find ADDITIONAL expressions hidden in the example sentences, explanations and remarks.\n"
            ."Return an expression when the material marks it as worth learning, i.e. it comes with its own gloss: a translation in brackets (like \"break the ice (сломать лёд)\"), a pronunciation in [brackets], a definition after \"=\" or \"–\", or an \"NB\" remark. Also return a clearly idiomatic expression, collocation or phrasal verb used in an example sentence even without a gloss.\n"
            ."Take the word or short phrase itself (\"break the ice\", \"exertion\"), not the whole sentence. Skip plain everyday words with no gloss, anything already in the extracted list, any part or shorter form of an already extracted entry (\"take a step back\" when \"To take a step back for a moment\" is listed), and anything not written in the material. \"text\" must be English text from the material, never a translation into \"{$translationLanguage}\".\n"
            ."For each: a short translation into \"{$translationLanguage}\" (reuse the gloss from the material if there is one), the example sentence it appears in with its translation, a CEFR level (A1-C2), a \"note\" saying where it comes from, and \"confidence\" 0.7-0.85. Return an empty list if there is nothing to add.";
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'lexemes' => [
                [
                    'text' => 'string', 'type' => 'word|phrase|phrasal_verb|idiom|collocation', 'translation' => 'string',
                    'example' => 'string', 'example_translation' => 'string',
                    'level' => 'A1|A2|B1|B2|C1|C2', 'note' => 'string', 'confidence' => 'number 0-1',
                ],
            ],
            'grammar' => [
                ['title' => 'string', 'summary' => 'string', 'example' => 'string', 'example_translation' => 'string', 'note' => 'string', 'confidence' => 'number 0-1'],
            ],
        ];
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

            $key = $this->dedupeKey($item[$field]);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Same item written with a curly vs straight apostrophe, different
     * spacing or a trailing period/ellipsis counts as one.
     */
    private function dedupeKey(string $value): string
    {
        $value = Str::lower(str_replace(['’', '‘'], "'", $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return rtrim(trim($value), " .,…!?;:");
    }

    /**
     * @param  mixed  $item
     */
    private function lexemeAttributes($item): ?array
    {
        if (! is_array($item) || ! is_string($item['text'] ?? null) || trim($item['text']) === '') {
            return null;
        }

        $text = trim($item['text']);
        $level = is_string($item['level'] ?? null) && preg_match('/^[ABC][12]$/', $item['level'])
            ? $item['level']
            : null;

        return [
            'text' => $text,
            'normalized_text' => Str::lower($text),
            'type' => $item['type'] ?? null,
            'level' => $level,
            'translation' => is_string($item['translation'] ?? null) ? $item['translation'] : null,
            'example' => is_string($item['example'] ?? null) ? $item['example'] : null,
            'example_translation' => is_string($item['example_translation'] ?? null) ? $item['example_translation'] : null,
            'note' => is_string($item['note'] ?? null) ? $item['note'] : null,
            'confidence' => is_numeric($item['confidence'] ?? null) ? (float) $item['confidence'] : null,
        ];
    }

    /**
     * @param  mixed  $item
     */
    private function grammarAttributes($item): ?array
    {
        if (! is_array($item) || ! is_string($item['title'] ?? null) || trim($item['title']) === '') {
            return null;
        }

        return [
            'title' => trim($item['title']),
            'summary' => is_string($item['summary'] ?? null) ? $item['summary'] : null,
            'example' => is_string($item['example'] ?? null) ? $item['example'] : null,
            'example_translation' => is_string($item['example_translation'] ?? null) ? $item['example_translation'] : null,
            'note' => is_string($item['note'] ?? null) ? $item['note'] : null,
            'confidence' => is_numeric($item['confidence'] ?? null) ? (float) $item['confidence'] : null,
        ];
    }
}
