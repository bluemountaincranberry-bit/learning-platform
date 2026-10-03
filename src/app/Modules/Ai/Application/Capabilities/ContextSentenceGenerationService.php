<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Data\ContextSentenceGenerationResult;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\ContextSentenceGenerationCapability;
use App\Contracts\Ai\PromptRegistryInterface;

final class ContextSentenceGenerationService implements ContextSentenceGenerationCapability
{
    public function __construct(
        private readonly AiJsonClient $jsonClient,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly TracedLlmCall $tracedCall,
    ) {}

    /**
     * @return array{sentence: string, target_form: string, translation: string, distractors: list<string>}
     *
     * @throws AiClientException
     */
    public function generate(string $lexemeText, string $targetLanguage, string $nativeLanguage, ?string $topic = null): ContextSentenceGenerationResult
    {
        $rendered = $this->promptRegistry->resolve(
            'ai_generate_context_sentence',
            ['lexeme' => $lexemeText, 'target_language' => $targetLanguage, 'native_language' => $nativeLanguage, 'topic' => $topic ?? ''],
            function () use ($lexemeText, $targetLanguage, $nativeLanguage, $topic): array {
                $userPrompt = "Word/phrase: \"{$lexemeText}\". Translate the sentence into \"{$nativeLanguage}\".";
                if ($topic !== null && $topic !== '') {
                    $userPrompt .= " If it fits naturally, relate the sentence to this topic: \"{$topic}\".";
                }

                return [
                    'system' => 'You are a language tutor writing one short, natural example sentence for a learner. '
                    ."Write exactly one sentence in \"{$targetLanguage}\" that naturally uses the given word or phrase. "
                    .'Natural conjugation, declension, pluralization, and other grammatical inflections are allowed and preferred when they make the sentence natural. '
                    .'Return the exact form used in the sentence as target_form, including its inflection. '
                    .'Also return exactly three wrong distractors for a cloze exercise. Distractors should be the same general part of speech and '
                    .'a plausible competing form, but each must make the complete sentence clearly wrong in meaning or usage. '
                    .'Never use synonyms, near-synonyms, interchangeable words, or alternatives that could also be correct in this sentence. '
                    .'There must be exactly one defensible answer: target_form. They do not have to be studied words. '
                    .'Also return its translation. Keep it short and simple — one sentence, not a paragraph or story.',
                    'user' => $userPrompt,
                ];
            }
        );

        $schema = [
            'sentence' => "string, exactly one sentence in {$targetLanguage} that naturally uses the word/phrase",
            'target_form' => 'string, the exact word or phrase form from the sentence that corresponds to the requested word/phrase; may be conjugated, declined, or pluralized',
            'translation' => "string, that sentence translated into {$nativeLanguage}",
            'distractors' => 'array of exactly three plausible alternative forms for the blank',
        ];
        $userPrompt = $rendered->user
            ."\nUse a natural grammatical form of the requested word or phrase when appropriate. Return that exact form as target_form, and ensure target_form appears verbatim in sentence.";

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $this->tracedCall->completeJson(
                $this->jsonClient,
                TraceContext::newTrace(),
                'explain_lexeme.generate_context_sentence',
                ['feature' => 'explain_lexeme', 'attempt' => $attempt + 1],
                $rendered->system,
                $userPrompt,
                $schema,
                $rendered->model,
            );

            $sentence = is_string($result['sentence'] ?? null) ? trim($result['sentence']) : '';
            $targetForm = is_string($result['target_form'] ?? null) ? trim($result['target_form']) : '';
            $translation = is_string($result['translation'] ?? null) ? trim($result['translation']) : '';
            $distractors = is_array($result['distractors'] ?? null)
                ? array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : '', $result['distractors'])))
                : [];
            $distractors = array_values(array_filter($distractors, fn (string $item) => mb_strtolower($item) !== mb_strtolower($targetForm)));

            if ($sentence !== '' && $targetForm !== '' && $translation !== '' && count($distractors) === 3 && mb_stripos($sentence, $targetForm) !== false) {
                return new ContextSentenceGenerationResult(sentence: $sentence, targetForm: $targetForm, translation: $translation, distractors: array_slice($distractors, 0, 3));
            }

            $userPrompt = $rendered->user
                ."\nThe previous response was invalid. Return target_form exactly as it appears in sentence, including any conjugation, plural, case, or punctuation. The sentence must contain that exact target_form. Return exactly three distractors, and make sure none is a synonym or another answer that could fit the sentence.";
        }

        throw new AiClientException('AI did not return a usable sentence with a matching target form.');
    }
}
