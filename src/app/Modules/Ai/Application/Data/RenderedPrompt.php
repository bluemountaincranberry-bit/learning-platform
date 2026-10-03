<?php

namespace App\Modules\Ai\Application\Data;

/**
 * What `PromptRegistryInterface::resolve()` hands back to a caller: the
 * ready-to-send system/user text (already `{{variable}}`-substituted when
 * it came from an active `PromptTemplateVersion`, or exactly the caller's
 * own default otherwise), plus which model to use and whether an override
 * was actually applied — the latter purely for observability
 * (span/log metadata), callers should never branch on it.
 */
final readonly class RenderedPrompt
{
    public function __construct(
        public string $system,
        public string $user,
        public ?string $model,
        public bool $isOverride,
    ) {}
}
