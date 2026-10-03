<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Data\PromptImprovementReply;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;

/**
 * A prompt-engineering copilot for the builder's own admin: chats about
 * one prompt template's current system/user text and, only when it has a
 * concrete rewrite to suggest, returns it separately from the reply — see
 * `PromptImprovementReply`. Same propose-don't-apply shape as
 * `AiFieldEditService` and every AI candidate flow in this codebase; the
 * conversation itself is not persisted anywhere (see PromptEditorPage.vue)
 * — this is a scratchpad, not a system of record.
 *
 * `SYSTEM_PROMPT` is dogfooded through the exact prompt-registry path it
 * helps administer — resolved via `PromptRegistryInterface::resolve()`
 * under the key `prompt_improvement_assistant_system_prompt`
 * (`PromptCatalogService::FLOWS`) — so its own wording is editable through
 * this same builder, not a hardcoded exception to the rule.
 */
final class PromptImprovementAssistantService
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
        You are a prompt-engineering assistant helping an administrator of a
        language-learning app improve one specific AI prompt used somewhere
        in that app's product. You are given the prompt's key/purpose and
        its current system/user template text. The templates may use
        {{variable}} placeholders that the app substitutes at call time —
        never drop a placeholder the description implies is still needed
        unless the admin explicitly asks you to remove it.

        Reply to the administrator conversationally in Russian — explain
        tradeoffs, ask clarifying questions when the request is ambiguous,
        and flag real risks (e.g. removing a placeholder the code still
        fills in, a tone change that could break a downstream JSON parser
        or scoring rubric). Keep the proposed template text itself in
        whatever language the current template is already written in — do
        not translate the prompt itself into Russian, only your chat reply.

        Only fill proposed_system_template / proposed_user_template with a
        full replacement text when you have a concrete, ready-to-use
        rewrite the admin could insert as-is. Leave a field null rather
        than send a partial or placeholder rewrite, and leave both null
        when you are just discussing, asking a question, or not yet
        confident in a specific rewrite.
        PROMPT;

    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function reply(string $promptKey, string $currentSystemTemplate, string $currentUserTemplate, array $history, string $message): PromptImprovementReply
    {
        $rendered = $this->promptRegistry->resolve(
            'prompt_improvement_assistant_system_prompt',
            [],
            fn () => ['system' => self::SYSTEM_PROMPT, 'user' => '']
        );

        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'prompt_improvement_assistant.completeJson',
            ['feature' => 'prompt_improvement_assistant', 'prompt_key' => $promptKey],
            $rendered->system,
            $this->buildUserPrompt($promptKey, $currentSystemTemplate, $currentUserTemplate, $history, $message),
            [
                'reply' => 'string — your conversational reply to the administrator, in Russian',
                'proposed_system_template' => 'string or null — full replacement system template text, only when you have a concrete ready-to-use rewrite',
                'proposed_user_template' => 'string or null — full replacement user template text, only when you have a concrete ready-to-use rewrite',
            ],
        );

        return new PromptImprovementReply(
            reply: (string) ($result['reply'] ?? ''),
            proposedSystemTemplate: $result['proposed_system_template'] ?? null,
            proposedUserTemplate: $result['proposed_user_template'] ?? null,
        );
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function buildUserPrompt(string $promptKey, string $currentSystemTemplate, string $currentUserTemplate, array $history, string $message): string
    {
        $historyText = implode("\n\n", array_map(
            fn (array $turn): string => sprintf('%s: %s', $turn['role'] === 'user' ? 'Admin' : 'Assistant', $turn['content']),
            $history
        ));

        return <<<TEXT
            Prompt key: {$promptKey}

            Current system template:
            ---
            {$currentSystemTemplate}
            ---

            Current user template:
            ---
            {$currentUserTemplate}
            ---

            Conversation so far:
            {$historyText}

            New message from the admin:
            {$message}
            TEXT;
    }
}
