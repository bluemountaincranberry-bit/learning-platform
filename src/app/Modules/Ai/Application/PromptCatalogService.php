<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\GrammarAgentService;
use App\Modules\Ai\Application\Agent\LessonAgentService;
use App\Modules\Ai\Application\Agent\ReviewAgentService;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use Illuminate\Support\Str;

/**
 * Answers "what prompts does this app actually send, grouped by the real
 * user-facing flow that triggers them" — every `prompt_templates.key` this
 * codebase resolves through `PromptRegistryInterface::resolve()`, plus
 * every agent's blueprint-derived key, in one place. Exists because the
 * graph-builder canvas only ever shows the `ai_analysis` graph's shape,
 * and the plain agent list only shows agents — neither answers "show me
 * everything, organized by what a user is actually doing" on its own.
 *
 * `PROMPTS` is a deliberately explicit, hand-maintained list (same
 * "closed, reviewed set" principle as `GraphNodeRegistry`/
 * `config('ai.agent.registry')`) rather than derived by scanning the
 * codebase for `promptRegistry->resolve(` call sites — grep-based
 * discovery would silently go stale in a different way (missing a
 * dynamically-built key, or picking up a call inside a test double) and
 * gives no place to attach a human label/description/flow grouping
 * anyway. Adding a new prompt key to a service means adding one entry
 * here too — the same discipline already required for a new graph node
 * type or a new registered agent.
 *
 * Most plain (non-agent) prompt entries have no `codeDefaultPreview`:
 * unlike an agent's blueprint (a static string), these are usually built
 * by a closure inside the owning service at call time, often from
 * runtime-only arguments (the actual transcript text, the actual lexeme
 * being explained) — there is no single static "default text" to show
 * without either duplicating that closure's logic here (guaranteed to
 * drift) or actually invoking it with fabricated inputs (misleading). The
 * catalog is honest about that gap rather than faking a preview. A plain
 * entry whose prompt genuinely *is* a fixed string (e.g. the AI-assistant
 * chat below) may set `code_default` to that same constant instead of
 * duplicating it as a literal.
 */
final class PromptCatalogService
{
    /**
     * @return array<int, array{flow: string, label: string, entries: array<int, array{key: string, agent_type: ?string}>}>
     */
    private const FLOWS = [
        [
            'flow' => 'agent_chat',
            'label' => 'Общение с агентом (чат)',
            'entries' => [
                ['key' => null, 'agent_type' => ContentAgentService::AGENT_TYPE, 'label' => 'Админ-чат создания урока', 'description' => 'ContentAgentService — диалог в админке "создать урок через чат" (PDF → черновик контента).'],
                ['key' => null, 'agent_type' => StudentTutorAgentService::AGENT_TYPE, 'label' => 'Чат-репетитор ученика', 'description' => 'StudentTutorAgentService — основной чат ученика с вызовом инструментов (объяснение грамматики, квиз, история занятий).'],
                ['key' => null, 'agent_type' => LessonAgentService::AGENT_TYPE, 'label' => 'Чат "Мои занятия"', 'description' => 'LessonAgentService — свободный чат ученика с преподавателем-ИИ внутри одного занятия.'],
                ['key' => null, 'agent_type' => GrammarAgentService::AGENT_TYPE, 'label' => 'Специалист по грамматике (handoff)', 'description' => 'GrammarAgentService — к нему переключается StudentTutorAgentService, когда вопрос про грамматику.'],
                ['key' => null, 'agent_type' => ReviewAgentService::AGENT_TYPE, 'label' => 'Специалист по повторению (handoff)', 'description' => 'ReviewAgentService — к нему переключается StudentTutorAgentService для планирования повторения.'],
                ['key' => 'chat_context_system_prompt', 'agent_type' => null, 'label' => 'Простой AI-чат (без инструментов)', 'description' => 'ChatContextAiService — более старый и простой чат-компаньон (без tool-calling), маршрут "AI chat".'],
            ],
        ],
        [
            'flow' => 'content_ingestion',
            'label' => 'Добавление контента',
            'entries' => [
                ['key' => 'content_analysis_system_prompt', 'agent_type' => null, 'label' => 'Разбор видео/текста при добавлении', 'description' => 'AiContentAnalysisService — реальный продовый путь: отправка YouTube → RunAiContentAnalysisJob → извлечение слов/грамматики. Тот же ключ, что у узла "Analyze" на канвасе графа ai_analysis (граф — бета-путь, этот промпт используется в обоих).'],
                ['key' => 'lesson_analysis_system_prompt', 'agent_type' => null, 'label' => 'Разбор заметок занятия', 'description' => 'LessonAnalysisService — кнопка "Разобрать урок" в "Мои занятия", извлекает слова/грамматику из заметок.'],
                ['key' => 'field_edit_grammar_rule_system_prompt', 'agent_type' => null, 'label' => 'Черновик грамматического правила (админка)', 'description' => 'GrammarRuleAiContentBuilder — кнопка "AI: Draft/Improve" на странице грамматического правила.'],
                ['key' => 'field_edit_lexeme_enrichment_system_prompt', 'agent_type' => null, 'label' => 'Обогащение слова (админка + автоматически)', 'description' => 'LexemeEnrichmentPromptBuilder — кнопка "AI: Enrich" на странице канонического слова, и EnrichLexemeAssociationsJob для каждой новой леммы (типизированные связи/примеры/перевод).'],
                ['key' => 'grammar_exercises_system_prompt', 'agent_type' => null, 'label' => 'Генерация упражнений', 'description' => 'AiGrammarExerciseService — генерация практических упражнений для грамматического правила.'],
            ],
        ],
        [
            'flow' => 'student_practice',
            'label' => 'Практика и подсказки ученику',
            'entries' => [
                ['key' => 'ai_explain_lexeme', 'agent_type' => null, 'label' => 'Объяснить слово', 'description' => 'AiExplainLexemeService::explain() — кнопка "Explain" на карточке слова.'],
                ['key' => 'ai_generate_context_sentence', 'agent_type' => null, 'label' => 'Новый пример предложения', 'description' => 'AiExplainLexemeService::generateContextSentence() — "Generate a new sentence" в практике по контексту.'],
                ['key' => 'ai_suggest_level', 'agent_type' => null, 'label' => 'Подсказать уровень CEFR (админка)', 'description' => 'AiExplainLexemeService::suggestLevel() — кнопка "Suggest CEFR level" в админке слов.'],
                ['key' => 'ai_suggest_metadata', 'agent_type' => null, 'label' => 'Авто-уровень+часть речи новой лексемы', 'description' => 'AiExplainLexemeService::suggestMetadata() — фоновый SuggestLexemeLevelJob при создании канонического слова.'],
                ['key' => 'sentence_practice_generate_system_prompt', 'agent_type' => null, 'label' => 'Генерация предложений для практики', 'description' => 'SentencePracticeService — генерация карточек для практики перевода на основе недавно изученных слов/грамматики.'],
                ['key' => 'sentence_practice_check_system_prompt', 'agent_type' => null, 'label' => 'Проверка ответа в практике предложений', 'description' => 'SentencePracticeService::checkAnswer() — оценка свободного ответа ученика (смысловая, не по строке).'],
            ],
        ],
        [
            'flow' => 'builder_tools',
            'label' => 'Инструменты билдера',
            'entries' => [
                ['key' => 'prompt_improvement_assistant_system_prompt', 'agent_type' => null, 'label' => 'AI-ассистент по промптам', 'description' => 'PromptImprovementAssistantService — чат-помощник в редакторе промпта, который сам этот текст и использует.', 'code_default' => PromptImprovementAssistantService::SYSTEM_PROMPT],
            ],
        ],
    ];

    /**
     * @return array<int, array{flow: string, label: string, entries: array<int, array{key: string, label: string, description: string, kind: string, has_override: bool, code_default_preview: ?string}>}>
     */
    public function flows(): array
    {
        $allKeys = [];
        foreach (self::FLOWS as $flow) {
            foreach ($flow['entries'] as $entry) {
                $allKeys[] = $entry['key'] ?? "agent_{$entry['agent_type']}_system_prompt";
            }
        }

        $overriddenKeys = PromptTemplate::query()
            ->whereNotNull('active_version_id')
            ->whereIn('key', $allKeys)
            ->pluck('key')
            ->all();

        return array_map(function (array $flow) use ($overriddenKeys): array {
            return [
                'flow' => $flow['flow'],
                'label' => $flow['label'],
                'entries' => array_map(
                    fn (array $entry) => $this->buildEntry($entry, $overriddenKeys),
                    $flow['entries']
                ),
            ];
        }, self::FLOWS);
    }

    /**
     * Looks up a single key across every flow — the prompt editor page
     * needs exactly one entry's full (untruncated) code default and tool
     * list, not the whole grouped catalog `flows()` builds for the list
     * page. Returns null for a key this catalog doesn't know about (a
     * genuinely unknown key, not "known but never overridden" — those
     * still resolve here with `has_override: false`).
     *
     * @return ?array{key: string, label: string, description: string, kind: string, has_override: bool, code_default_preview: ?string, tools: array<int, array{name: string, description: string, side_effect: string}>}
     */
    public function findByKey(string $key): ?array
    {
        foreach (self::FLOWS as $flow) {
            foreach ($flow['entries'] as $entry) {
                $entryKey = $entry['key'] ?? "agent_{$entry['agent_type']}_system_prompt";
                if ($entryKey === $key) {
                    return $this->buildEntry($entry, [], truncate: false);
                }
            }
        }

        return null;
    }

    /**
     * @param  array{key: ?string, agent_type: ?string, label: string, description: string, code_default?: string}  $entry
     * @param  array<int, string>  $overriddenKeys
     * @return array{key: string, label: string, description: string, kind: string, has_override: bool, code_default_preview: ?string, tools: array<int, array{name: string, description: string, side_effect: string}>}
     */
    private function buildEntry(array $entry, array $overriddenKeys, bool $truncate = true): array
    {
        if ($entry['agent_type'] !== null) {
            $agents = [...config('ai.agent.registry', []), ...config('ai.agent.specialists', [])];
            $class = $agents[$entry['agent_type']] ?? null;
            /** @var ?AgentBlueprint $blueprint */
            $blueprint = $class ? $class::blueprint() : null;
            $key = "agent_{$entry['agent_type']}_system_prompt";

            return [
                'key' => $key,
                'label' => $entry['label'],
                'description' => $entry['description'],
                'kind' => 'agent',
                'has_override' => in_array($key, $overriddenKeys, true),
                'code_default_preview' => $blueprint ? ($truncate ? Str::limit($blueprint->systemPrompt, 160) : $blueprint->systemPrompt) : null,
                'tools' => $blueprint ? $this->describeTools($blueprint) : [],
            ];
        }

        // A plain (non-agent) entry only has a static code_default when the
        // owning service's prompt is a fixed string, not built at call time
        // from runtime-only arguments — see this class's own docblock for
        // why most entries below leave it unset rather than faking one.
        $codeDefault = $entry['code_default'] ?? null;

        return [
            'key' => $entry['key'],
            'label' => $entry['label'],
            'description' => $entry['description'],
            'kind' => 'prompt',
            'has_override' => in_array($entry['key'], $overriddenKeys, true),
            'code_default_preview' => $codeDefault === null ? null : ($truncate ? Str::limit($codeDefault, 160) : $codeDefault),
            'tools' => [],
        ];
    }

    /**
     * Resolves each of the blueprint's tool class-strings through the
     * container into its real `AgentToolDefinition` — the same wire-format
     * description the model itself receives (`name`, `description`,
     * `sideEffect`) — rather than just listing raw class names, so an
     * admin sees "get_user_mistakes: Returns the student's most recent
     * failed reviews..." instead of a PHP FQCN. Read-only: this never
     * exposes a way to add/remove a tool from the blueprint — the tool
     * *list* stays a closed, reviewed, code-only set (see
     * `AiServiceProvider::resolveBlueprintSystemPrompt()`'s docblock for
     * why only the prompt text is admin-editable, not this).
     *
     * @return array<int, array{name: string, description: string, side_effect: string}>
     */
    private function describeTools(AgentBlueprint $blueprint): array
    {
        return array_map(function (string $toolClass): array {
            /** @var AgentTool $tool */
            $tool = app($toolClass);
            $definition = $tool->definition();

            return [
                'name' => $definition->name,
                'description' => $definition->description,
                'side_effect' => $definition->sideEffect,
            ];
        }, $blueprint->tools);
    }
}
