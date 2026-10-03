<?php

namespace App\Modules\Ai\Application\Data;

/**
 * Token counts for one completed `complete()`/`completeJson()` call, as
 * reported by the provider's own response (`usage.prompt_tokens` /
 * `usage.completion_tokens` for OpenAI) — not estimated locally. Either
 * field can be null if the provider didn't report it.
 */
final readonly class TokenUsage
{
    public function __construct(
        public ?int $promptTokens,
        public ?int $completionTokens,
    ) {}
}
