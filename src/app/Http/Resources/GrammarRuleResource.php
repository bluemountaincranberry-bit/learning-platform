<?php

namespace App\Http\Resources;

use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public/learner-facing shape — distinct from the admin-only
 * AdminGrammarRuleResource, which exposes coverage_state/counts that don't
 * belong in front of learners.
 */
class GrammarRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'language' => $this->language,
            'level' => $this->level,
            'summary' => $this->summary,
            'body' => $this->body,
            'topic' => $this->whenLoaded('topic', fn (): ?array => $this->topic === null ? null : [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
            ]),
            'is_personal' => $this->status === GrammarRule::STATUS_PERSONAL,
            'source_lesson' => $this->when(
                $this->status === GrammarRule::STATUS_PERSONAL,
                fn (): array => ['id' => $this->source_lesson_id, 'title' => $this->source_lesson_title],
            ),
            'examples' => $this->whenLoaded('examples', fn () => GrammarRuleExampleResource::collection($this->examples->values())),
            'in_my_list' => $this->when(isset($this->in_my_list), fn (): bool => (bool) $this->in_my_list),
            'learned' => $this->when(isset($this->learned), fn (): bool => (bool) $this->learned),
            // Gated on in_my_list rather than isset($this->confidence_manual)
            // directly — that value is legitimately null (no rating yet), and
            // isset() on a null Eloquent attribute returns false, which would
            // hide the key entirely instead of sending confidence_manual: null.
            'confidence_manual' => $this->when(isset($this->in_my_list), fn (): ?float => $this->confidence_manual !== null ? (float) $this->confidence_manual : null),
            'confidence_calculated' => $this->when(isset($this->in_my_list), fn (): ?float => $this->confidence_calculated !== null ? (float) $this->confidence_calculated : null),
        ];
    }
}
