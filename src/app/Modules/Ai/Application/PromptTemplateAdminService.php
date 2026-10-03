<?php

namespace App\Modules\Ai\Application;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Domain\Models\PromptTemplateVersion;
use App\Modules\Ai\Application\Data\TestRunResult;
use App\Modules\Ai\Application\Prompt\PromptTemplateRenderer;
use App\Contracts\Ai\AiClientInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Admin CRUD over prompt_templates/prompt_template_versions — the write
 * side `PromptRegistryService` (read-only, resolve-at-call-time) never
 * touches. Kept as its own Application service, not folded into
 * PromptRegistryService, so the read path used by every live AI call
 * stays as small and boring as possible (Interface Segregation) — this
 * class is only ever reached from the admin-gated builder API.
 */
final class PromptTemplateAdminService
{
    public function __construct(
        private readonly AiClientInterface $client,
        private readonly PromptTemplateRenderer $renderer,
    ) {}

    public function list(): Collection
    {
        return PromptTemplate::query()->with('activeVersion')->orderBy('key')->get();
    }

    public function show(string $key): PromptTemplate
    {
        return PromptTemplate::query()->where('key', $key)->with('versions', 'activeVersion')->firstOrFail();
    }

    /**
     * Creates the template row on first use, then always appends a new
     * version rather than mutating an existing one — a draft never
     * affects live traffic until publish() below points active_version_id
     * at it, mirroring the human-in-the-loop "propose, don't auto-apply"
     * shape the rest of this codebase already uses for AI candidates.
     */
    public function saveDraft(string $key, string $name, ?string $description, string $systemTemplate, string $userTemplate, ?string $model, ?int $userId): PromptTemplateVersion
    {
        $template = PromptTemplate::query()->updateOrCreate(['key' => $key], ['name' => $name, 'description' => $description]);

        $nextVersion = ((int) $template->versions()->max('version')) + 1;

        return $template->versions()->create([
            'version' => $nextVersion,
            'system_template' => $systemTemplate,
            'user_template' => $userTemplate,
            'model' => $model,
            'created_by' => $userId,
        ]);
    }

    /**
     * @throws InvalidArgumentException if $versionId does not belong to the template identified by $key
     */
    public function publish(string $key, int $versionId): PromptTemplate
    {
        $template = PromptTemplate::query()->where('key', $key)->firstOrFail();
        $version = $template->versions()->where('id', $versionId)->first();

        if ($version === null) {
            throw new InvalidArgumentException("Version {$versionId} does not belong to prompt template \"{$key}\".");
        }

        $template->update(['active_version_id' => $version->id]);

        return $template->fresh();
    }

    /**
     * Renders $systemTemplate/$userTemplate with $variables through the
     * exact same `PromptTemplateRenderer` a real call would use, then
     * actually calls the model — "what will this send, and what does the
     * model say back", the whole point being to try a draft before
     * publishing it. Never touches prompt_templates/prompt_template_versions
     * — a test run is not a save.
     *
     * @param  array<string, string>  $variables
     *
     * @throws InvalidArgumentException if a template references a placeholder not present in $variables
     * @throws AiClientException if the underlying AI call fails
     */
    public function testRun(string $systemTemplate, string $userTemplate, array $variables, ?string $model): TestRunResult
    {
        $renderedSystem = $this->renderer->render($systemTemplate, $variables);
        $renderedUser = $this->renderer->render($userTemplate, $variables);

        $response = $this->client->complete($renderedSystem, $renderedUser, $model);

        return new TestRunResult($renderedSystem, $renderedUser, trim($response));
    }
}
