<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleWriterInterface;
use App\Modules\Content\Application\Data\GrammarRuleExampleSource;

/**
 * VIK-39: writes example sentences for a grammar rule — affirmative,
 * negative, question and typical-mistake examples at the rule's level, each
 * with the grammar form marked (`**…**`) and a translation. One structured
 * JSON call (ADR-002 "tool, not agent"), same shape as
 * AiGrammarExerciseService. Examples go straight into the catalog as
 * origin = ai (PO decision: catalog enrichment, learners can hide bad ones);
 * Content validates and dedups them.
 */
class AiGrammarRuleExampleService
{
    public const PROMPT_KEY = 'grammar_examples_system_prompt';

    private const DEFAULT_LEVEL = 'A2–B1';

    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly GrammarRuleExampleWriterInterface $writer,
    ) {}

    /**
     * @return int how many examples were stored
     *
     * @throws AiClientException when the call fails or nothing usable came back
     */
    public function generate(int $ruleId, int $count, ?string $translationLanguage): int
    {
        $rule = $this->writer->source($ruleId);
        $rendered = $this->promptRegistry->resolve(
            self::PROMPT_KEY,
            [
                'rule_title' => $rule->title,
                'topic_name' => $rule->topicName,
                'language' => $rule->language,
                'level' => $rule->level ?? self::DEFAULT_LEVEL,
                'count' => (string) $count,
                'translation_language' => $translationLanguage ?? '',
            ],
            fn () => ['system' => $this->buildSystemPrompt($rule, $count, $translationLanguage), 'user' => '']
        );

        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'grammar_examples.completeJson',
            ['feature' => 'grammar_examples', 'grammar_rule_id' => $rule->ruleId],
            $rendered->system,
            'Write the examples now.',
            $this->responseSchema(),
            $rendered->model,
        );

        $created = $this->writer->append($ruleId, $this->items($result, $count), $translationLanguage, $count);

        if ($created === 0) {
            throw new AiClientException('AI did not return any usable examples.');
        }

        return $created;
    }

    private function buildSystemPrompt(GrammarRuleExampleSource $rule, int $count, ?string $translationLanguage): string
    {
        $level = $rule->level ?? self::DEFAULT_LEVEL;

        $prompt = "You write example sentences for language learners. Write {$count} example sentences in \"{$rule->language}\" for the grammar point \"{$rule->title}\""
            .($rule->topicName !== '' ? " (topic: {$rule->topicName})" : '')
            .", based on this explanation:\n\n{$rule->summary}\n\n{$rule->body}\n\n"
            ."Level: {$level} — short (max 12 words), natural, everyday sentences a learner at this level understands.\n"
            .'Every sentence must be one a teacher would pick to show THIS grammar point: the grammar point itself carries the sentence, it is not just somewhere in it. '
            .'A sentence that merely contains a related word (e.g. "said" without reported content, an article in a sentence about something else) does not count. Check each sentence before you write it.'."\n"
            .'Wrap exactly the words that form the grammar point in double asterisks, e.g. "She **doesn\'t like** coffee." or "**Have** you ever **been** to Rome?". '
            .'Mark only the grammar form, not the whole sentence, but all of its parts — in questions and negatives too ("**Was** the car **fixed**?", "**Can** you **swim**?"). Never use asterisks in "wrong" or "translation".'."\n"
            .'"examples": mix kinds — at least one "affirmative", one "negative" and one "question", each a form OF THE GRAMMAR POINT (e.g. for reported speech: "She said she **didn\'t know**", "He **asked if** I was ready").'."\n"
            .'"mistakes": 1-2 errors learners typically make with this rule. Write "wrong" first — the INCORRECT sentence a learner writes — then "correct", the fixed sentence, marked as above. '
            .'They differ exactly in this grammar point (e.g. first conditional: wrong "If it will rain, we will stay." → correct "If it **rains**, we **will stay**."; reported speech: wrong "She told that she was tired." → correct "She **told me** that she was tired."). '
            .'Never invent spelling mistakes or verb forms nobody writes ("builded"); if you are not sure what learners get wrong with this rule, leave "mistakes" empty. '
.'Write '.$count.' sentences in "examples" plus 1-2 in "mistakes". Use different subjects, verbs and situations; no two sentences alike.';

        if ($translationLanguage !== null) {
            $prompt .= "\nGive each sentence a natural translation into \"{$translationLanguage}\" (no asterisks in the translation).";
        }

        if ($rule->existingExamples !== []) {
            $prompt .= "\nThe rule already has these examples — do not repeat them or write near-copies:\n- "
                .implode("\n- ", array_slice($rule->existingExamples, 0, 30));
        }

        return $prompt;
    }

    /**
     * Mistakes come as their own list with the error written first — with
     * one mixed list the model kept storing the error as the correct sentence.
     * The model writes $count examples plus 1-2 mistakes; the writer keeps
     * $count in order, so mistakes go in before the surplus examples, which
     * then refill whatever Content drops (unmarked, duplicate, …).
     *
     * @param  array<string, mixed>  $result
     * @return list<mixed>
     */
    private function items(array $result, int $count): array
    {
        $examples = $this->roundRobinByKind(is_array($result['examples'] ?? null) ? array_values($result['examples']) : []);
        $mistakes = [];

        foreach (is_array($result['mistakes'] ?? null) ? array_slice($result['mistakes'], 0, 2) : [] as $mistake) {
            if (is_array($mistake)) {
                $mistakes[] = [
                    'text' => $mistake['correct'] ?? null,
                    'kind' => 'mistake',
                    'wrong' => $mistake['wrong'] ?? null,
                    'translation' => $mistake['translation'] ?? null,
                ];
            }
        }

        $head = max(0, $count - count($mistakes));

        return [...array_slice($examples, 0, $head), ...$mistakes, ...array_slice($examples, $head)];
    }

    /**
     * Orders examples affirmative, negative, question, affirmative, … so
     * cutting the list to $count keeps every kind the model wrote.
     *
     * @param  list<mixed>  $examples
     * @return list<mixed>
     */
    private function roundRobinByKind(array $examples): array
    {
        $groups = [];
        foreach ($examples as $example) {
            $kind = is_array($example) && is_string($example['kind'] ?? null) ? $example['kind'] : '';
            $groups[$kind][] = $example;
        }

        $ordered = [];
        while ($groups !== []) {
            foreach ($groups as $kind => $items) {
                $ordered[] = array_shift($groups[$kind]);
                if ($groups[$kind] === []) {
                    unset($groups[$kind]);
                }
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'examples' => [
                [
                    'text' => 'string, a correct sentence with the grammar form wrapped in **double asterisks**',
                    'kind' => 'affirmative|negative|question',
                    'translation' => 'string',
                ],
            ],
            'mistakes' => [
                [
                    'wrong' => 'string, the INCORRECT sentence a learner typically writes (no asterisks)',
                    'correct' => 'string, the fixed sentence with the grammar form wrapped in **double asterisks**',
                    'translation' => 'string, translation of the correct sentence',
                ],
            ],
        ];
    }
}
