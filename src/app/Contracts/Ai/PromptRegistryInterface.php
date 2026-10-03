<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Data\RenderedPrompt;
use Closure;

interface PromptRegistryInterface
{
    /**
     * Resolves the prompt to actually send for $key: an active, published
     * `PromptTemplateVersion` if one exists, otherwise exactly what
     * $default() returns — the caller's own hardcoded prompt, unchanged.
     * $variables must cover every `{{placeholder}}` the *active override*
     * might reference; $default is a closure (not a plain array) so a
     * caller with an expensive-to-build fallback (conditional strings,
     * cache lookups) only pays for it when actually needed.
     *
     * @param  array<string, string>  $variables
     * @param  Closure(): array{system: string, user: string, model?: ?string}  $default
     */
    public function resolve(string $key, array $variables, Closure $default): RenderedPrompt;
}
