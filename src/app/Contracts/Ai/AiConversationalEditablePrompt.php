<?php

namespace App\Contracts\Ai;

use Illuminate\Database\Eloquent\Model;

/** Adds conversation and editable draft context to a field-edit prompt. */
interface AiConversationalEditablePrompt extends AiEditablePrompt
{
    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $draft
     * @return array{system: string, user: string, schema: array<string, mixed>, model?: ?string, feature?: string}
     */
    public function buildConversationPrompt(Model $subject, string $instruction, array $conversation, array $draft): array;
}
