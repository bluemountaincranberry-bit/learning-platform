<?php

namespace App\Modules\Content\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLexemeResource extends JsonResource
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
            'lemma' => $this->lemma,
            'normalized_lemma' => $this->normalized_lemma,
            'part_of_speech' => $this->part_of_speech,
            'status' => $this->status,
            'level' => $this->level,
            'notes' => $this->notes,
            'examples_count' => $this->whenCounted('examples'),
            'rules_count' => $this->whenCounted('rules'),
            'linked_content_count' => $this->whenCounted('contentLinks'),
            'associations_count' => $this->whenCounted('associations'),
            'coverage_state' => $this->when(isset($this->coverage_state), $this->coverage_state),
            'rules' => $this->whenLoaded('rules', fn () => $this->rules->map(fn ($rule): array => [
                'id' => $rule->id,
                'slug' => $rule->slug,
                'title' => $rule->title,
                'status' => $rule->status,
                'level' => $rule->level,
                'topic_id' => $rule->topic_id,
            ])->values()),
            'examples' => $this->whenLoaded('examples', fn () => $this->examples->map(fn ($example): array => [
                'id' => $example->id,
                'language' => $example->language,
                'example' => $example->example,
                'translation' => $example->translation,
                'is_primary' => (bool) $example->is_primary,
                'sort_order' => (int) $example->sort_order,
            ])->values()),
            'associations' => $this->whenLoaded('associations', fn () => $this->associations->map(fn ($association): array => [
                'id' => $association->id,
                'type' => $association->type,
                'note' => $association->note,
                'sort_order' => (int) $association->sort_order,
                'related_lexeme' => $association->relatedLexeme === null ? null : [
                    'id' => $association->relatedLexeme->id,
                    'slug' => $association->relatedLexeme->slug,
                    'lemma' => $association->relatedLexeme->lemma,
                    'normalized_lemma' => $association->relatedLexeme->normalized_lemma,
                ],
            ])->values()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
