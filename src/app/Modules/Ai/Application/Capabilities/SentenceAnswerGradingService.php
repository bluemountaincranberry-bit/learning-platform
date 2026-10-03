<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Data\SentenceAnswerGradingInput;
use App\Modules\Ai\Application\Data\SentenceAnswerGradingResult;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Contracts\Ai\SentenceAnswerGradingCapability;

final class SentenceAnswerGradingService implements SentenceAnswerGradingCapability
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
    ) {}

    public function grade(SentenceAnswerGradingInput $input): SentenceAnswerGradingResult
    {
        $rendered = $this->promptRegistry->resolve(
            'sentence_practice_check_system_prompt',
            ['promptSentence' => $input->promptSentence, 'promptLanguage' => $input->promptLanguage, 'answerLanguage' => $input->answerLanguage, 'checkMode' => $input->checkMode],
            fn () => ['system' => $this->buildPrompt($input->promptSentence, $input->promptLanguage, $input->answerLanguage, $input->checkMode), 'user' => '']
        );
        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'sentence_practice.check',
            ['feature' => 'sentence_practice'],
            $rendered->system,
            "Learner's answer: {$input->answer}",
            ['correct' => 'boolean', 'feedback' => 'string', 'model_answer' => 'string'],
            $rendered->model,
        );
        if (! is_bool($result['correct'] ?? null)) {
            throw new AiClientException('AI did not return a usable grading result.');
        }

        return new SentenceAnswerGradingResult(
            correct: $result['correct'],
            feedback: is_string($result['feedback'] ?? null) ? $result['feedback'] : '',
            modelAnswer: is_string($result['model_answer'] ?? null) ? $result['model_answer'] : '',
        );
    }

    private function buildPrompt(string $promptSentence, string $promptLanguage, string $answerLanguage, string $checkMode): string
    {
        return "You are grading a language learner's translation. The original sentence, in \"{$promptLanguage}\", is: \"{$promptSentence}\". The learner tried to translate it into \"{$answerLanguage}\". ".($checkMode === 'exact' ? 'Judge strictly, allowing only harmless capitalization, whitespace, and punctuation differences.' : 'Judge meaning and grammatical acceptability leniently because many phrasings can be correct.').' Reply with short encouraging feedback and one natural example correct translation.';
    }
}
