<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\LessonAnalysisStoreInterface;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use Illuminate\Support\Str;

/**
 * Lesson-scoped analog of `AiContentAnalysisService`, deliberately kept
 * separate rather than generalizing that service to accept a Lesson —
 * `AiContentAnalysisService` also owns transcript-chunking and a
 * coverage-triggered auto-retry built for long video transcripts
 * (docs/architecture, task 9.1); a lesson's accumulated chat/notes text is
 * short by comparison, and reusing that machinery here would mean either
 * threading a Content-shaped abstraction through it or carrying dead
 * complexity into a simpler use case. Both services intentionally use the
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

        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'lesson_analysis.completeJson',
            ['feature' => 'lesson_analysis', 'run_id' => $run->id],
            $rendered->system,
            $text,
            $this->responseSchema(),
            $rendered->model,
        );

        $lexemes = is_array($result['lexemes'] ?? null) ? $result['lexemes'] : [];
        $grammar = is_array($result['grammar'] ?? null) ? $result['grammar'] : [];

        if ($lexemes === [] && $grammar === []) {
            throw new AiClientException('AI analysis found no vocabulary or grammar in these notes.');
        }

        $this->lessons->persistCandidates(
            $runId,
            array_values(array_filter(array_map($this->lexemeAttributes(...), $this->dedupeByField($lexemes, 'text')))),
            array_values(array_filter(array_map($this->grammarAttributes(...), $this->dedupeByField($grammar, 'title')))),
        );
    }

    private function buildSystemPrompt(string $sourceLanguage, string $translationLanguage): string
    {
        return "You are helping a language learner organize their own notes from a tutoring session, written in or about \"{$sourceLanguage}\".\n"
            .'Extract two things:'."\n"
            .'1. Notable words and phrases worth remembering: single words, phrasal verbs, idioms, collocations.'
            ." For each, give: a short translation into \"{$translationLanguage}\"; one example sentence with its translation into \"{$translationLanguage}\" (use an actual sentence from the notes if one is there, otherwise write a natural one); a CEFR level estimate (A1-C2); a brief note on why it stood out.\n"
            .'2. Grammar points the notes mention or demonstrate (tense usage, conditionals, passive voice, modal verbs, etc). For each: a short title, a one-sentence summary, one example sentence with its translation, a brief note.'."\n"
            .'These are informal personal notes, not a polished transcript — extract what is genuinely there, do not invent content that is not implied by the text. If the notes barely mention grammar, return an empty grammar list rather than guessing.';
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
