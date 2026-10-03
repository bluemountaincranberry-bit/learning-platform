<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\EloquentGraphRunObserver;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionResolver;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResumeGraphJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $graphRunId) {}

    public function tags(): array
    {
        return ['graph-run:'.$this->graphRunId, 'job:resume-graph'];
    }

    public function handle(GraphRunner $runner, GraphDefinitionResolver $resolver): void
    {
        $dbRun = AgentGraphRun::query()->find($this->graphRunId);
        if ($dbRun === null || $dbRun->status !== AgentGraphRun::STATUS_PAUSED) {
            return;
        }

        $resolved = $resolver->resolve($dbRun->graph_name);
        $observer = EloquentGraphRunObserver::resume($this->graphRunId);
        $runner->run($resolved->definition, GraphState::fromArray($dbRun->state ?? []), $observer, $dbRun->current_node);
    }
}
