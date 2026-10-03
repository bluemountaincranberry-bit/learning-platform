<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Data\GeneratedSentenceCard;
use App\Modules\Ai\Application\Data\SentenceGenerationInput;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Contracts\Ai\SentenceGenerationCapability;

final class SentenceGenerationService implements SentenceGenerationCapability
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
    ) {}

    public function generate(SentenceGenerationInput $input): array
    {
        $context = $input->context();
        $direction = $input->direction;
        $count = $input->count;
        $promptLanguage = $direction === 'to_native' ? $context['target_language'] : $context['native_language'];
        $answerLanguage = $direction === 'to_native' ? $context['native_language'] : $context['target_language'];
        $rendered = $this->promptRegistry->resolve(
            'sentence_practice_generate_system_prompt',
            [
                'count' => (string) $count,
                'prompt_language' => (string) $promptLanguage,
                'answer_language' => (string) $answerLanguage,
                'words' => implode(', ', $context['words']),
                'grammar_topics' => implode(', ', $context['grammar_topics']),
            ],
            fn () => ['system' => $this->buildPrompt($count, (string) $promptLanguage, (string) $answerLanguage, $context['words'], $context['grammar_topics']), 'user' => '']
        );

        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'sentence_practice.generate',
            ['feature' => 'sentence_practice', 'direction' => $direction],
            $rendered->system,
            'Generate the sentences now.',
            ['sentences' => [['text' => 'string', 'translation' => 'string', 'uses' => 'array of strings']]],
            $rendered->model,
        );

        $cards = [];
        foreach (is_array($result['sentences'] ?? null) ? $result['sentences'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = is_string($item['text'] ?? null) ? trim($item['text']) : '';
            $answer = is_string($item['translation'] ?? null) ? trim($item['translation']) : '';
            if ($text === '' || $answer === '') {
                continue;
            }
            $uses = is_array($item['uses'] ?? null) ? array_values(array_filter($item['uses'], 'is_string')) : [];
            $cards[] = new GeneratedSentenceCard($text, $answer, (string) $promptLanguage, (string) $answerLanguage, $uses);
        }

        if ($cards === []) {
            throw new AiClientException('AI did not return any usable practice sentences.');
        }

        return $cards;
    }

    /** @param list<string> $words @param list<string> $grammarTopics */
    private function buildPrompt(int $count, string $promptLanguage, string $answerLanguage, array $words, array $grammarTopics): string
    {
        $prompt = "You are a language-learning exercise writer. Write {$count} short, natural, everyday sentences in \"{$promptLanguage}\" for a learner who will translate each one into \"{$answerLanguage}\". Each sentence should be simple, grammatically correct, and use at least one listed learning word. Prioritize the first words and grammar points because they are weak areas.\n\n";
        if ($words !== []) {
            $prompt .= 'Recently studied words/phrases: '.implode(', ', $words)."\n";
        }
        if ($grammarTopics !== []) {
            $prompt .= 'Recently studied grammar points: '.implode(', ', $grammarTopics)."\n";
        }

        return $prompt.'For each sentence, provide a natural translation as "translation" and list used items in "uses".';
    }
}
