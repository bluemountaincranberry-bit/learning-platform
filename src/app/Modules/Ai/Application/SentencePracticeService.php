<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\ContentExamGenerationCapability;
use App\Contracts\Ai\SentenceAnswerGradingCapability;
use App\Contracts\Ai\SentenceGenerationCapability;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Data\SentenceAnswerGradingInput;
use App\Modules\Ai\Application\Data\SentenceGenerationInput;
use App\Modules\Content\Application\Contracts\SentencePracticeCatalogInterface;
use App\Modules\Learning\Application\Contracts\SentencePracticeLearnerContextInterface;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sentence-production practice: short AI-generated sentences built from the
 * words the learner explicitly has in their learning queue, which the learner translates in either direction, plus
 * AI grading of the free-text answer (semantic equivalence, not exact
 * string match — a real translation has many correct phrasings).
 *
 * Same ADR-002 "Tool, not Agent" shape as AiGrammarExerciseService/
 * GenerateQuizTool: ready inputs in, one capability/provider call, sanitize
 * structured output, no self-correction loop. Deliberately
 * stateless — no new table — mirroring the restraint already applied
 * elsewhere in this codebase (e.g. roadmap's decision to skip ExerciseAgent
 * and DB-backed graphs until real usage shows a single call isn't enough).
 */
class SentencePracticeService implements ContentExamGenerationCapability
{
    public const DIRECTION_TO_TARGET = 'to_target'; // native sentence shown -> learner writes in the language they're learning

    public const DIRECTION_TO_NATIVE = 'to_native'; // target-language sentence shown -> learner translates to their own language

    private const LEARNING_WORDS_LIMIT = 10;

    private const RECENT_GRAMMAR_LIMIT = 5;

    public function __construct(
        private readonly SentenceGenerationCapability $sentenceGeneration,
        private readonly SentenceAnswerGradingCapability $sentenceGrading,
        private readonly SentencePracticeLearnerContextInterface $learnerContext,
        private readonly SentencePracticeCatalogInterface $catalog,
    ) {}

    public function generateContentExam(int $userId, int $contentId, int $count): array
    {
        return $this->generateExamForIds($userId, $contentId, $count);
    }

    public function generateForContentId(Authenticatable $user, int $contentId, string $direction, int $count = 5): array
    {
        return $this->generateForContent($user, $contentId, $direction, $count);
    }

    public function generateGrammarWarmup(int $userId, int $contentId, array $grammarRuleIds, int $count): array
    {
        return $this->generateForRules(
            $userId,
            $contentId,
            $this->catalog->grammarRules($contentId, $grammarRuleIds),
            $count,
        );
    }

    /**
     * @return array{native_language: string, target_language: ?string, words: list<string>, grammar_topics: list<string>}
     */
    public function recentContext(Authenticatable $user): array
    {
        $userId = (int) $user->getAuthIdentifier();
        $nativeLanguage = $this->learnerContext->nativeLanguage($userId);

        $learningLexemes = $this->prioritizeLearningLexemes($userId, $this->catalog->learningLexemes($userId));

        $targetLanguage = $learningLexemes
            ->map(fn (array $lexeme) => $lexeme['language'])
            ->first(fn (?string $lang) => $lang !== null);

        $words = $learningLexemes
            ->pluck('word')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $recentGrammar = collect($this->learnerContext->recentGrammar($userId, self::RECENT_GRAMMAR_LIMIT));

        if ($targetLanguage === null) {
            $targetLanguage = $recentGrammar
                ->map(fn (array $grammar): ?string => $grammar['language'])
                ->first(fn (?string $lang) => $lang !== null);
        }

        $grammarTopics = $recentGrammar
            ->map(fn (array $grammar): ?string => $grammar['title'])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'native_language' => $nativeLanguage,
            'target_language' => $targetLanguage,
            'words' => $words,
            'grammar_topics' => $grammarTopics,
        ];
    }

    /**
     * Content-scoped counterpart to recentContext() — only words from this
     * content that the current learner explicitly started learning.
     *
     * @return array{native_language: string, target_language: ?string, words: list<string>, grammar_topics: list<string>}
     */
    public function contentContext(Authenticatable $user, int|object $content): array
    {
        $userId = (int) $user->getAuthIdentifier();
        $contentId = $this->contentId($content);
        $learningLexemes = $this->prioritizeLearningLexemes($userId, $this->catalog->learningLexemes($userId, $contentId));
        $words = $learningLexemes
            ->pluck('word')
            ->filter()
            ->unique()
            ->values()
            ->take(self::LEARNING_WORDS_LIMIT)
            ->all();

        $grammarTopics = $this->prioritizeContentGrammar($userId, $this->catalog->grammarRules($contentId))
            ->pluck('title')
            ->filter()
            ->unique()
            ->take(self::RECENT_GRAMMAR_LIMIT)
            ->values()
            ->all();

        return [
            'native_language' => $this->learnerContext->nativeLanguage($userId),
            'target_language' => $this->catalog->language($contentId),
            'words' => $words,
            'grammar_topics' => $grammarTopics,
        ];
    }

    /**
     * @return array{cards: list<array{prompt_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>}>, note: ?string}
     *
     * @throws AiClientException
     */
    public function generateBatch(Authenticatable $user, string $direction, int $count = 5): array
    {
        $context = $this->recentContext($user);

        if ($context['target_language'] === null || empty($context['words'])) {
            return [
                'cards' => [],
                'note' => 'Start learning a few words first — sentence practice uses words from your learning list.',
            ];
        }

        return ['cards' => $this->generateFromContext($context, $direction, $count), 'note' => null];
    }

    /**
     * Content-scoped generation ("Reinforce") — same shape as generateBatch()
     * but sourced from contentContext() instead of the learner's global
     * recent-study pool.
     *
     * @return array{cards: list<array{prompt_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>}>, note: ?string}
     *
     * @throws AiClientException
     */
    public function generateForContent(Authenticatable $user, int|object $content, string $direction, int $count = 5): array
    {
        $context = $this->contentContext($user, $content);

        if ($context['target_language'] === null || empty($context['words'])) {
            return [
                'cards' => [],
                'note' => 'You have no words from this content in your learning list yet.',
            ];
        }

        return ['cards' => $this->generateFromContext($context, $direction, $count), 'note' => null];
    }

    /**
     * The "Ready to watch" exam — a fixed-length, mixed-direction session
     * built entirely from this content's own words/grammar. Mixed direction
     * is implemented as two separate generation calls (half to_target, half
     * to_native) rather than asking the model to vary direction per
     * sentence within one call — each card already fully encodes its own
     * direction via prompt_language/answer_language, so the response shape
     * needs no change, and each sub-call stays a single, simple, uniform
     * prompt (same ADR-002 shape as everything else in this class).
     *
     * @return list<array{prompt_sentence: string, answer_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>}>
     *
     * @throws AiClientException
     */
    public function generateExam(Authenticatable $user, int|object $content, int $count): array
    {
        $context = $this->contentContext($user, $content);

        return $this->generateExamFromContext($context, $count);
    }

    private function generateExamFromContext(array $context, int $count): array
    {

        if ($context['target_language'] === null || empty($context['words'])) {
            throw new AiClientException('This content has no words or grammar to build an exam from.');
        }

        $toTargetCount = intdiv($count, 2);
        $toNativeCount = $count - $toTargetCount;

        $cards = [];
        if ($toTargetCount > 0) {
            $cards = [...$cards, ...$this->generateFromContext($context, self::DIRECTION_TO_TARGET, $toTargetCount)];
        }
        if ($toNativeCount > 0) {
            $cards = [...$cards, ...$this->generateFromContext($context, self::DIRECTION_TO_NATIVE, $toNativeCount)];
        }

        shuffle($cards);

        return $cards;
    }

    /**
     * Grammar warm-up (pre/post-exam): unlike generateExam(), which draws on
     * every word/grammar point of the content at once, this generates cards
     * for only the caller-chosen subset of grammar rules — and, unlike
     * generateExam()'s cards, each one is tagged with the specific
     * grammar_rule_id it was generated for. That tag is assigned here
     * deterministically (one generateFromContext() call per rule, with only
     * that rule's title as context) rather than trusting the model's "uses"
     * list to name the right rule back — grading confidence per topic needs
     * a reliable rule attribution, not a fuzzy string match.
     *
     * @param  list<array{id: int, title: string}>  $grammarRules
     * @return list<array{prompt_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>, grammar_rule_id: int}>
     *
     * @throws AiClientException
     */
    public function generateForRules(int $userId, int $contentId, array $grammarRules, int $totalCount): array
    {
        if ($grammarRules === []) {
            throw new AiClientException('Select at least one grammar topic to practice.');
        }

        $words = $this->catalog->words($contentId);
        $nativeLanguage = $this->learnerContext->nativeLanguage($userId);
        $targetLanguage = $this->catalog->language($contentId);

        $rules = array_values($grammarRules);
        $perRule = max(1, intdiv($totalCount, count($rules)));

        $cards = [];
        foreach ($rules as $i => $rule) {
            $direction = $i % 2 === 0 ? self::DIRECTION_TO_TARGET : self::DIRECTION_TO_NATIVE;
            $context = [
                'native_language' => $nativeLanguage,
                'target_language' => $targetLanguage,
                'words' => $words,
                'grammar_topics' => [$rule['title']],
            ];

            foreach ($this->generateFromContext($context, $direction, $perRule) as $card) {
                $card['grammar_rule_id'] = $rule['id'];
                $cards[] = $card;
            }
        }

        shuffle($cards);

        return $cards;
    }

    /**
     * Put the learner's active frontier first: relearning/new cards, then
     * words with low skill confidence, then comfortable review cards.
     * Already-known words without an SRS card never enter this pool.
     *
     * @param  list<array{id: int, content_id: int, word: string, language: ?string, review_state: ?string}>  $lexemes
     */
    private function prioritizeLearningLexemes(int $userId, array $lexemes): \Illuminate\Support\Collection
    {
        $lexemes = collect($lexemes);
        if ($lexemes->isEmpty()) {
            return $lexemes;
        }

        $confidenceAverages = $this->learnerContext->lexemeConfidenceAverages($userId, $lexemes->pluck('id')->all());

        return $lexemes
            ->sortBy(function (array $lexeme) use ($confidenceAverages): array {
                $average = $confidenceAverages[$lexeme['id']] ?? 0;

                $statePriority = match ($lexeme['review_state']) {
                    'relearning' => 0,
                    'new' => 1,
                    'reviewing' => 2,
                    default => 3,
                };

                return [$statePriority, $average, $lexeme['id']];
            })
            ->take(self::LEARNING_WORDS_LIMIT)
            ->values();
    }

    /**
     * Content grammar is the source of truth for content-scoped practice.
     * Rules currently being learned and rules with lower calculated
     * confidence come before already learned rules.
     *
     * @param  list<array{id: int, title: string}>  $rules
     */
    private function prioritizeContentGrammar(int $userId, array $rules): \Illuminate\Support\Collection
    {
        $rules = collect($rules);
        if ($rules->isEmpty()) {
            return $rules;
        }

        $progress = $this->learnerContext->grammarProgress($userId, $rules->pluck('id')->all());

        return $rules
            ->sortBy(function (array $rule) use ($progress): array {
                $item = $progress[$rule['id']] ?? null;
                $statusPriority = ($item['status'] ?? null) === 'learning'
                    ? 0
                    : (($item['status'] ?? null) === 'learned' ? 2 : 1);

                return [$statusPriority, $item['confidence_calculated'] ?? 0.0, $rule['id']];
            })
            ->values();
    }

    private function generateExamForIds(int $userId, int $contentId, int $count): array
    {
        $context = $this->contentContextForIds($userId, $contentId);

        return $this->generateExamFromContext($context, $count);
    }

    /** @return array{native_language: string, target_language: string, words: list<string>, grammar_topics: list<string>} */
    private function contentContextForIds(int $userId, int $contentId): array
    {
        $learningLexemes = $this->prioritizeLearningLexemes($userId, $this->catalog->learningLexemes($userId, $contentId));

        return [
            'native_language' => $this->learnerContext->nativeLanguage($userId),
            'target_language' => $this->catalog->language($contentId),
            'words' => $learningLexemes->pluck('word')->filter()->unique()->values()->take(self::LEARNING_WORDS_LIMIT)->all(),
            'grammar_topics' => $this->prioritizeContentGrammar($userId, $this->catalog->grammarRules($contentId))->pluck('title')->take(self::RECENT_GRAMMAR_LIMIT)->values()->all(),
        ];
    }

    private function contentId(int|object $content): int
    {
        return is_int($content) ? $content : (int) $content->id;
    }

    /**
     * @param  array{native_language: string, target_language: ?string, words: list<string>, grammar_topics: list<string>}  $context
     * @return list<array{prompt_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>}>
     *
     * @throws AiClientException
     */
    private function generateFromContext(array $context, string $direction, int $count): array
    {
        return array_map(
            static fn ($card): array => $card->toArray(),
            $this->sentenceGeneration->generate(new SentenceGenerationInput(
                nativeLanguage: (string) $context['native_language'],
                targetLanguage: $context['target_language'] !== null ? (string) $context['target_language'] : null,
                words: $context['words'],
                grammarTopics: $context['grammar_topics'],
                direction: $direction,
                count: $count,
            )),
        );

    }

    /**
     * @return array{correct: bool, feedback: string, model_answer: string}
     *
     * @throws AiClientException
     */
    public function checkAnswer(string $promptSentence, string $promptLanguage, string $answerLanguage, string $answer, string $checkMode = 'flexible'): array
    {
        return $this->sentenceGrading->grade(new SentenceAnswerGradingInput(
            promptSentence: $promptSentence,
            promptLanguage: $promptLanguage,
            answerLanguage: $answerLanguage,
            answer: $answer,
            checkMode: $checkMode,
        ))->toArray();

    }
}
