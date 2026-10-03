<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Application\Agent\Graph\Contracts\GraphRunObserver;

/**
 * No-op observer — same pattern as `NullSpanRecorder`/`NullKafkaProducer`.
 * Useful for tests exercising `GraphRunner` purely on fake nodes, with no
 * interest in persistence at all.
 */
final class NullGraphRunObserver implements GraphRunObserver
{
    public function onStepCompleted(string $stepKey, GraphState $state): void {}

    public function onCompleted(string $lastStepKey, GraphState $state): void {}

    public function onPaused(string $stepKey, GraphState $state): void {}

    public function onFailed(string $stepKey, \Throwable $e): void {}
}
