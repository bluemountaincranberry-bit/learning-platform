<?php

namespace App\Filament\Pages;

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\AiAnalysisGraphService;
use App\Modules\Ai\Application\Agent\Graph\Definitions\AiAnalysisGraph;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Task 6.8: "отдельный простой список agent_graph_runs" — deliberately its
 * own page rather than folded into AgentTraces, since a graph run
 * (task 4.1, `GraphRunner`) is a different kind of thing than an agent
 * chat turn's trace, even though both are "AI platform activity an admin
 * might want to check on without a DB client".
 *
 * Task 8.5 adds this page's first action, "Approve & Apply", rendered per
 * row (see the Blade view) only for `AiAnalysisGraph` runs paused at
 * `review_checkpoint` — the graph-path analogue of clicking "Apply
 * approved AI candidates" on the live path. It does nothing beyond
 * resuming the paused graph run via `AiAnalysisGraphService::approveAndResume()`
 * (already fully tested in `AiAnalysisGraphServiceTest`): the admin is
 * expected to have already reviewed candidates in the exact same
 * relation-manager tables the live path uses (both paths write to the same
 * `ContentLexemeCandidate`/`ContentGrammarCandidate` rows), this page is
 * not a second review UI.
 */
class AgentGraphRuns extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Agent Graph Runs';

    protected static string|\UnitEnum|null $navigationGroup = 'Observability';

    protected string $view = 'filament.pages.agent-graph-runs';

    /**
     * Plain method rather than a mounted property — see AgentTraces::traces()'s
     * docblock: Livewire cannot sync a raw LengthAwarePaginator as public
     * state, so this is computed fresh on every render instead.
     */
    public function runs(): LengthAwarePaginator
    {
        return AgentGraphRun::query()
            ->latest('started_at')
            ->paginate(25);
    }

    /**
     * Whether the given row is a paused ai_analysis run awaiting review —
     * the only case `approveAndApplyAction` is meant to be shown for. A
     * plain method (not baked into the action's own `visible()`) so the
     * Blade view can decide whether to render the button at all without
     * mounting an action instance for every row.
     */
    public function canApproveAndApply(AgentGraphRun $run): bool
    {
        return $run->graph_name === AiAnalysisGraph::NAME
            && $run->current_node === 'review_checkpoint'
            && $run->status === AgentGraphRun::STATUS_PAUSED;
    }

    public function approveAndApplyAction(): Action
    {
        return Action::make('approveAndApply')
            ->label('Approve & Apply')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve & apply this graph run?')
            ->modalDescription('Resumes this paused graph run past its review checkpoint and applies its accepted candidates to the catalog — the same effect as the live "Apply approved AI candidates" button, just for the graph-engine path.')
            ->action(function (array $arguments): void {
                $graphRunId = (int) $arguments['graphRunId'];

                app(AiAnalysisGraphService::class)->approveAndResume($graphRunId);

                Notification::make()
                    ->title('Graph run approved and applied')
                    ->success()
                    ->send();
            });
    }
}
