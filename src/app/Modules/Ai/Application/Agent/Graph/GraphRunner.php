<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Application\Agent\Graph\Contracts\GraphRunObserver;
use InvalidArgumentException;
use Throwable;

/**
 * Pure execution engine for a `GraphDefinition` — knows nothing about
 * Eloquent, jobs, or which concrete graph/nodes it is running, exactly the
 * same design goal `AgentLoop` has for the agent tool-calling cycle (task
 * 1.2/5.2). Persistence of a run (`agent_graph_runs`) happens entirely
 * through the `GraphRunObserver` passed in, not here — see that contract's
 * docblock.
 *
 * Advances sequentially through `GraphDefinition::steps` by default; a
 * `RouterNode` can redirect via `GraphState::routeTo()` (task 4.7). Stops
 * and returns a `paused` result the moment a node leaves state paused
 * (`GraphNode`'s docblock) instead of running the rest of the definition —
 * `resume()` re-enters at that same step, letting the node itself decide
 * (given whatever new state resume() was called with) whether to pause
 * again or proceed.
 */
final class GraphRunner
{
    /**
     * Runs $definition starting at $fromStepKey (default: the first step)
     * until it completes, pauses, or a node throws.
     */
    public function run(
        GraphDefinition $definition,
        GraphState $state,
        ?GraphRunObserver $observer = null,
        ?string $fromStepKey = null,
    ): GraphRunResult {
        $observer ??= new NullGraphRunObserver;

        $stepKey = $fromStepKey ?? $definition->firstStep()->key;

        while (true) {
            $step = $definition->stepByKey($stepKey);

            if ($step === null) {
                throw new InvalidArgumentException(sprintf(
                    'GraphDefinition "%s" has no step with key "%s".',
                    $definition->name,
                    $stepKey
                ));
            }

            try {
                $state = $step->node->run($state);
            } catch (Throwable $e) {
                $observer->onFailed($step->key, $e);

                return GraphRunResult::failed($state, $step->key, $e->getMessage());
            }

            if ($state->isPaused()) {
                $observer->onPaused($step->key, $state);

                return GraphRunResult::paused($state, $step->key);
            }

            $observer->onStepCompleted($step->key, $state);

            $routeTarget = $state->consumeRouteTarget();
            $nextStep = $routeTarget !== null
                ? $definition->stepByKey($routeTarget)
                : $definition->stepAfter($step->key);

            if ($routeTarget !== null && $nextStep === null) {
                throw new InvalidArgumentException(sprintf(
                    'GraphDefinition "%s": RouterNode at step "%s" routed to unknown step "%s".',
                    $definition->name,
                    $step->key,
                    $routeTarget
                ));
            }

            if ($nextStep === null) {
                $observer->onCompleted($step->key, $state);

                return GraphRunResult::completed($state, $step->key);
            }

            $stepKey = $nextStep->key;
        }
    }
}
