<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\LearningFlowMetricEvent;
use Illuminate\Support\Collection;

class LearningFlowMetricsService
{
    /** @param array<string, mixed> $metadata */
    public function recordSelection(int $userId, ?int $profileId, int $contentLexemeId, array $selection, array $metadata = []): void
    {
        $this->record('selection', $userId, $profileId, $contentLexemeId, $selection['activity'] ?? null, $selection['target_dimension'] ?? null, null, null, [...$metadata, 'stage' => $selection['stage'] ?? null, 'reason' => $selection['reason'] ?? null]);
    }

    public function recordOutcome(int $userId, ?int $profileId, ?int $contentLexemeId, string $activity, bool $success, ?string $dimension = null, ?int $points = null, array $metadata = []): void
    {
        $this->record('outcome', $userId, $profileId, $contentLexemeId, $activity, $dimension, $success, $points, $metadata);
    }

    /** @return array<string, mixed> */
    public function summary(?int $profileId = null, int $days = 30): array
    {
        $events = LearningFlowMetricEvent::query()->where('created_at', '>=', now()->subDays($days))->when($profileId !== null, fn ($query) => $query->where('learning_flow_profile_id', $profileId))->get();
        $outcomes = $events->where('event_type', 'outcome');

        return [
            'days' => $days,
            'selections' => $events->where('event_type', 'selection')->count(),
            'outcomes' => $outcomes->count(),
            'success_rate' => round($outcomes->count() > 0 ? $outcomes->where('success', true)->count() / $outcomes->count() * 100 : 0, 1),
            'by_activity' => $outcomes->groupBy('activity_type')->map(fn (Collection $items) => ['attempts' => $items->count(), 'success_rate' => round($items->where('success', true)->count() / max(1, $items->count()) * 100, 1)])->all(),
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function record(string $eventType, int $userId, ?int $profileId, ?int $contentLexemeId, ?string $activity, ?string $dimension, ?bool $success, ?int $points, array $metadata): void
    {
        LearningFlowMetricEvent::query()->create(['user_id' => $userId, 'learning_flow_profile_id' => $profileId, 'content_lexeme_id' => $contentLexemeId, 'event_type' => $eventType, 'activity_type' => $activity, 'dimension' => $dimension, 'success' => $success, 'points' => $points, 'metadata' => $metadata]);
    }
}
