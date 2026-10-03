<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'language' => $this->language,
            'level' => $this->level,
            'status' => $this->status,
            'source_url' => $this->source_url,
            'origin' => $this->origin,
            'created_by' => $this->created_by,
            'processing_failure_reason' => $this->processing_failure_reason,
            'created_at' => $this->resource->created_at instanceof \DateTimeInterface ? $this->resource->created_at->toIso8601String() : $this->created_at,
            'updated_at' => $this->resource->updated_at instanceof \DateTimeInterface ? $this->resource->updated_at->toIso8601String() : $this->updated_at,
        ];

        if ($this->resource->getAttribute('total_lexemes') !== null) {
            $data['learned_count'] = (int) $this->resource->getAttribute('learned_count');
            $data['in_learning_count'] = (int) $this->resource->getAttribute('in_learning_count');
            $data['total_lexemes'] = (int) $this->resource->getAttribute('total_lexemes');
            $data['progress_pct'] = (float) $this->resource->getAttribute('progress_pct');
        }

        // Set alongside the progress fields above (getReadyPaginatedWithProgress) —
        // same opt-in-only-when-a-user-is-known pattern, one batched query per page.
        if ($this->resource->getAttribute('ready_to_watch') !== null) {
            $data['ready_to_watch'] = (bool) $this->resource->getAttribute('ready_to_watch');
        }

        // Task 9.6: only set by ContentService::getMySubmissions() — omitted
        // everywhere else (catalog list/show), same opt-in pattern as the
        // progress fields above.
        if ($this->resource->getAttribute('pending_ai_suggestions_count') !== null) {
            $data['pending_ai_suggestions_count'] = (int) $this->resource->getAttribute('pending_ai_suggestions_count');
        }

        if ($this->resource->getAttribute('recommendation_score') !== null) {
            $data['recommendation_score'] = (float) $this->resource->getAttribute('recommendation_score');
            $data['recommendation_reasons'] = $this->resource->getAttribute('recommendation_reasons') ?? [];
        }

        // Full transcript/body text is heavy and only needed on the single-content
        // page, not the catalog list — controllers opt in per-request via this flag.
        if ($this->resource->getAttribute('include_source_text') === true) {
            $data['source_text'] = $this->source_text;
        }

        return $data;
    }
}
