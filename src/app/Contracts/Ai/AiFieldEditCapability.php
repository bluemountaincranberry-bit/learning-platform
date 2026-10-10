<?php

namespace App\Contracts\Ai;

use Illuminate\Database\Eloquent\Model;

/** Structured AI proposals for user-reviewed edits; implementations never save the subject. */
interface AiFieldEditCapability
{
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
    ): array;
}
