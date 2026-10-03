<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Data\TokenUsage;

/**
 * Optional capability, separate from `AiClientInterface`/`AiJsonClient` on
 * purpose (Interface Segregation — a provider that cannot report usage,
 * e.g. a future Ollama usage integration that isn't built yet, is not
 * forced to implement this). A caller that wants span/cost tracking
 * checks `$client instanceof AiUsageReportingClient` rather than every
 * provider being required to support it.
 *
 * `lastUsage()` reflects the most recently *completed* `complete()`/
 * `completeJson()` call on this instance — safe because every provider
 * client is container-`bind()`-resolved (a fresh instance per resolution,
 * not a singleton reused across concurrent requests), the same lifecycle
 * `OpenAiClient` already relies on elsewhere.
 */
interface AiUsageReportingClient
{
    public function lastUsage(): ?TokenUsage;

    /**
     * The model that actually served the most recently completed call —
     * not necessarily equal to whatever `?string $model` the caller
     * passed in (a caller can omit it and get the provider's own
     * default), which is exactly why this exists: it's what
     * DatabaseSpanRecorder needs to look up `model_pricing` correctly.
     */
    public function lastModel(): ?string;
}
