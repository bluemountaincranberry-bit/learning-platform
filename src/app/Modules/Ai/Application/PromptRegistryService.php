<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Application\Data\RenderedPrompt;
use App\Modules\Ai\Application\Prompt\PromptTemplateRenderer;
use App\Contracts\Ai\PromptRegistryInterface;
use Closure;

/**
 * Single lookup point every AI call site goes through for its prompt text
 * (task: prompt-registry groundwork, docs/architecture/ai-platform-implementation-roadmap.md).
 * Deliberately DB-first-then-code-fallback rather than the other way
 * around: an admin publishing a new version should take effect on the
 * very next call, with no deploy — the same "no auto-publish, explicit
 * activation" shape the codebase already uses for AI candidates
 * (AiCandidateApplyService) and graph runs (HumanCheckpointNode), just
 * applied to prompt text instead of catalog data.
 */
final class PromptRegistryService implements PromptRegistryInterface
{
    public function __construct(
        private readonly PromptTemplateRenderer $renderer,
    ) {}

    public function resolve(string $key, array $variables, Closure $default): RenderedPrompt
    {
        $template = PromptTemplate::query()
            ->where('key', $key)
            ->with('activeVersion')
            ->first();

        $version = $template?->activeVersion;

        if ($version === null) {
            $fallback = $default();

            return new RenderedPrompt(
                system: $fallback['system'],
                user: $fallback['user'],
                model: $fallback['model'] ?? null,
                isOverride: false,
            );
        }

        return new RenderedPrompt(
            system: $this->renderer->render($version->system_template, $variables),
            user: $this->renderer->render($version->user_template, $variables),
            model: $version->model,
            isOverride: true,
        );
    }
}
