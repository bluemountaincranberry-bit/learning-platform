<?php

namespace App\Contracts\Ai;

interface AiClientInterface
{
    /**
     * $model overrides the provider's own default for this one call (e.g.
     * a prompt_templates version's `model` field) — null keeps whatever
     * the implementation would otherwise use.
     */
    public function complete(string $systemPrompt, string $userPrompt, ?string $model = null): string;
}
