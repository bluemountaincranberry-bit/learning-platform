<?php

namespace App\Modules\Content\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminGrammarTopicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'language' => $this->language,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => (int) $this->sort_order,
            'rules_count' => $this->whenCounted('rules'),
            'covered_rules_count' => $this->when(isset($this->covered_rules_count), (int) $this->covered_rules_count),
            'coverage_state' => $this->when(isset($this->coverage_state), $this->coverage_state),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
