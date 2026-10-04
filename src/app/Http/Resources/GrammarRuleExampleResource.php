<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Learner-facing rule example. `target_spans` are [start, end) offsets in
 * characters (code points) into `example`, for highlighting the grammar
 * form; null for examples nobody marked yet.
 */
class GrammarRuleExampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'example' => $this->example,
            'translation' => $this->translation,
            'kind' => $this->kind,
            'mistake' => $this->mistake,
            'target_spans' => $this->target_spans,
            'origin' => $this->origin ?? 'admin',
            'from_content' => $this->content_id !== null,
        ];
    }
}
