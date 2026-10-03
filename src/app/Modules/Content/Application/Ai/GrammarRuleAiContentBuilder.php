<?php

namespace App\Modules\Content\Application\Ai;

use App\Contracts\Ai\AiEditablePrompt;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Content\Application\Data\GrammarRuleStructureInstructions;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The only current implementation of AiEditablePrompt. Used both by the
 * standalone "AI: Draft/Improve" admin action (via AiFieldEditService) and,
 * for its structureInstructions() half, by the batch content-analysis
 * pipeline (AiContentAnalysisService) — one shared description of "what a
 * good grammar body looks like" instead of two prompts drifting apart.
 */
class GrammarRuleAiContentBuilder implements AiEditablePrompt
{
    public function __construct(private readonly PromptRegistryInterface $promptRegistry) {}

    public function buildPrompt(Model $subject, ?string $instruction): array
    {
        /** @var GrammarRule $subject */
        $language = $subject->language ?? 'en';
        $explanationLanguage = config('ai.analysis.translation_language', 'ru');
        $topicName = $subject->topic?->name;

        $examples = $subject->examples()
            ->limit(5)
            ->pluck('example')
            ->filter()
            ->implode(' | ');

        $rendered = $this->promptRegistry->resolve(
            'field_edit_grammar_rule_system_prompt',
            [
                'rule_title' => $subject->title,
                'topic_name' => $topicName ?? '',
                'language' => $language,
                'explanation_language' => $explanationLanguage,
                'structure_instructions' => self::structureInstructions(),
                'examples' => $examples,
            ],
            function () use ($subject, $topicName, $language, $explanationLanguage, $examples) {
                $system = "You are a grammar reference writer for language learners. Write about the grammar point \"{$subject->title}\""
                    .($topicName ? " (topic: {$topicName})" : '')
                    ." in \"{$language}\". Write the explanation itself in \"{$explanationLanguage}\".\n\n"
                    .self::structureInstructions();

                if ($examples !== '') {
                    $system .= "\n\nExisting examples for this rule, for context: {$examples}";
                }

                return ['system' => $system, 'user' => ''];
            }
        );

        return [
            'system' => $rendered->system,
            'user' => $instruction ?: 'Write a short summary and a structured explanation for this grammar rule.',
            'schema' => [
                'summary' => 'string, one or two sentences',
                'body' => 'string, markdown following the structure instructions above',
            ],
        ];
    }

    public static function structureInstructions(): string
    {
        return GrammarRuleStructureInstructions::text();
    }
}
