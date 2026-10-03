<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers\Admin;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ChatWithPromptAssistantRequest;
use App\Http\Requests\Api\Admin\SavePromptTemplateDraftRequest;
use App\Http\Requests\Api\Admin\TestRunPromptRequest;
use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Domain\Models\PromptTemplateVersion;
use App\Modules\Ai\Application\PromptCatalogService;
use App\Modules\Ai\Application\PromptImprovementAssistantService;
use App\Modules\Ai\Application\PromptTemplateAdminService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class PromptTemplateController extends Controller
{
    public function __construct(
        private readonly PromptTemplateAdminService $service,
        private readonly PromptCatalogService $catalog,
        private readonly PromptImprovementAssistantService $assistant,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list()->map(fn (PromptTemplate $t) => $this->summarize($t)),
        ]);
    }

    /**
     * `code_default_system`/`tools` always come from the catalog entry
     * (when this key is a known one — see `PromptCatalogService`), not
     * from `prompt_templates`: an admin who has never saved a draft for
     * this key still gets a useful starting point instead of a blank
     * editor, and — for an agent's own key — the tool list that makes the
     * prompt's wording legible (what it's allowed to call). A key with no
     * `prompt_templates` row AND no catalog entry is genuinely unknown —
     * that's the only case that actually 404s.
     */
    public function show(string $key): JsonResponse
    {
        $entry = $this->catalog->findByKey($key);

        try {
            $template = $this->service->show($key);

            return response()->json([
                'data' => [
                    ...$this->summarize($template),
                    'versions' => $template->versions->map(fn (PromptTemplateVersion $v) => $this->summarizeVersion($v)),
                    'kind' => $entry['kind'] ?? 'prompt',
                    'code_default_system' => $entry['code_default_preview'] ?? null,
                    'tools' => $entry['tools'] ?? [],
                ],
            ]);
        } catch (ModelNotFoundException) {
            if ($entry === null) {
                return response()->json(['message' => 'Unknown prompt key.'], 404);
            }

            return response()->json([
                'data' => [
                    'key' => $key,
                    'name' => $entry['label'],
                    'description' => $entry['description'],
                    'active_version_id' => null,
                    'versions' => [],
                    'kind' => $entry['kind'],
                    'code_default_system' => $entry['code_default_preview'],
                    'tools' => $entry['tools'],
                ],
            ]);
        }
    }

    public function store(string $key, SavePromptTemplateDraftRequest $request): JsonResponse
    {
        $version = $this->service->saveDraft(
            $key,
            $request->string('name')->toString(),
            $request->input('description'),
            $request->string('system_template')->toString(),
            (string) ($request->input('user_template') ?? ''),
            $request->input('model'),
            $request->user()?->id,
        );

        return response()->json(['data' => $this->summarizeVersion($version)], 201);
    }

    public function publish(string $key, int $versionId): JsonResponse
    {
        try {
            $template = $this->service->publish($key, $versionId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->summarize($template)]);
    }

    public function testRun(string $key, TestRunPromptRequest $request): JsonResponse
    {
        try {
            $result = $this->service->testRun(
                $request->string('system_template')->toString(),
                $request->string('user_template')->toString(),
                $request->input('variables', []),
                $request->input('model'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json(['data' => [
            'rendered_system' => $result->renderedSystem,
            'rendered_user' => $result->renderedUser,
            'response' => $result->response,
        ]]);
    }

    /**
     * The prompt-improvement chat (PromptEditorPage.vue's "AI-ассистент"
     * tab) — stateless like testRun() above: the conversation lives only
     * in the browser tab, nothing here reads or writes prompt_templates.
     * $key is passed through purely as context for the assistant's reply
     * (which prompt it's discussing) and span metadata, not looked up.
     */
    public function chat(string $key, ChatWithPromptAssistantRequest $request): JsonResponse
    {
        try {
            $result = $this->assistant->reply(
                $key,
                $request->string('system_template')->toString(),
                $request->string('user_template')->toString(),
                $request->input('history', []),
                $request->string('message')->toString(),
            );
        } catch (AiClientException) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json(['data' => [
            'reply' => $result->reply,
            'proposed_system_template' => $result->proposedSystemTemplate,
            'proposed_user_template' => $result->proposedUserTemplate,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(PromptTemplate $template): array
    {
        return [
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'active_version_id' => $template->active_version_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeVersion(PromptTemplateVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'system_template' => $version->system_template,
            'user_template' => $version->user_template,
            'model' => $version->model,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
