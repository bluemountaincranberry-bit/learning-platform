<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SaveGraphDefinitionDraftRequest;
use App\Http\Requests\Api\Admin\TestRunGraphRequest;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinitionVersion;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionAdminService;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionTestRunService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class GraphDefinitionController extends Controller
{
    public function __construct(
        private readonly GraphDefinitionAdminService $service,
        private readonly GraphDefinitionTestRunService $testRunService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list()->map(fn (PersistedGraphDefinition $g) => $this->summarize($g)),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        $record = $this->service->show($key);

        return response()->json([
            'data' => [
                ...$this->summarize($record),
                'versions' => $record->versions->map(fn (PersistedGraphDefinitionVersion $v) => $this->summarizeVersion($v)),
            ],
        ]);
    }

    public function store(string $key, SaveGraphDefinitionDraftRequest $request): JsonResponse
    {
        $version = $this->service->saveDraft(
            $key,
            $request->string('name')->toString(),
            $request->input('description'),
            $request->input('nodes'),
            $request->input('edges', []),
            $request->user()?->id,
        );

        return response()->json(['data' => $this->summarizeVersion($version)], 201);
    }

    public function publish(string $key, int $versionId): JsonResponse
    {
        try {
            $record = $this->service->publish($key, $versionId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->summarize($record)]);
    }

    /**
     * Note: unlike `PromptTemplateController::testRun()`, a failed AI call
     * inside the graph does not throw here — `GraphRunner` catches it and
     * reports it as `status: "failed"` in the response body (see
     * `GraphTestRunResult`), the same way a real graph run would. Only a
     * malformed *definition* (unknown version, invalid wiring, a
     * `ParallelNode`) throws `InvalidArgumentException`, before any node
     * ever runs.
     */
    public function testRun(string $key, int $versionId, TestRunGraphRequest $request): JsonResponse
    {
        try {
            $result = $this->testRunService->testRun(
                $key,
                $versionId,
                $request->string('test_transcript')->toString(),
                $request->input('source_language', 'en'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'status' => $result->status,
            'current_node' => $result->currentNode,
            'pause_reason' => $result->pauseReason,
            'failure_reason' => $result->failureReason,
            'lexeme_candidates' => $result->lexemeCandidates,
            'grammar_candidates' => $result->grammarCandidates,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(PersistedGraphDefinition $record): array
    {
        return [
            'key' => $record->key,
            'name' => $record->name,
            'description' => $record->description,
            'active_version_id' => $record->active_version_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeVersion(PersistedGraphDefinitionVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'nodes' => $version->nodes,
            'edges' => $version->edges,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
