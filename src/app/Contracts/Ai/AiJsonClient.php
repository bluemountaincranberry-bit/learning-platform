<?php

namespace App\Contracts\Ai;

interface AiJsonClient
{
    /**
     * Request a structured JSON response from the model.
     *
     * @param  array<string, mixed>  $schema  Description of the expected response shape, appended to the prompt
     *                                        and, where the provider supports it, used to request JSON-mode output.
     * @param  ?string  $model  Overrides the provider's own default model for this one call — see AiClientInterface::complete().
     * @return array<string, mixed>
     */
    public function completeJson(string $systemPrompt, string $userPrompt, array $schema = [], ?string $model = null): array;
}
