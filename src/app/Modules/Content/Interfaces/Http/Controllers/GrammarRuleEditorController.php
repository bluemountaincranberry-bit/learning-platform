<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Contracts\Ai\AiErrorMessage;
use App\Contracts\Ai\AiFieldEditCapability;
use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApplyGrammarRuleEditorDraftRequest;
use App\Http\Requests\Api\GrammarRuleEditorProposalRequest;
use App\Http\Requests\Api\RestoreGrammarRuleEditorRevisionRequest;
use App\Modules\Content\Application\ApplyGrammarRuleEditorDraft;
use App\Modules\Content\Application\Ai\GrammarRuleAiContentBuilder;
use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Interfaces\Http\Resources\AdminGrammarRuleResource;
use App\Support\AiConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class GrammarRuleEditorController extends Controller
{
    public function __construct(
        private readonly AiFieldEditCapability $aiFieldEdit,
        private readonly GrammarRuleAiContentBuilder $promptBuilder,
        private readonly ApplyGrammarRuleEditorDraft $applyDraft,
        private readonly GrammarCatalogServiceInterface $grammarCatalog,
    ) {}

    public function show(Request $request, GrammarRule $rule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('editWithAi', $rule);

        return response()->json(['rule' => new AdminGrammarRuleResource($this->grammarCatalog->getRule($rule))]);
    }

    public function propose(GrammarRuleEditorProposalRequest $request, GrammarRule $rule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('editWithAi', $rule);
        abort_unless(AiConfig::isEnabled(), 503, 'AI editing is currently unavailable.');

        try {
            $proposal = $this->aiFieldEdit->proposeConversation(
                $rule->loadMissing(['topic', 'examples']),
                $this->promptBuilder,
                $request->validated('instruction'),
                $request->validated('conversation'),
                $request->validated('draft'),
            );
        } catch (AiClientException $exception) {
            return response()->json(['message' => AiErrorMessage::safe($exception)], 503);
        }

        $validator = Validator::make($proposal, [
            'assistant_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:12000'],
            'examples' => ['required', 'array', 'min:1', 'max:20'],
            'examples.*.id' => ['sometimes', 'integer'],
            'examples.*.language' => ['required', 'string', 'max:8'],
            'examples.*.example' => ['required', 'string', 'max:500'],
            'examples.*.translation' => ['nullable', 'string', 'max:500'],
            'examples.*.is_primary' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'AI returned an incomplete draft. Try again with a more specific request.'], 502);
        }

        $validatedProposal = $validator->validated();
        $assistantMessage = $validatedProposal['assistant_message'] ?? null;
        unset($validatedProposal['assistant_message']);
        $draftExampleIds = collect($request->validated('draft.examples'))
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();
        $validatedProposal['examples'] = array_map(function (array $example) use ($draftExampleIds): array {
            if (isset($example['id']) && ! in_array((int) $example['id'], $draftExampleIds, true)) {
                unset($example['id']);
            }

            return $example;
        }, $validatedProposal['examples']);

        return response()->json(['proposal' => $validatedProposal, 'message' => $assistantMessage]);
    }

    public function apply(ApplyGrammarRuleEditorDraftRequest $request, GrammarRule $rule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('editWithAi', $rule);
        $data = $request->validated();
        $updated = $this->applyDraft->execute(
            $rule,
            $request->user(),
            (int) $data['expected_version'],
            $data,
        );

        return response()->json(['rule' => new AdminGrammarRuleResource($this->grammarCatalog->getRule($updated))]);
    }

    public function revisions(Request $request, GrammarRule $rule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('editWithAi', $rule);
        $page = $rule->revisions()
            ->whereNotNull('changes->editor_snapshot')
            ->paginate(20);

        return response()->json([
            'revisions' => $page->getCollection()
                ->map(fn ($revision): array => [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at?->toIso8601String(),
                    'old' => $revision->changes['editor_snapshot']['old'],
                    'new' => $revision->changes['editor_snapshot']['new'],
                ])
                ->values(),
            'has_more' => $page->hasMorePages(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    public function restore(
        RestoreGrammarRuleEditorRevisionRequest $request,
        GrammarRule $rule,
        int $revision,
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('editWithAi', $rule);
        $revisionRecord = $rule->revisions()
            ->whereKey($revision)
            ->whereNotNull('changes->editor_snapshot')
            ->firstOrFail();
        $snapshot = $revisionRecord->changes['editor_snapshot']['old'];

        /** @var Model $user */
        $user = $request->user();
        $updated = $this->applyDraft->execute(
            $rule,
            $user,
            (int) $request->validated('expected_version'),
            $snapshot,
        );

        return response()->json(['rule' => new AdminGrammarRuleResource($this->grammarCatalog->getRule($updated))]);
    }
}
