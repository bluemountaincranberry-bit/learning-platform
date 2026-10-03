<?php

namespace App\Contracts\Ai;

use Illuminate\Database\Eloquent\Model;

/**
 * One implementation per entity type that can be AI-drafted/edited through
 * AiFieldEditService (e.g. GrammarRuleAiContentBuilder). The generic service
 * knows nothing about grammar, lexemes, or any other domain — all of that
 * lives in the implementation, so adding a new AI-editable entity later is
 * "write one small class," not "extend the service."
 */
interface AiEditablePrompt
{
    /**
     * @return array{system: string, user: string, schema: array<string, mixed>}
     */
    public function buildPrompt(Model $subject, ?string $instruction): array;
}
