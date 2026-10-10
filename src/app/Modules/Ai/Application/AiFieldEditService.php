<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiEditablePrompt;
use App\Contracts\Ai\AiConversationalEditablePrompt;
use App\Contracts\Ai\AiFieldEditCapability;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic, entity-agnostic "ask AI to draft/revise part of a record" service.
 *
 * Deliberately does not save anything — the caller (a Filament action, in
 * the current use) is responsible for what happens with the proposal, e.g.
 * filling a live edit form so the admin reviews it before hitting Save.
 * This mirrors the "AI never writes canonical data directly" rule already
 * used throughout the AI candidate extraction epic, just at field-level
 * instead of batch-candidate-level.
 */
class AiFieldEditService implements AiFieldEditCapability
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function propose(Model $subject, AiEditablePrompt $builder, ?string $instruction = null): array
    {
        $prompt = $builder->buildPrompt($subject, $instruction);

        return $this->proposePrepared($prompt, $subject::class, (int) $subject->getKey());
    }

    /**
     * @param  array{system: string, user: string, schema: array<string, mixed>}  $prompt
     * @return array<string, mixed>
     */
    public function proposePrepared(array $prompt, string $subjectType, int $subjectId): array
    {

        return $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'field_edit.completeJson',
            ['feature' => $prompt['feature'] ?? 'field_edit', 'subject_type' => $subjectType, 'subject_id' => $subjectId],
            $prompt['system'],
            $prompt['user'],
            $prompt['schema'],
            $prompt['model'] ?? null,
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function proposeConversation(
        Model $subject,
        AiConversationalEditablePrompt $builder,
        string $instruction,
        array $conversation,
        array $draft,
    ): array {
        return $this->proposePrepared(
            $builder->buildConversationPrompt($subject, $instruction, $conversation, $draft),
            $subject::class,
            (int) $subject->getKey(),
        );
    }
}
